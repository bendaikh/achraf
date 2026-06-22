<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\DocumentTaxBreakdown;
use App\Support\LineItemCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineItemCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_mode_treats_unit_price_as_ht_when_setting_is_ttc(): void
    {
        Setting::set('shopify_price_type', 'ttc');

        $computed = LineItemCalculator::compute([
            'quantity' => 1,
            'unit_price' => 583.33,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
        ]);

        $this->assertEquals(700.0, $computed['line_total']);
    }

    public function test_sale_mode_treats_unit_price_as_ht_when_setting_is_ht(): void
    {
        Setting::set('shopify_price_type', 'ht');

        $computed = LineItemCalculator::compute([
            'quantity' => 1,
            'unit_price' => 700,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
        ]);

        $this->assertEquals(840.0, $computed['line_total']);
    }

    public function test_purchase_mode_always_treats_unit_price_as_ht(): void
    {
        Setting::set('shopify_price_type', 'ttc');

        $computed = LineItemCalculator::compute([
            'quantity' => 1,
            'unit_price' => 700,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
        ], 'purchase');

        $this->assertEquals(840.0, $computed['line_total']);
    }

    public function test_document_tax_breakdown_matches_sale_ht_unit_prices(): void
    {
        Setting::set('shopify_price_type', 'ttc');

        $taxes = DocumentTaxBreakdown::fromItems([
            [
                'quantity' => 1,
                'unit_price' => 583.33,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ],
        ]);

        $this->assertEquals(583.33, $taxes['subtotal_ht']);
        $this->assertEquals(116.67, $taxes['tax_total']);
        $this->assertEquals(700.0, $taxes['total_ttc']);
    }

    public function test_for_display_returns_ht_unit_price_from_stored_ht_value(): void
    {
        Setting::set('shopify_price_type', 'ttc');

        $display = LineItemCalculator::forDisplay([
            'quantity' => 1,
            'unit_price' => 583.33,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
        ]);

        $this->assertEquals(583.33, $display['unit_price_ht']);
        $this->assertEquals(700.0, $display['line_total']);
    }

    public function test_normalize_stored_unit_price_converts_legacy_ttc_value(): void
    {
        $ht = LineItemCalculator::normalizeStoredUnitPriceToHt([
            'quantity' => 1,
            'unit_price' => 700,
            'tax_rate' => 20,
            'line_total' => 700,
        ]);

        $this->assertEquals(583.33, $ht);
    }

    public function test_normalize_stored_unit_price_keeps_correct_ht_value(): void
    {
        $ht = LineItemCalculator::normalizeStoredUnitPriceToHt([
            'quantity' => 1,
            'unit_price' => 358.33,
            'tax_rate' => 20,
            'line_total' => 430,
        ]);

        $this->assertEquals(358.33, $ht);
    }
}
