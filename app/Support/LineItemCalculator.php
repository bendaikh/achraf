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
        $pricesAreTtc = self::pricesAreTtcForMode($priceMode);
        $discountType = $discountType === 'percent' ? 'percent' : 'fixed';

        $lineBase = $quantity * $unitPrice;
        $discountAmount = $discountType === 'percent'
            ? $lineBase * ($discountInput / 100)
            : $discountInput;

        if ($pricesAreTtc) {
            $lineTtc = max(0, $lineBase - $discountAmount);
            $lineHt = $taxRate > 0 ? $lineTtc / (1 + $taxRate / 100) : $lineTtc;
            $lineTax = $lineTtc - $lineHt;
            $lineTotal = $lineTtc;
        } else {
            $lineHt = max(0, $lineBase - $discountAmount);
            $lineTax = $lineHt * ($taxRate / 100);
            $lineTotal = $lineHt + $lineTax;
        }

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
}
