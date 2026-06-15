<?php

namespace App\Support;

use App\Models\Setting;

class VatCategoryHelper
{
    public static function defaultCategories(): array
    {
        return [
            'TVA (20%)',
            'TVA (10%)',
            'TVA (5.5%)',
            'TVA (2.1%)',
        ];
    }

    public static function all(): array
    {
        $configured = Setting::getList('vat_categories');

        return $configured !== [] ? $configured : self::defaultCategories();
    }

    public static function rateFromLabel(?string $label): float
    {
        if (! $label) {
            return 20.0;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $label, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return 20.0;
    }
}
