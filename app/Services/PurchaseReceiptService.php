<?php

namespace App\Services;

use App\Models\PurchaseItem;
use App\Models\Reception;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Support\Collection;

class PurchaseReceiptService
{
    /**
     * Quantités déjà entrées en stock pour un BC.
     * Source de vérité : BR + factures avec entrée de stock validée.
     * Les BL historiques déjà stock_applied_at sont aussi comptés (évite double réception).
     *
     * @return Collection<int, array{product_id:int, ordered:int, received:int, remaining:int, designation:?string}>
     */
    public function progressForOrder(SupplierPurchaseOrder $order): Collection
    {
        $order->loadMissing('items');

        $receivedByProduct = collect()
            ->merge(Reception::query()->where('supplier_purchase_order_id', $order->id)->whereNotNull('stock_applied_at')->with('items')->get()->flatMap->items)
            ->merge(SupplierDeliveryNote::query()->where('supplier_purchase_order_id', $order->id)->whereNotNull('stock_applied_at')->with('items')->get()->flatMap->items)
            ->merge(SupplierInvoice::query()->where('supplier_purchase_order_id', $order->id)->whereNotNull('stock_applied_at')->with('items')->get()->flatMap->items)
            ->groupBy('product_id')
            ->map(fn ($items) => (int) $items->sum('quantity'));

        return $order->items->map(function (PurchaseItem $item) use ($receivedByProduct) {
            $productId = (int) ($item->product_id ?? 0);
            $ordered = (int) $item->quantity;
            $received = $productId ? (int) $receivedByProduct->get($productId, 0) : 0;

            return [
                'product_id' => $productId,
                'designation' => $item->designation,
                'ordered' => $ordered,
                'received' => min($received, $ordered),
                'remaining' => max(0, $ordered - $received),
            ];
        });
    }

    /**
     * @param  iterable<int, array{product_id?:int|null, quantity:int}>  $incoming
     */
    public function assertNotOverReceiving(SupplierPurchaseOrder $order, iterable $incoming): void
    {
        $progress = $this->progressForOrder($order)->keyBy('product_id');

        foreach ($incoming as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $qty = (int) ($row['quantity'] ?? 0);
            $remaining = (int) ($progress->get($productId)['remaining'] ?? 0);
            if ($qty > $remaining) {
                $label = $progress->get($productId)['designation'] ?? ('#'.$productId);
                throw new \RuntimeException(
                    'Réception partielle dépassée pour « '.$label.' » : reste à recevoir '.$remaining.', demandé '.$qty.'.'
                );
            }
        }
    }
}
