<?php

namespace App\Support;

use App\Models\Setting;

class StockSettings
{
    public const DEFAULT_LOW_THRESHOLD = 3;

    public static function lowThreshold(): int
    {
        return max(0, (int) Setting::get('stock_low_threshold', self::DEFAULT_LOW_THRESHOLD));
    }

    public static function minimumDefault(): int
    {
        return max(0, (int) Setting::get('stock_minimum_default', 0));
    }

    public static function allowNegative(): bool
    {
        return Setting::get('stock_allow_negative', '0') === '1';
    }

    public static function multiWarehouseEnabled(): bool
    {
        return Setting::get('stock_multi_warehouse', '1') !== '0';
    }

    public static function valuationMethod(): string
    {
        return (string) Setting::get('stock_valuation_method', '');
    }

    public static function controlEnabled(): bool
    {
        return Setting::get('stock_control_enabled', '1') !== '0';
    }
}
