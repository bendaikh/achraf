<?php

namespace App\Support;

class InvoiceCommercialStatus
{
    public const NORMAL = 'normal';

    public const PARTIAL_RETURN = 'partial_return';

    public const TOTAL_RETURN = 'total_return';

    public const PARTIAL_REFUND = 'partial_refund';

    public const FULLY_REFUNDED = 'fully_refunded';

    public const EXCHANGE = 'exchange';

    public static function labels(): array
    {
        return [
            self::NORMAL => 'Normale',
            self::PARTIAL_RETURN => 'Retour partiel',
            self::TOTAL_RETURN => 'Retour total',
            self::PARTIAL_REFUND => 'Remboursement partiel',
            self::FULLY_REFUNDED => 'Remboursée totalement',
            self::EXCHANGE => 'Échange',
        ];
    }

    public static function badgeClasses(): array
    {
        return [
            self::NORMAL => 'bg-green-100 text-green-800',
            self::PARTIAL_RETURN => 'bg-amber-100 text-amber-800',
            self::TOTAL_RETURN => 'bg-red-100 text-red-800',
            self::PARTIAL_REFUND => 'bg-orange-100 text-orange-800',
            self::FULLY_REFUNDED => 'bg-red-100 text-red-800',
            self::EXCHANGE => 'bg-purple-100 text-purple-800',
        ];
    }

    public static function filterOptions(): array
    {
        return self::labels();
    }
}
