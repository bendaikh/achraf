<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Support\VatCategoryHelper;

class ProductPurchasePriceService
{
    /**
     * @param  iterable<array<string, mixed>|object>  $items
     */
    public function syncLastPurchasePrices(iterable $items): void
    {
        foreach ($items as $item) {
            $productId = is_array($item)
                ? ($item['product_id'] ?? null)
                : ($item->product_id ?? null);

            if (! $productId) {
                continue;
            }

            $unitPrice = is_array($item)
                ? ($item['unit_price'] ?? null)
                : ($item->unit_price ?? null);

            if ($unitPrice === null) {
                continue;
            }

            Product::query()
                ->whereKey($productId)
                ->update(['last_purchase_price' => round((float) $unitPrice, 2)]);
        }
    }

    /**
     * Saisie manuelle du Prix dernier achat TTC (même champ que la fiche produit).
     * Conserve la date et l’utilisateur ayant modifié le prix.
     *
     * @return array{ht: float, ttc: float, source: string, has_cost: bool}
     */
    public function setManualLastPurchasePrice(Product $product, float $priceTtc, ?User $user = null): array
    {
        $ttc = round($priceTtc, 2);

        $product->last_purchase_price = $ttc;
        $product->last_purchase_price_updated_at = now();
        $product->last_purchase_price_updated_by = $user?->id;
        $product->save();

        return app(LocationStockReportService::class)->resolvePurchaseCost($product->fresh());
    }

    /**
     * Enregistre l’audit si le prix dernier achat change via la fiche produit.
     */
    public function recordManualChangeIfNeeded(Product $product, mixed $previousPrice, ?User $user = null): void
    {
        $previous = $previousPrice === null || $previousPrice === ''
            ? null
            : round((float) $previousPrice, 2);
        $current = $product->last_purchase_price === null || $product->last_purchase_price === ''
            ? null
            : round((float) $product->last_purchase_price, 2);

        if ($previous === $current) {
            return;
        }

        $product->last_purchase_price_updated_at = now();
        $product->last_purchase_price_updated_by = $user?->id;
    }

    public function htFromTtc(Product $product, float $priceTtc): float
    {
        $rate = VatCategoryHelper::rateFromLabel($product->vat_category);
        $factor = 1 + ($rate / 100);

        return round($priceTtc / $factor, 2);
    }
}
