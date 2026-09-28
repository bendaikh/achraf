<?php

namespace App\Support;

use App\Models\PosSale;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class StockSettings
{
    public const DEFAULT_LOW_THRESHOLD = 3;

    public const PICKING_ACTIVATED_AT_KEY = 'stock_picking_activated_at';

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

    /**
     * When enabled: Commande → Allocation/Réservation → Picking → Valider sortie.
     * When disabled: legacy immediate physical exit on prepare.
     */
    public static function pickingEnabled(): bool
    {
        return Setting::get('stock_picking_enabled', '0') === '1';
    }

    /**
     * Timestamp from which orders enter the active Picking workflow.
     * Persisted on activation (not "today") so unprepared orders remain visible afterwards.
     */
    public static function pickingActivatedAt(): ?CarbonInterface
    {
        $raw = Setting::get(self::PICKING_ACTIVATED_AT_KEY);
        if ($raw === null || $raw === '') {
            if (! self::pickingEnabled()) {
                return null;
            }

            // Self-heal: picking already ON without a cutoff → start from now.
            return self::recordPickingActivation();
        }

        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Record (or refresh) the picking activation cutoff.
     */
    public static function recordPickingActivation(?CarbonInterface $at = null): CarbonInterface
    {
        $activatedAt = $at ? Carbon::instance($at) : now();
        Setting::set(
            self::PICKING_ACTIVATED_AT_KEY,
            $activatedAt->toIso8601String(),
            'Date/heure d’activation Préparation / Picking'
        );

        return $activatedAt;
    }

    /**
     * Orders created/imported at or after activation are eligible for the picking queue.
     * Uses PosSale.created_at (Libromart insert / import time), not sold_at.
     */
    public static function orderEligibleForPicking(PosSale $order): bool
    {
        if (! self::pickingEnabled()) {
            return false;
        }

        $activatedAt = self::pickingActivatedAt();
        if (! $activatedAt) {
            return false;
        }

        $createdAt = $order->created_at ?? null;
        if (! $createdAt) {
            return false;
        }

        return Carbon::parse($createdAt)->greaterThanOrEqualTo($activatedAt);
    }

    /**
     * When enabled: Ventes → Retours clients / Scan retours workflow.
     */
    public static function customerReturnsEnabled(): bool
    {
        return Setting::get('stock_customer_returns_enabled', '0') === '1';
    }
}
