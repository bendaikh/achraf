<?php

namespace App\Support;

use App\Models\Reception;
use App\Models\Setting;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;

class LineItemCalculator
{
    /**
     * @param  array<string, mixed>  $item
     * @return array{discount: float, discount_type: string, line_total: float}
     */
    public static function compute(array $item, string $priceMode = 'sale'): array
    {
        $discountType = ($item['discount_type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
        $breakdown = self::breakdown(
            quantity: (float) ($item['quantity'] ?? 0),
            unitPrice: (float) ($item['unit_price'] ?? 0),
            taxRate: (float) ($item['tax_rate'] ?? 0),
            discountInput: (float) ($item['discount'] ?? 0),
            discountType: $discountType,
            priceMode: $priceMode,
        );

        return [
            'discount' => round((float) ($item['discount'] ?? 0), 2),
            'discount_type' => $discountType,
            'line_total' => round($breakdown['line_total'], 2),
        ];
    }

    /**
     * @return array{line_ht: float, line_tax: float, line_total: float, discount_amount: float}
     */
    public static function breakdown(
        float $quantity,
        float $unitPrice,
        float $taxRate,
        float $discountInput,
        string $discountType = 'fixed',
        string $priceMode = 'sale',
    ): array {
        $discountType = $discountType === 'percent' ? 'percent' : 'fixed';

        $lineBase = $quantity * $unitPrice;
        $discountAmount = $discountType === 'percent'
            ? $lineBase * ($discountInput / 100)
            : $discountInput;

        $lineHt = max(0, $lineBase - $discountAmount);
        $lineTax = $lineHt * ($taxRate / 100);
        $lineTotal = $lineHt + $lineTax;

        return [
            'line_ht' => $lineHt,
            'line_tax' => $lineTax,
            'line_total' => $lineTotal,
            'discount_amount' => $discountAmount,
        ];
    }

    public static function pricesAreTtcForMode(string $priceMode): bool
    {
        return $priceMode === 'sale' && Setting::getShopifyPriceType() === 'ttc';
    }

    /**
     * Convert legacy sale line items that stored unit_price as TTC into HT.
     */
    public static function normalizeStoredUnitPriceToHt(object|array $item): float
    {
        $quantity = (float) (is_array($item) ? ($item['quantity'] ?? 0) : $item->quantity);
        $unitPrice = (float) (is_array($item) ? ($item['unit_price'] ?? 0) : $item->unit_price);
        $taxRate = (float) (is_array($item) ? ($item['tax_rate'] ?? 0) : ($item->tax_rate ?? 0));
        $lineTotal = (float) (is_array($item) ? ($item['line_total'] ?? 0) : ($item->line_total ?? 0));

        if ($quantity <= 0 || $taxRate <= 0) {
            return $unitPrice;
        }

        $factor = 1 + ($taxRate / 100);
        $perUnitLine = $lineTotal / $quantity;

        // Legacy rows stored unit_price as TTC (equal to line_total per unit).
        // Correct rows store unit_price as HT and line_total as TTC — do not re-normalize those.
        if (abs($unitPrice - $perUnitLine) < 0.02) {
            return round($unitPrice / $factor, 2);
        }

        return $unitPrice;
    }

    public static function priceModeForDocument(object $document): string
    {
        return match (true) {
            $document instanceof SupplierInvoice,
            $document instanceof Reception,
            $document instanceof SupplierCreditNote,
            $document instanceof SupplierPurchaseOrder => 'purchase',
            default => 'sale',
        };
    }

    /**
     * @param  object|array<string, mixed>  $item
     * @return array{unit_price_ht: float, line_total: float}
     */
    public static function forDisplay(object|array $item, string $priceMode = 'sale'): array
    {
        $quantity = (float) (is_array($item) ? ($item['quantity'] ?? 0) : $item->quantity);
        $discountType = (is_array($item) ? ($item['discount_type'] ?? 'fixed') : ($item->discount_type ?? 'fixed'));

        $breakdown = self::breakdown(
            quantity: $quantity,
            unitPrice: (float) (is_array($item) ? ($item['unit_price'] ?? 0) : $item->unit_price),
            taxRate: (float) (is_array($item) ? ($item['tax_rate'] ?? 0) : ($item->tax_rate ?? 0)),
            discountInput: (float) (is_array($item) ? ($item['discount'] ?? 0) : ($item->discount ?? 0)),
            discountType: $discountType === 'percent' ? 'percent' : 'fixed',
            priceMode: $priceMode,
        );

        return [
            'unit_price_ht' => $quantity > 0 ? round($breakdown['line_ht'] / $quantity, 2) : 0.0,
            'line_total' => round($breakdown['line_total'], 2),
        ];
    }
}
