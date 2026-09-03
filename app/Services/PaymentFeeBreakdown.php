<?php

namespace App\Services;

/**
 * Canonical gross / delivery fees / net collected breakdown for sales payments.
 *
 * Invoice balance uses the gross (CRBT / montant facture).
 * Treasury prefers the net when fees are known.
 */
class PaymentFeeBreakdown
{
    /**
     * @param  array{
     *   amount?: float|int|string|null,
     *   gross_amount?: float|int|string|null,
     *   delivery_fees?: float|int|string|null,
     *   net_received?: float|int|string|null,
     * }  $data
     * @return array{gross_amount: float, delivery_fees: ?float, net_received: ?float}
     */
    public static function normalize(array $data): array
    {
        $amount = self::toMoney($data['amount'] ?? null);
        $gross = self::toMoney($data['gross_amount'] ?? null) ?? $amount;

        if ($gross === null) {
            throw new \InvalidArgumentException('Un montant brut (facture) est requis.');
        }

        $fees = self::toMoney($data['delivery_fees'] ?? null);
        $net = self::toMoney($data['net_received'] ?? null);

        if ($net === null && $fees !== null) {
            $net = round($gross - $fees, 2);
        } elseif ($fees === null && $net !== null) {
            $fees = round($gross - $net, 2);
        }

        return [
            'gross_amount' => $gross,
            'delivery_fees' => $fees,
            'net_received' => $net,
        ];
    }

    public static function toMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
