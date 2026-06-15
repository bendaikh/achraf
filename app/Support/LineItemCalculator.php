<?php

namespace App\Support;

class LineItemCalculator
{
    /**
     * @param  array<string, mixed>  $item
     * @return array{discount: float, discount_type: string, line_total: float}
     */
    public static function compute(array $item): array
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $taxRate = (float) ($item['tax_rate'] ?? 0);
        $discountInput = (float) ($item['discount'] ?? 0);
        $discountType = ($item['discount_type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';

        $lineBase = $quantity * $unitPrice;
        $discountAmount = $discountType === 'percent'
            ? $lineBase * ($discountInput / 100)
            : $discountInput;

        $lineHt = max(0, $lineBase - $discountAmount);
        $lineTax = $lineHt * ($taxRate / 100);
        $lineTotal = $lineHt + $lineTax;

        return [
            'discount' => round($discountInput, 2),
            'discount_type' => $discountType,
            'line_total' => round($lineTotal, 2),
        ];
    }
}
