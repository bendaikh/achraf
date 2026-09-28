<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;

class FinanceSettings
{
    public const DEFAULT_CUTOFF = '2026-10-01';

    public static function cutoffDate(): Carbon
    {
        $raw = (string) Setting::get('finance_cutoff_date', self::DEFAULT_CUTOFF);

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return Carbon::parse(self::DEFAULT_CUTOFF)->startOfDay();
        }
    }

    public static function cutoffDateString(): string
    {
        return self::cutoffDate()->toDateString();
    }
}
