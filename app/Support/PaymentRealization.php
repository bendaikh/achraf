<?php

namespace App\Support;

use Carbon\CarbonInterface;

class PaymentRealization
{
    /**
     * A payment is realized (counts toward balance + treasury) only on/after its payment_date.
     * Future payment_date = scheduled / planned, not paid yet.
     */
    public static function isRealized(CarbonInterface|string|null $paymentDate, ?CarbonInterface $asOf = null): bool
    {
        if ($paymentDate === null || $paymentDate === '') {
            return false;
        }

        $asOfDate = ($asOf ?? now())->toDateString();
        $date = $paymentDate instanceof CarbonInterface
            ? $paymentDate->toDateString()
            : (string) $paymentDate;

        return $date <= $asOfDate;
    }
}
