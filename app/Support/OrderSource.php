<?php

namespace App\Support;

class OrderSource
{
    public const SHOPIFY = 'shopify';

    public const JUMIA = 'jumia';

    public const LIBROMART = 'libromart';

    /**
     * Marketplace / Libromart order channels — never true POS cash.
     *
     * @return list<string>
     */
    public static function channelSources(): array
    {
        return [self::SHOPIFY, self::JUMIA, self::LIBROMART];
    }

    public static function isChannel(?string $source): bool
    {
        return in_array((string) $source, self::channelSources(), true);
    }

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
