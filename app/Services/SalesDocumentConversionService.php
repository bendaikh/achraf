<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Conversion commerciale ventes, calquée sur les achats :
 * separate = 1 source → 1 cible ; combined = N sources (même client) → 1 cible.
 * Tampon par type de cible, copie intégrale des lignes, référence d’origine sur chaque ligne.
 */
class SalesDocumentConversionService
{
    public const TARGET_PURCHASE_ORDER = 'purchase_order';

    public const TARGET_DELIVERY_NOTE = 'delivery_note';

    public const TARGET_INVOICE = 'invoice';

    /**
     * @param  Collection<int, Model>  $sources
     * @return Collection<int, Model>
     */
    public function convert(Collection $sources, string $mode, string $target): Collection
    {
        if ($sources->isEmpty()) {
            throw new \RuntimeException('Aucun document sélectionné.');
        }

        $this->assertAllowed($sources->first(), $target);
        $this->assertNotAlreadyConverted($sources, $target);

        if ($mode === 'combined' && $sources->pluck('client_id')->unique()->count() > 1) {
            throw new \RuntimeException('Les documents sélectionnés doivent appartenir au même client pour être regroupés.');
        }

        return DB::transaction(function () use ($sources, $mode, $target) {
            if ($mode === 'combined') {
                return collect([$this->createTarget($sources, $target)]);
            }

            return $sources->map(fn (Model $source) => $this->createTarget(collect([$source]), $target))->values();
        });
    }

    public function redirectUrl(string $target): string
    {
        return match ($target) {
            self::TARGET_PURCHASE_ORDER => route('purchase-orders.index'),
            self::TARGET_DELIVERY_NOTE => route('delivery-notes.index'),
            self::TARGET_INVOICE => route('invoices.index'),
            default => url()->previous(),
        };
    }

    public function successMessage(string $target, int $count): string
    {
        return match ($target) {
            self::TARGET_PURCHASE_ORDER => $count.' bon(s) de commande créé(s) avec succès.',
            self::TARGET_DELIVERY_NOTE => $count.' bon(s) de livraison créé(s) avec succès.',
            self::TARGET_INVOICE => $count.' facture(s) créée(s) avec succès.',
            default => $count.' document(s) créé(s) avec succès.',
        };
    }

    protected function assertAllowed(Model $source, string $target): void
    {
        $allowed = match (true) {
            $source instanceof Quote => [
                self::TARGET_PURCHASE_ORDER,
                self::TARGET_DELIVERY_NOTE,
                self::TARGET_INVOICE,
            ],
            $source instanceof PurchaseOrder => [
                self::TARGET_DELIVERY_NOTE,
                self::TARGET_INVOICE,
            ],
            $source instanceof DeliveryNote => [self::TARGET_INVOICE],
            default => [],
        };

        if (! in_array($target, $allowed, true)) {
            throw new \RuntimeException('Conversion non autorisée pour ce type de document.');
        }
    }

    /**
     * @param  Collection<int, Model>  $sources
     */
    protected function assertNotAlreadyConverted(Collection $sources, string $target): void
    {
        $already = $sources->filter(function (Model $source) use ($target) {
            return match ($target) {
                self::TARGET_PURCHASE_ORDER => $source instanceof Quote && $source->isConvertedToPurchaseOrder(),
                self::TARGET_DELIVERY_NOTE => method_exists($source, 'isConvertedToDeliveryNote') && $source->isConvertedToDeliveryNote(),
                self::TARGET_INVOICE => method_exists($source, 'isConvertedToInvoice') && $source->isConvertedToInvoice(),
                default => false,
            };
        });

        if ($already->isEmpty()) {
            return;
        }

        $message = match ($target) {
            self::TARGET_PURCHASE_ORDER => 'Un ou plusieurs devis sélectionnés ont déjà été convertis en bon de commande.',
            self::TARGET_DELIVERY_NOTE => 'Un ou plusieurs documents sélectionnés ont déjà été convertis en bon de livraison.',
            default => 'Un ou plusieurs documents sélectionnés ont déjà été convertis en facture.',
        };

        throw new \RuntimeException($message);
    }

    /**
     * @param  Collection<int, Model>  $sources
     */
    protected function createTarget(Collection $sources, string $target): Model
    {
        $document = match ($target) {
            self::TARGET_PURCHASE_ORDER => $this->createPurchaseOrder($sources),
            self::TARGET_DELIVERY_NOTE => $this->createDeliveryNote($sources),
            self::TARGET_INVOICE => $this->createInvoice($sources),
            default => throw new \RuntimeException('Type de conversion inconnu.'),
        };

        $this->copyItems($sources, $document);

        foreach ($sources as $source) {
            $this->stampConverted($source, $document);
        }

        if ($document instanceof Invoice) {
            app(\App\Services\Access\CommissionService::class)->syncForInvoice($document->fresh());
        }

        return $document;
    }

    /**
     * @param  Collection<int, Model>  $sources
     */
    protected function createPurchaseOrder(Collection $sources): PurchaseOrder
    {
        /** @var Quote $first */
        $first = $sources->first();
        $labels = $this->originLabels($sources);

        return PurchaseOrder::create([
            'reference' => DocumentNumberService::generate('bc_client'),
            'client_id' => $first->client_id,
            'collaborator_id' => app(\App\Services\Access\CommercialAttributionService::class)->collaboratorIdFromSources($sources)
                ?? $first->collaborator_id,
            'created_by_user_id' => auth()->id(),
            'order_date' => now()->toDateString(),
            'expiry_date' => $first->expiry_date?->toDateString(),
            'currency' => $first->currency,
            'status' => 'confirmé',
            'model' => $first->model,
            'matricule' => $first->matricule,
            'remarks' => 'Généré depuis Devis: '.$labels->implode(', '),
            'conditions' => $first->conditions,
            'subtotal' => 0,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 0,
        ]);
    }

    /**
     * @param  Collection<int, Model>  $sources
     */
    protected function createDeliveryNote(Collection $sources): DeliveryNote
    {
        $first = $sources->first();
        $labels = $this->originLabels($sources);

        return DeliveryNote::create([
            'delivery_number' => DocumentNumberService::generate('bon_livraison'),
            'client_id' => $first->client_id,
            'collaborator_id' => app(\App\Services\Access\CommercialAttributionService::class)->collaboratorIdFromSources($sources)
                ?? $first->collaborator_id,
            'created_by_user_id' => auth()->id(),
            'delivery_date' => now()->toDateString(),
            'shipping_date' => now()->toDateString(),
            'reference' => $this->sourceNumbers($sources)->implode(', '),
            'currency' => $first->currency,
            'status' => 'En cours',
            'stock_location' => $first->stock_location ?? 'DEPOT',
            'model' => $first->model,
            'matricule' => $first->matricule,
            'remarks' => 'Généré depuis '.$this->sourceKindLabel($first).': '.$labels->implode(', '),
            'conditions' => $first->conditions ?? null,
            'subtotal' => 0,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 0,
        ]);
    }

    /**
     * @param  Collection<int, Model>  $sources
     */
    protected function createInvoice(Collection $sources): Invoice
    {
        $first = $sources->first();
        $labels = $this->originLabels($sources);

        return Invoice::create([
            'invoice_number' => DocumentNumberService::generate('facture'),
            'client_id' => $first->client_id,
            'collaborator_id' => app(\App\Services\Access\CommercialAttributionService::class)->collaboratorIdFromSources($sources)
                ?? $first->collaborator_id,
            'created_by_user_id' => auth()->id(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => $first->currency,
            'stock_location' => $first->stock_location ?? 'DEPOT',
            'model' => $first->model,
            'matricule' => $first->matricule,
            'remarks' => 'Générée depuis '.$this->sourceKindLabel($first).': '.$labels->implode(', '),
            'conditions' => $first->conditions ?? null,
            'subtotal' => 0,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 0,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'commercial_status' => 'normal',
        ]);
    }

    /**
     * @param  Collection<int, Model>  $sources
     */
    protected function copyItems(Collection $sources, Model $target): void
    {
        $subtotal = 0;

        foreach ($sources as $source) {
            $origin = $this->originLabel($source);

            foreach ($source->items as $item) {
                $target->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'ref' => $item->ref,
                    'designation' => $item->designation,
                    'description' => $item->description,
                    'source_document_reference' => $origin,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'discount' => $item->discount,
                    'discount_type' => $item->discount_type ?? 'fixed',
                    'line_total' => $item->line_total,
                ]);

                $subtotal += (float) $item->line_total;
            }
        }

        $target->update([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);
    }

    protected function stampConverted(Model $source, Model $target): void
    {
        if ($target instanceof PurchaseOrder) {
            $source->update([
                'converted_purchase_order_id' => $target->id,
                'converted_to_purchase_order_at' => now(),
            ]);

            return;
        }

        if ($target instanceof DeliveryNote) {
            $source->update([
                'converted_delivery_note_id' => $target->id,
                'converted_to_delivery_note_at' => now(),
            ]);

            return;
        }

        if ($target instanceof Invoice) {
            $source->update([
                'converted_invoice_id' => $target->id,
                'converted_to_invoice_at' => now(),
            ]);
        }
    }

    public function originLabel(Model $document): string
    {
        return match (true) {
            $document instanceof Quote => (string) $document->quote_number,
            $document instanceof PurchaseOrder => (string) $document->reference,
            $document instanceof DeliveryNote => (string) $document->delivery_number,
            $document instanceof Invoice => (string) $document->invoice_number,
            default => '#'.$document->getKey(),
        };
    }

    /**
     * @param  Collection<int, Model>  $sources
     * @return Collection<int, string>
     */
    protected function originLabels(Collection $sources): Collection
    {
        return $sources->map(fn (Model $source) => $this->originLabel($source))->values();
    }

    /**
     * @param  Collection<int, Model>  $sources
     * @return Collection<int, string>
     */
    protected function sourceNumbers(Collection $sources): Collection
    {
        return $this->originLabels($sources);
    }

    protected function sourceKindLabel(Model $source): string
    {
        return match (true) {
            $source instanceof Quote => 'Devis',
            $source instanceof PurchaseOrder => 'Bon(s) de Commande',
            $source instanceof DeliveryNote => 'Bon(s) de Livraison',
            default => 'document(s)',
        };
    }
}
