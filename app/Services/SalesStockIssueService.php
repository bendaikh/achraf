<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sortie de stock commerciale (BL validé / facture directe).
 * Idempotente via stock_applied_at — une vente ne sort le stock qu’une seule fois.
 */
class SalesStockIssueService
{
    public const DELIVERY_EXIT_STATUSES = ['livré', 'livre', 'validé', 'valide'];

    public function __construct(
        protected StockMovementService $stockMovement,
    ) {}

    public function resolveDefaultWarehouse(?int $warehouseId = null, ?string $stockLocation = null): Warehouse
    {
        $warehouse = $warehouseId
            ? Warehouse::query()->find($warehouseId)
            : (Warehouse::findByStockLocation($stockLocation) ?? Warehouse::fulfillmentWarehouse());

        if (! $warehouse || $warehouse->isOnline()) {
            throw new RuntimeException('Veuillez choisir un dépôt source physique (ex. Magasin Belvédère).');
        }

        return $warehouse;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
        ];
    }

    public function isDeliveryExitStatus(?string $status): bool
    {
        $normalized = mb_strtolower(trim((string) $status));

        return in_array($normalized, self::DELIVERY_EXIT_STATUSES, true);
    }

    /**
     * Sortie physique pour un BL validé. No-op si déjà sorti.
     *
     * @return list<string> warnings
     */
    public function applyForDeliveryNoteIfNeeded(DeliveryNote $note, bool $strict = true): array
    {
        if ($note->stock_applied_at) {
            return [];
        }

        if (! $this->isDeliveryExitStatus($note->status)) {
            return [];
        }

        $note->loadMissing('items');

        return $this->applyIfNeeded($note, $this->itemsPayloadFromDocument($note), $strict);
    }

    /**
     * Sortie pour facture directe, ou marquage si un BL source a déjà sorti.
     *
     * @return list<string> warnings
     */
    public function applyForInvoiceIfNeeded(Invoice $invoice, bool $strict = false): array
    {
        if ($invoice->stock_applied_at) {
            return [];
        }

        $invoice->loadMissing(['items', 'sourceDeliveryNotes']);

        $alreadyExitedNotes = $invoice->sourceDeliveryNotes
            ->filter(fn (DeliveryNote $note) => (bool) $note->stock_applied_at);

        if ($alreadyExitedNotes->isNotEmpty()) {
            // Facture depuis BL déjà sorti → pas de deuxième sortie.
            $invoice->update(['stock_applied_at' => now()]);

            return [];
        }

        // Facture depuis BL non encore sortis → une seule sortie ici, et on marque les BL.
        $pendingNotes = $invoice->sourceDeliveryNotes
            ->filter(fn (DeliveryNote $note) => ! $note->stock_applied_at);

        $warnings = $this->applyIfNeeded($invoice, $this->itemsPayloadFromDocument($invoice), $strict);

        foreach ($pendingNotes as $note) {
            if (! $note->stock_applied_at) {
                $note->update(['stock_applied_at' => $invoice->fresh()->stock_applied_at ?? now()]);
            }
        }

        return $warnings;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public function applyIfNeeded(Model $document, array $items, bool $strict = true): array
    {
        if ($document->getAttribute('stock_applied_at')) {
            return [];
        }

        if (! $this->canApplyStock($document)) {
            throw new RuntimeException('Ce type de document ne peut pas générer de sortie de stock.');
        }

        if ($document instanceof DeliveryNote && ! $this->isDeliveryExitStatus($document->status)) {
            return [];
        }

        $defaultWarehouse = $this->resolveDefaultWarehouse(
            null,
            $document->getAttribute('stock_location')
        );

        $warnings = [];

        DB::transaction(function () use ($document, $items, $defaultWarehouse, $strict, &$warnings) {
            $meta = $this->documentMeta($document);

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $qty = (int) ($item['quantity'] ?? 0);
                if (! $productId || $qty <= 0) {
                    continue;
                }

                $product = Product::query()->lockForUpdate()->find($productId);
                if (! $product || ! $product->tracksStock()) {
                    continue;
                }

                $warehouseId = isset($item['warehouse_id']) && $item['warehouse_id']
                    ? (int) $item['warehouse_id']
                    : (int) $defaultWarehouse->id;

                $warehouse = Warehouse::query()->find($warehouseId);
                if (! $warehouse || $warehouse->isOnline()) {
                    throw new RuntimeException('Dépôt source invalide pour « '.$product->name.' ».');
                }

                $locationId = $this->sanitizeLocationId(
                    isset($item['warehouse_location_id']) && $item['warehouse_location_id']
                        ? (int) $item['warehouse_location_id']
                        : null,
                    $warehouseId
                );

                if (! $locationId) {
                    $locationId = $this->preferredLocationWithStock(
                        $product,
                        $warehouseId,
                        isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null
                    );
                }

                $variantId = isset($item['product_variant_id']) && $item['product_variant_id']
                    ? (int) $item['product_variant_id']
                    : null;

                $warning = $this->stockMovement->decrease(
                    $product,
                    $qty,
                    'magasin',
                    $strict,
                    true,
                    StockMovement::TYPE_SALE,
                    $meta['type'],
                    (int) $document->getKey(),
                    $meta['reference'],
                    $warehouseId,
                    $locationId,
                    'Sortie vente '.$meta['reference'],
                    null,
                    $variantId
                );

                if ($warning !== null) {
                    $warnings[] = $warning;
                }
            }

            $document->update(['stock_applied_at' => now()]);
        });

        return $warnings;
    }

    /**
     * Reverse physical exit owned by a direct invoice (not inherited from BL).
     */
    public function reverseIfOwned(Invoice $invoice): void
    {
        if (! $invoice->stock_applied_at) {
            return;
        }

        $invoice->loadMissing(['items', 'sourceDeliveryNotes']);

        // If a source BL already owns the exit timestamp independently, skip reverse here.
        $blOwnsExit = $invoice->sourceDeliveryNotes->contains(
            fn (DeliveryNote $note) => $note->stock_applied_at
                && $note->stock_applied_at->ne($invoice->stock_applied_at)
        );

        // Prefer: only reverse when invoice itself created the movements.
        $hasOwnMovements = StockMovement::query()
            ->where('document_type', 'invoice')
            ->where('document_id', $invoice->id)
            ->where('type', StockMovement::TYPE_SALE)
            ->exists();

        if (! $hasOwnMovements || $blOwnsExit) {
            return;
        }

        foreach ($invoice->items as $item) {
            if (! $item->product_id || (int) $item->quantity <= 0) {
                continue;
            }

            $product = Product::query()->lockForUpdate()->find($item->product_id);
            if (! $product || ! $product->tracksStock()) {
                continue;
            }

            $warehouseId = $item->warehouse_id
                ? (int) $item->warehouse_id
                : (int) ($this->resolveDefaultWarehouse(null, $invoice->stock_location)->id);

            $this->stockMovement->increase(
                $product,
                (int) $item->quantity,
                'magasin',
                true,
                StockMovement::TYPE_CUSTOMER_RETURN,
                'invoice',
                $invoice->id,
                $invoice->invoice_number,
                $warehouseId,
                $item->warehouse_location_id ? (int) $item->warehouse_location_id : null,
                'Annulation facture '.$invoice->invoice_number,
                null,
                $item->product_variant_id ? (int) $item->product_variant_id : null
            );
        }
    }

    public function canApplyStock(Model $document): bool
    {
        return $document instanceof DeliveryNote || $document instanceof Invoice;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsPayloadFromDocument(Model $document): array
    {
        $document->loadMissing('items');

        return $document->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'quantity' => (int) $item->quantity,
            'warehouse_id' => $item->warehouse_id,
            'warehouse_location_id' => $item->warehouse_location_id,
        ])->all();
    }

    /**
     * Stamp invoice as already stock-applied when converting from exited BLs.
     *
     * @param  Collection<int, Model>  $sources
     */
    public function attachConvertedInvoice(Collection $sources, Invoice $invoice): void
    {
        $exitedNotes = $sources->filter(
            fn (Model $source) => $source instanceof DeliveryNote && $source->stock_applied_at
        );

        if ($exitedNotes->isNotEmpty()) {
            $invoice->update(['stock_applied_at' => now()]);
        }
    }

    protected function preferredLocationWithStock(Product $product, int $warehouseId, ?int $variantId): ?int
    {
        $query = \App\Models\ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->whereNotNull('warehouse_location_id')
            ->whereRaw('(quantity - reserved) > 0')
            ->orderBy('warehouse_location_id');

        if ($variantId) {
            $query->where(function ($q) use ($variantId) {
                $q->where('product_variant_id', $variantId)
                    ->orWhereNull('product_variant_id');
            });
        }

        $slot = $query->first();

        return $slot?->warehouse_location_id ? (int) $slot->warehouse_location_id : null;
    }

    protected function sanitizeLocationId(?int $locationId, int $warehouseId): ?int
    {
        if (! $locationId) {
            return null;
        }

        $belongs = WarehouseLocation::query()
            ->where('id', $locationId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        return $belongs ? $locationId : null;
    }

    /**
     * @return array{type: string, reference: string}
     */
    protected function documentMeta(Model $document): array
    {
        return match (true) {
            $document instanceof DeliveryNote => [
                'type' => 'delivery_note',
                'reference' => (string) $document->delivery_number,
            ],
            $document instanceof Invoice => [
                'type' => 'invoice',
                'reference' => (string) $document->invoice_number,
            ],
            default => [
                'type' => 'sales_document',
                'reference' => (string) $document->getKey(),
            ],
        };
    }
}
