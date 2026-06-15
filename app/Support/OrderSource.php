<?php

namespace App\Support;

class OrderSource
{
    public const SHOPIFY = 'shopify';

    public const JUMIA = 'jumia';

    public static function labels(): array
    {
        return [
            self::SHOPIFY => 'Shopify',
            self::JUMIA => 'Jumia',
            'pos' => 'Point de Vente',
        ];
    }
}
