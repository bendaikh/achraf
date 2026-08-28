<?php

namespace App\Support;

class DocumentAdjustmentCalculator
{
    public const TYPE_ADD = 'add';

    public const TYPE_DEDUCT = 'deduct';

    /**
     * @param  object|array<string, mixed>  $adjustment
     * @return array{
     *     type: string,
     *     amount: float,
     *     is_taxable: bool,
     *     tax_rate: float,
     *     tax: float,
     *     line_total: float,
     *     signed_total: float
     * }
     */
    public static function compute(object|array $adjustment): array
    {
        $type = self::normalizeType(is_array($adjustment) ? ($adjustment['type'] ?? self::TYPE_ADD) : ($adjustment->type ?? self::TYPE_ADD));
        $amount = round(abs((float) (is_array($adjustment) ? ($adjustment['amount'] ?? 0) : $adjustment->amount)), 2);
        $isTaxable = self::isTaxable($adjustment);
        $taxRate = $isTaxable
            ? round((float) (is_array($adjustment) ? ($adjustment['tax_rate'] ?? 0) : ($adjustment->tax_rate ?? 0)), 2)
            : 0.0;
        $tax = $isTaxable ? round($amount * ($taxRate / 100), 2) : 0.0;
        $lineTotal = round($amount + $tax, 2);
        $signed = $type === self::TYPE_DEDUCT ? -1 * $lineTotal : $lineTotal;

        return [
            'type' => $type,
            'amount' => $amount,
            'is_taxable' => $isTaxable,
            'tax_rate' => $taxRate,
            'tax' => $tax,
            'line_total' => $lineTotal,
            'signed_total' => $signed,
        ];
    }

    public static function normalizeType(mixed $type): string
    {
        return $type === self::TYPE_DEDUCT ? self::TYPE_DEDUCT : self::TYPE_ADD;
    }

    /**
     * @param  object|array<string, mixed>  $adjustment
     */
    public static function isTaxable(object|array $adjustment): bool
    {
        $value = is_array($adjustment)
            ? ($adjustment['is_taxable'] ?? false)
            : ($adjustment->is_taxable ?? false);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
