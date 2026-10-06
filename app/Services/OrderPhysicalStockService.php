<?php

namespace App\Services;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockReplenishmentNeed;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Support\StockSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderPhysicalStockService
{
    public function __construct(
        protected StockMovementService $stockMovement,
        protected ProductPurchaseHistoryService $purchaseHistory,
    ) {}

    /**
     * Entry point used by the order UI.
     * Picking ON  → allocate/reserve (no physical exit).
     * Picking OFF → legacy immediate physical exit.
     *
     * @return array{deducted: list<array>, unavailable: list<array>, warehouse: Warehouse, mode: string, reserved?: list<array>}
     */
    public function prepare(PosSale $order, ?int $warehouseId = null): array
    {
        // Pre-activation orders stay on the legacy immediate-exit path.
        if (StockSettings::pickingEnabled() && StockSettings::orderEligibleForPicking($order)) {
            return $this->allocate($order, $warehouseId);
        }

        $result = $this->process($order, $warehouseId);
        $result['mode'] = 'legacy_exit';

        return $result;
    }

    /**
     * Allocate (or top-up) available physical stock for an eligible picking order.
     * Safe to call repeatedly: never uses Shopify/online stock; only physical warehouses.
     *
     * @return array{deducted: list<array>, reserved: list<array>, unavailable: list<array>, warehouse: Warehouse, mode: string}|null
     */
    public function ensureAllocated(PosSale $order, ?int $warehouseId = null): ?array
    {
        if (! StockSettings::pickingEnabled() || ! StockSettings::orderEligibleForPicking($order)) {
            return null;
        }

        if ($order->physical_stock_processed_at) {
            return null;
        }

        return $this->allocate($order, $warehouseId);
    }

    public function hasActiveReservations(PosSale $order): bool
    {
        return StockReservation::query()
            ->active()
            ->where('source_type', 'pos_sale')
            ->where('source_id', $order->id)
            ->exists();
    }

    /**
     * Allocate available physical stock to the order (reservation only).
     * Searches physical warehouses (fulfillment first), never the online mirror.
     * Disponible = stock physique − réservé (même source que Stock par emplacement).
     *
     * Stock suffisant → réservation complète.
     * Stock = 0 → aucun picking, besoin d’achat pour la totalité.
     * Stock partiel → réserve le dispo, reliquat en besoin d’achat.
     *
     * @return array{deducted: list<array>, reserved: list<array>, unavailable: list<array>, warehouse: Warehouse, mode: string}
     */
    public function allocate(PosSale $order, ?int $warehouseId = null): array
    {
        $preferred = $warehouseId
            ? Warehouse::query()->findOrFail($warehouseId)
            : Warehouse::fulfillmentWarehouse();

        if (! $preferred || $preferred->isOnline()) {
            throw new RuntimeException('Aucun emplacement physique de préparation n’est configuré (Magasin Belvédère).');
        }

        if ($order->physical_stock_processed_at) {
            throw new RuntimeException('Le stock physique de cette commande a déjà été traité (sortie validée).');
        }

        $order->loadMissing('items.product.variants', 'items.variant');

        return DB::transaction(function () use ($order, $preferred) {
            // Re-imports (Shopify/Jumia) recreate order lines with new ids: re-attach the
            // existing reservations / sorties to the current lines first, otherwise every
            // sync would reserve the same units again and drain Belvédère stock.
            $this->reconcileOrderReservations($order);
            $order->unsetRelation('items');
            $order->load('items.product.variants', 'items.variant');

            $lineQuantities = $this->lineQuantities($order);
            $reserved = [];
            $unavailable = [];
            $shortages = [];

            // Same physical source as « Stock par emplacement » : Belvédère first, then
            // other sellable physical depots. Never Shopify online, never SAV / quarantaine.
            $warehouses = Warehouse::query()
                ->active()
                ->sellable()
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$preferred->id])
                ->orderByDesc('is_fulfillment_default')
                ->orderBy('name')
                ->get();

            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product || ! $product->tracksStock()) {
                    continue;
                }

                $needed = (int) $item->quantity;
                if ($needed <= 0) {
                    continue;
                }

                $shortages[$product->id] ??= ['product' => $product, 'quantity' => 0];

                $alreadyReserved = (int) ($lineQuantities[$item->id]['reserved'] ?? 0);
                $alreadyShipped = (int) ($lineQuantities[$item->id]['shipped'] ?? 0);

                // Besoin = Qté commandée − déjà sortie − déjà réservée − stock physique disponible.
                $remaining = $needed - $alreadyShipped - $alreadyReserved;
                if ($remaining <= 0) {
                    continue;
                }

                $variantId = $this->resolveItemVariantId($product, $item);

                foreach ($warehouses as $warehouse) {
                    if ($remaining <= 0) {
                        break;
                    }

                    // Same physical slots as « Stock par emplacement » (SKU/variant Belvédère), never Shopify.
                    $slots = $this->physicalSlotsQuery($product, (int) $warehouse->id, $variantId)
                        ->orderByRaw('CASE WHEN warehouse_location_id IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('warehouse_location_id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($slots as $slot) {
                        if ($remaining <= 0) {
                            break;
                        }
                        $available = $slot->available();
                        if ($available <= 0) {
                            continue;
                        }
                        $take = min($remaining, $available);
                        $slotVariantId = $slot->product_variant_id
                            ? (int) $slot->product_variant_id
                            : $variantId;
                        $this->stockMovement->reserveStock(
                            $product,
                            $take,
                            (int) $warehouse->id,
                            $slot->warehouse_location_id ? (int) $slot->warehouse_location_id : null,
                            $slotVariantId,
                            [
                                'source_type' => 'pos_sale',
                                'source_id' => $order->id,
                                'source_line_type' => 'pos_sale_item',
                                'source_line_id' => $item->id,
                                'document_reference' => $order->ticket_number,
                                'notes' => 'Allocation commande '.$order->ticket_number,
                                // Delta reservation: always add to an existing reservation on the same slot.
                                'top_up' => true,
                            ]
                        );
                        $reserved[] = [
                            'product_id' => $product->id,
                            'product_variant_id' => $slotVariantId,
                            'name' => $product->name,
                            'sku' => $item->variant?->sku ?: $product->ref,
                            'quantity' => $take,
                            'warehouse_id' => (int) $warehouse->id,
                            'warehouse_location_id' => $slot->warehouse_location_id,
                        ];
                        $remaining -= $take;
                    }
                }

                if ($remaining > 0) {
                    $shortages[$product->id]['quantity'] += $remaining;
                }
            }

            // One purchase need per (commande, produit) = somme des manques réels ; 0 → aucun besoin.
            foreach ($shortages as $shortage) {
                if ($shortage['quantity'] > 0) {
                    $unavailable[] = $this->registerNeed($order, $shortage['product'], $preferred, $shortage['quantity']);
                } else {
                    $this->clearNeed($order, $shortage['product'], $preferred);
                }
            }

            return [
                'deducted' => [],
                'reserved' => $reserved,
                'unavailable' => $unavailable,
                'warehouse' => $preferred,
                'mode' => 'reservation',
            ];
        });
    }

    /**
     * Validate physical exit for reserved quantities (picking workflow).
     * $lineIds = null → every reserved line of the order; otherwise ONLY the ticked
     * order lines (sortie partielle). Unreserved lines stay pending / besoin d’achat.
     * The order is closed (physical_stock_processed_at) only once every stocked line
     * has been fully shipped. Idempotent: already consumed reservations are skipped.
     *
     * @param  list<int>|null  $lineIds
     * @return array{consumed: int, skipped: int, quantity: int, completed: bool}
     */
    public function validateExit(PosSale $order, ?array $lineIds = null): array
    {
        if ($order->physical_stock_processed_at) {
            return ['consumed' => 0, 'skipped' => 0, 'quantity' => 0, 'completed' => true];
        }

        return DB::transaction(function () use ($order, $lineIds) {
            $this->reconcileOrderReservations($order);

            $query = StockReservation::query()
                ->active()
                ->where('source_type', 'pos_sale')
                ->where('source_id', $order->id);

            if ($lineIds !== null) {
                $lineIds = array_values(array_unique(array_map('intval', $lineIds)));
                if ($lineIds === []) {
                    return ['consumed' => 0, 'skipped' => 0, 'quantity' => 0, 'completed' => false];
                }
                $query->where('source_line_type', 'pos_sale_item')
                    ->whereIn('source_line_id', $lineIds);
            }

            $reservations = $query->lockForUpdate()->get();

            $consumed = 0;
            $quantity = 0;
            foreach ($reservations as $reservation) {
                $this->stockMovement->consumeReservation(
                    $reservation,
                    'Validation sortie commande '.$order->ticket_number
                );
                $consumed++;
                $quantity += (int) $reservation->quantity;
            }

            $completed = $this->orderFullyShipped($order);
            if ($completed) {
                $order->physical_stock_processed_at = now();
                $order->save();
            }

            return ['consumed' => $consumed, 'skipped' => 0, 'quantity' => $quantity, 'completed' => $completed];
        });
    }

    /**
     * True when every stocked line has been physically shipped (consumed reservations).
     */
    public function orderFullyShipped(PosSale $order): bool
    {
        $items = PosSaleItem::query()
            ->where('pos_sale_id', $order->id)
            ->with('product')
            ->get();
        $quantities = $this->lineQuantities($order);

        foreach ($items as $item) {
            if (! $item->product || ! $item->product->tracksStock() || (int) $item->quantity <= 0) {
                continue;
            }
            if ((int) ($quantities[$item->id]['shipped'] ?? 0) < (int) $item->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reserved (active) and shipped (consumed) quantities per order line.
     *
     * @return array<int, array{reserved:int, shipped:int}>
     */
    public function lineQuantities(PosSale $order): array
    {
        $rows = StockReservation::query()
            ->where('source_type', 'pos_sale')
            ->where('source_id', $order->id)
            ->where('source_line_type', 'pos_sale_item')
            ->whereIn('status', [StockReservation::STATUS_ACTIVE, StockReservation::STATUS_CONSUMED])
            ->get(['source_line_id', 'status', 'quantity']);

        $out = [];
        foreach ($rows as $row) {
            $lineId = (int) $row->source_line_id;
            $out[$lineId] ??= ['reserved' => 0, 'shipped' => 0];
            $key = $row->status === StockReservation::STATUS_CONSUMED ? 'shipped' : 'reserved';
            $out[$lineId][$key] += (int) $row->quantity;
        }

        return $out;
    }

    /**
     * Re-attach reservations whose order line no longer exists (Shopify/Jumia re-imports
     * delete and recreate pos_sale_items) to the current line of the same product, and
     * release reservations that exceed what the order still needs. Without this, each
     * sync re-reserved the same order (e.g. 11 units reserved for 1 ordered) and the
     * remaining orders were wrongly flagged « Manquant » with a purchase need.
     *
     * @return array{rebound:int, released:int}
     */
    public function reconcileOrderReservations(PosSale $order): array
    {
        $items = PosSaleItem::query()
            ->where('pos_sale_id', $order->id)
            ->with('product')
            ->orderBy('id')
            ->get();

        $capacity = [];
        $itemProduct = [];
        $itemVariant = [];
        foreach ($items as $item) {
            if (! $item->product || ! $item->product->tracksStock() || (int) $item->quantity <= 0) {
                continue;
            }
            $capacity[$item->id] = (int) $item->quantity;
            $itemProduct[$item->id] = (int) $item->product_id;
            $itemVariant[$item->id] = $item->product_variant_id ? (int) $item->product_variant_id : null;
        }

        $reservations = StockReservation::query()
            ->where('source_type', 'pos_sale')
            ->where('source_id', $order->id)
            ->whereIn('status', [StockReservation::STATUS_ACTIVE, StockReservation::STATUS_CONSUMED])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StockReservation::STATUS_CONSUMED])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $rebound = 0;
        $released = 0;
        $orphans = [];

        // Pass 1: reservations still pointing at an existing line of the same product.
        foreach ($reservations as $reservation) {
            $lineId = (int) $reservation->source_line_id;
            $qty = (int) $reservation->quantity;
            $bound = $reservation->source_line_type === 'pos_sale_item'
                && isset($capacity[$lineId])
                && $itemProduct[$lineId] === (int) $reservation->product_id;

            if (! $bound) {
                $orphans[] = $reservation;

                continue;
            }

            if ($reservation->status === StockReservation::STATUS_CONSUMED) {
                $capacity[$lineId] -= $qty;

                continue;
            }

            if ($capacity[$lineId] >= $qty) {
                $capacity[$lineId] -= $qty;

                continue;
            }

            // More reserved than still needed on this line → give the units back.
            $this->stockMovement->releaseReservation($reservation, 'Ajustement réservation (quantité commandée réduite)');
            $released++;
        }

        // Pass 2: orphan reservations → current line of the same product, else release.
        foreach ($orphans as $reservation) {
            $qty = (int) $reservation->quantity;
            $target = null;
            foreach ($capacity as $lineId => $left) {
                if ($itemProduct[$lineId] !== (int) $reservation->product_id || $left < $qty) {
                    continue;
                }
                if ($target === null
                    || ($itemVariant[$lineId] && $itemVariant[$lineId] === (int) $reservation->product_variant_id)) {
                    $target = $lineId;
                }
            }

            if ($target !== null) {
                $reservation->source_line_type = 'pos_sale_item';
                $reservation->source_line_id = $target;
                $reservation->save();
                $capacity[$target] -= $qty;
                $rebound++;

                continue;
            }

            if ($reservation->status === StockReservation::STATUS_ACTIVE) {
                $this->stockMovement->releaseReservation($reservation, 'Libération : ligne de commande supprimée / déjà couverte');
                $released++;
            }
            // Consumed without matching line: keep as history.
        }

        return ['rebound' => $rebound, 'released' => $released];
    }

    /**
     * Release active reservations when order is cancelled before exit.
     */
    public function releaseAllocations(PosSale $order, ?string $notes = null): int
    {
        $reservations = StockReservation::query()
            ->active()
            ->where('source_type', 'pos_sale')
            ->where('source_id', $order->id)
            ->get();

        $released = 0;
        foreach ($reservations as $reservation) {
            $this->stockMovement->releaseReservation(
                $reservation,
                $notes ?? 'Annulation commande '.$order->ticket_number
            );
            $released++;
        }

        // Remove open automatic needs for this order (workflow work data).
        StockReplenishmentNeed::query()
            ->open()
            ->where('pos_sale_id', $order->id)
            ->delete();

        return $released;
    }

    /**
     * Reset the active picking queue without touching physical stock or deleting orders.
     * Kept for Paramètres → Stock ; same logic as « Recalculer les besoins d’achat ».
     *
     * @return array{orders:int, released:int, reserved_qty:int, needs:int, cancelled_needs:int, shortage_qty:int}
     */
    public function resetPickingQueue(): array
    {
        return $this->recalculatePurchaseNeeds();
    }

    /**
     * Vraie remise à zéro du workflow Picking / Besoins d’achat :
     *  1. SUPPRIME les besoins automatiques non traités (ouverts) — pas de lignes à quantité 0 ;
     *  2. libère puis SUPPRIME les réservations non validées (sorties consommées conservées) ;
     *  3. resynchronise les compteurs « réservé » sur le stock physique réel ;
     *  4. repart de la date d’activation : réserve ce qui existe, recrée uniquement les vrais manques.
     * Ne modifie ni les commandes, ni les quantités physiques, ni les sorties déjà validées,
     * ni les besoins déjà commandés (BC fournisseur).
     *
     * @return array{orders:int, released:int, reserved_qty:int, needs:int, cancelled_needs:int, shortage_qty:int}
     */
    public function recalculatePurchaseNeeds(): array
    {
        if (! StockSettings::pickingEnabled()) {
            throw new RuntimeException('Le picking est désactivé. Activez-le avant de recalculer les besoins d’achat.');
        }

        $activatedAt = StockSettings::pickingActivatedAt();
        if (! $activatedAt) {
            throw new RuntimeException('Aucune date d’activation picking n’est enregistrée.');
        }

        return DB::transaction(function () use ($activatedAt) {
            // Commandes à retraiter : file active depuis l’activation, hors sorties déjà validées.
            $orders = PosSale::query()
                ->whereNull('physical_stock_processed_at')
                ->where('created_at', '>=', $activatedAt)
                ->where(function ($q) {
                    $q->whereHas('items.product', fn ($p) => $p->where('item_kind', 'stocked'))
                        ->orWhereExists(function ($r) {
                            $r->selectRaw('1')
                                ->from('stock_reservations')
                                ->whereColumn('stock_reservations.source_id', 'pos_sales.id')
                                ->where('stock_reservations.source_type', 'pos_sale')
                                ->whereIn('stock_reservations.status', [
                                    StockReservation::STATUS_ACTIVE,
                                    StockReservation::STATUS_CONSUMED,
                                ]);
                        });
                })
                ->orderBy('id')
                ->get();

            $orderIds = $orders->pluck('id')->all();

            // 1. Supprimer les besoins automatiques non traités (+ fantômes qty ≤ 0).
            //    Les besoins « ordered » (BC) et manuels (sans commande) restent.
            $deletedNeeds = StockReplenishmentNeed::query()
                ->where(function ($q) {
                    $q->where(function ($auto) {
                        $auto->open()->whereNotNull('pos_sale_id');
                    })->orWhere(function ($zero) {
                        $zero->open()->where('quantity_needed', '<=', 0);
                    });
                })
                ->delete();

            // 2. Libérer les réservations actives (stock physique inchangé), puis supprimer
            //    toutes les réservations de travail non validées (active/released). Les
            //    sorties consommées restent pour le calcul des reliquats.
            $released = 0;
            $affectedProductIds = [];
            if ($orderIds !== []) {
                foreach (
                    StockReservation::query()
                        ->where('source_type', 'pos_sale')
                        ->whereIn('source_id', $orderIds)
                        ->where('status', '!=', StockReservation::STATUS_CONSUMED)
                        ->pluck('product_id') as $productId
                ) {
                    $affectedProductIds[(int) $productId] = true;
                }
            }

            foreach ($orders as $order) {
                $reservations = StockReservation::query()
                    ->active()
                    ->where('source_type', 'pos_sale')
                    ->where('source_id', $order->id)
                    ->get();
                foreach ($reservations as $reservation) {
                    $affectedProductIds[(int) $reservation->product_id] = true;
                    $this->stockMovement->releaseReservation($reservation, 'Réinitialisation picking / besoins d’achat');
                    $released++;
                }
            }

            if ($orderIds !== []) {
                StockReservation::query()
                    ->where('source_type', 'pos_sale')
                    ->whereIn('source_id', $orderIds)
                    ->where('status', '!=', StockReservation::STATUS_CONSUMED)
                    ->delete();
            }

            // Compteurs réservés = somme des réservations actives restantes (jamais de qty fantôme).
            $this->rebuildReservedCounters(array_keys($affectedProductIds));

            // 3–4. Réallouer FIFO depuis le stock physique actuel ; seuls les manques > 0
            //     génèrent un besoin d’achat.
            $reservedQty = 0;
            $needs = 0;
            $shortageQty = 0;
            foreach ($orders as $order) {
                $result = $this->allocate($order->fresh(['items.product.variants', 'items.variant']));
                $reservedQty += collect($result['reserved'])->sum('quantity');
                $needs += count($result['unavailable']);
                $shortageQty += collect($result['unavailable'])->sum('quantity');
            }

            return [
                'orders' => $orders->count(),
                'released' => $released,
                'reserved_qty' => $reservedQty,
                'needs' => $needs,
                'cancelled_needs' => $deletedNeeds,
                'shortage_qty' => $shortageQty,
            ];
        });
    }

    /**
     * Rebuild product_stocks.reserved from active reservations for the given products.
     *
     * @param  list<int>  $productIds
     */
    protected function rebuildReservedCounters(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return;
        }

        foreach ($productIds as $productId) {
            $slots = ProductStock::query()
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->get();

            foreach ($slots as $slot) {
                $query = StockReservation::query()
                    ->active()
                    ->where('product_id', $productId)
                    ->where('warehouse_id', (int) $slot->warehouse_id);

                if ($slot->warehouse_location_id) {
                    $query->where('warehouse_location_id', (int) $slot->warehouse_location_id);
                } else {
                    $query->whereNull('warehouse_location_id');
                }

                if ($slot->product_variant_id) {
                    $query->where('product_variant_id', (int) $slot->product_variant_id);
                } else {
                    $query->whereNull('product_variant_id');
                }

                $slot->reserved = (int) $query->sum('quantity');
                $slot->save();
            }

            $product = Product::query()->find($productId);
            if ($product) {
                $this->stockMovement->refreshProductStockAggregates($product);
            }
        }
    }

    /**
     * Line-level picking detail for UI expand rows.
     * Reserved comes from active stock_reservations; physical Belvédère qty is unchanged by reservation.
     *
     * @return list<array{
     *   product: string,
     *   sku: string,
     *   ordered: int,
     *   belvedere_stock: int,
     *   location: string,
     *   reserved: int,
     *   missing: int
     * }>
     */
    public function pickingLinesForOrder(PosSale $order, ?Warehouse $belvedere = null): array
    {
        $belvedere = $belvedere ?: Warehouse::fulfillmentWarehouse();
        if (! $belvedere || $belvedere->isOnline()) {
            return [];
        }

        $order->loadMissing('items.product.variants', 'items.variant');

        $reservations = StockReservation::query()
            ->active()
            ->where('source_type', 'pos_sale')
            ->where('source_id', $order->id)
            ->with('location')
            ->get()
            ->groupBy('source_line_id');
        $quantities = $this->lineQuantities($order);

        $lines = [];
        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product || ! $product->tracksStock()) {
                continue;
            }

            $ordered = (int) $item->quantity;
            if ($ordered <= 0) {
                continue;
            }

            $lineReservations = $reservations->get($item->id) ?? collect();
            $reserved = (int) $lineReservations->sum('quantity');
            $shipped = (int) ($quantities[$item->id]['shipped'] ?? 0);
            $variantId = $this->resolveItemVariantId($product, $item);

            $slots = $this->physicalSlotsQuery($product, (int) $belvedere->id, $variantId)
                ->with('location')
                ->get();
            $belvedereStock = (int) $slots->sum(fn (ProductStock $slot) => (int) $slot->quantity);

            $locationCodes = $lineReservations
                ->map(fn (StockReservation $r) => $r->location?->code)
                ->filter()
                ->unique()
                ->values();

            if ($locationCodes->isEmpty()) {
                $fallback = $slots
                    ->filter(fn (ProductStock $slot) => (int) $slot->quantity > 0 && $slot->warehouse_location_id)
                    ->sortBy('warehouse_location_id')
                    ->first();
                if ($fallback?->location?->code) {
                    $locationCodes = collect([$fallback->location->code]);
                }
            }

            $missing = max(0, $ordered - $shipped - $reserved);
            $lines[] = [
                'item_id' => (int) $item->id,
                'product' => $item->designation ?: $product->name,
                'sku' => $item->variant?->sku ?: ($item->ref ?: $product->ref) ?: '—',
                'ordered' => $ordered,
                'belvedere_stock' => $belvedereStock,
                'location' => $locationCodes->isNotEmpty() ? $locationCodes->implode(', ') : '—',
                'reserved' => $reserved,
                'shipped' => $shipped,
                'missing' => $missing,
                'can_ship' => $reserved > 0,
                'status' => $shipped >= $ordered
                    ? 'shipped'
                    : ($reserved > 0 ? ($missing > 0 ? 'partial' : 'ready') : 'pending'),
            ];
        }

        return $lines;
    }

    /**
     * Deduct physical stock at the fulfillment location (Belvédère by default).
     * Never uses Shopify/online stock. Never creates negative physical stock.
     * Legacy path when picking is disabled.
     *
     * @return array{deducted: list<array>, unavailable: list<array>, warehouse: Warehouse}
     */
    public function process(PosSale $order, ?int $warehouseId = null): array
    {
        $warehouse = $warehouseId
            ? Warehouse::query()->findOrFail($warehouseId)
            : Warehouse::fulfillmentWarehouse();

        if (! $warehouse || $warehouse->isOnline()) {
            throw new RuntimeException('Aucun emplacement physique de préparation n’est configuré (Magasin Belvédère).');
        }

        if ($order->physical_stock_processed_at) {
            throw new RuntimeException('Le stock physique de cette commande a déjà été traité.');
        }

        $order->loadMissing('items.product.variants', 'items.variant');

        return DB::transaction(function () use ($order, $warehouse) {
            $deducted = [];
            $unavailable = [];

            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product || ! $product->tracksStock()) {
                    continue;
                }

                $needed = (int) $item->quantity;
                if ($needed <= 0) {
                    continue;
                }

                $variantId = $this->resolveItemVariantId($product, $item);
                $available = $this->availableAtWarehouse($product, (int) $warehouse->id, $variantId);
                if ($available >= $needed) {
                    // Prefer an existing Belvédère location slot when present (same as Stock par emplacement).
                    $locationId = $this->preferredLocationId($product, (int) $warehouse->id, $variantId);
                    $this->stockMovement->decrease(
                        $product,
                        $needed,
                        'magasin',
                        true,
                        true,
                        StockMovement::TYPE_ORDER_OUT,
                        'pos_sale',
                        $order->id,
                        $order->ticket_number,
                        (int) $warehouse->id,
                        $locationId,
                        'Sortie commande '.$order->ticket_number,
                        null,
                        $variantId
                    );
                    $deducted[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $variantId,
                        'name' => $product->name,
                        'sku' => $item->variant?->sku ?: $product->ref,
                        'quantity' => $needed,
                    ];
                    $this->clearNeed($order, $product, $warehouse);

                    continue;
                }

                $unavailable[] = $this->registerNeed($order, $product, $warehouse, $needed);
            }

            $order->physical_stock_processed_at = now();
            $order->save();

            return [
                'deducted' => $deducted,
                'unavailable' => $unavailable,
                'warehouse' => $warehouse,
            ];
        });
    }

    /**
     * Disponible = quantité physique − réservé, sur les slots physiques du dépôt
     * (même périmètre que Stock par emplacement ; ignore Shopify).
     */
    protected function availableAtWarehouse(Product $product, int $warehouseId, ?int $variantId): int
    {
        return (int) $this->physicalSlotsQuery($product, $warehouseId, $variantId)
            ->get()
            ->sum(fn (ProductStock $slot) => $slot->available());
    }

    /**
     * Prefer an existing location-tagged slot at the warehouse (e.g. BEL-STOCK).
     */
    protected function preferredLocationId(Product $product, int $warehouseId, ?int $variantId): ?int
    {
        $slot = $this->physicalSlotsQuery($product, $warehouseId, $variantId)
            ->whereNotNull('warehouse_location_id')
            ->whereRaw('(quantity - reserved) > 0')
            ->orderBy('warehouse_location_id')
            ->first();

        return $slot?->warehouse_location_id ? (int) $slot->warehouse_location_id : null;
    }

    /**
     * Physical stock slots matching Stock par emplacement for this SKU/variant.
     * Includes legacy null-variant rows when a concrete Default Title variant is resolved.
     */
    protected function physicalSlotsQuery(Product $product, int $warehouseId, ?int $variantId): Builder
    {
        $query = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId);

        if ($variantId) {
            // Exact variant + legacy product-level rows (unique slot key ignores variant).
            $query->where(function ($q) use ($variantId) {
                $q->where('product_variant_id', $variantId)
                    ->orWhereNull('product_variant_id');
            });
        } elseif ($product->hasVariants()) {
            // Multi-variant product without a resolved line variant: only legacy null rows.
            $query->whereNull('product_variant_id');
        }
        // Single-SKU product without a variant row: all physical slots for the product.

        return $query;
    }

    /**
     * Align order line → variant with StockMovementService (Default Title / shopify_variant_id).
     * Order imports often omit product_variant_id while Belvédère stock is tagged on Default Title.
     */
    protected function resolveItemVariantId(Product $product, PosSaleItem $item): ?int
    {
        if ($item->product_variant_id) {
            return (int) $item->product_variant_id;
        }

        if (! empty($item->shopify_variant_id)) {
            $fromShopify = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('shopify_variant_id', (string) $item->shopify_variant_id)
                ->value('id');
            if ($fromShopify) {
                return (int) $fromShopify;
            }
        }

        // Produit « simple » Shopify : une seule variante Default Title porte le stock Belvédère.
        if (! $product->hasVariants()) {
            $variants = $product->relationLoaded('variants')
                ? $product->variants
                : $product->variants()->get();
            if ($variants->count() === 1) {
                return (int) $variants->first()->id;
            }
        }

        return null;
    }

    /**
     * @return array{product_id:int, name:string, sku:?string, quantity:int, message:string}
     */
    protected function registerNeed(PosSale $order, Product $product, Warehouse $warehouse, int $needed): array
    {
        // Jamais de ligne besoin à quantité 0 (ni update, ni create).
        if ($needed <= 0) {
            $this->clearNeed($order, $product, $warehouse);

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->ref,
                'quantity' => 0,
                'message' => 'Aucun manque — besoin d’achat non créé.',
            ];
        }

        $last = $this->purchaseHistory->lastSuppliersForProducts([$product->id])[$product->id] ?? null;

        $existing = StockReplenishmentNeed::query()
            ->open()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('pos_sale_id', $order->id)
            ->first();

        if ($existing) {
            $existing->update([
                'quantity_needed' => $needed,
                'suggested_supplier_id' => $last['supplier_id'] ?? $existing->suggested_supplier_id,
            ]);
        } else {
            StockReplenishmentNeed::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'pos_sale_id' => $order->id,
                'quantity_needed' => $needed,
                'suggested_supplier_id' => $last['supplier_id'] ?? null,
                'status' => 'open',
                'notes' => 'Manque sur commande '.$order->ticket_number,
            ]);
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->ref,
            'quantity' => $needed,
            'message' => 'Stock physique insuffisant — besoin d’approvisionnement créé.',
        ];
    }

    /**
     * Remove a false replenishment need when physical available stock covers the order line.
     */
    protected function clearNeed(PosSale $order, Product $product, Warehouse $warehouse): void
    {
        StockReplenishmentNeed::query()
            ->open()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('pos_sale_id', $order->id)
            ->delete();
    }
}
