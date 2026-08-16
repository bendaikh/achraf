<?php

namespace App\Support;

class OrderSource
{
    public const SHOPIFY = 'shopify';

    public const JUMIA = 'jumia';

    public const LIBROMART = 'libromart';

    public static function labels(): array
    {
        return [
            self::SHOPIFY => 'Shopify',
            self::JUMIA => 'Jumia',
            self::LIBROMART => 'Libromart',
            'pos' => 'Point de Vente',
        ];
    }
}
