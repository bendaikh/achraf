<?php

namespace App\Models;

use App\Support\LineItemCalculator;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'itemable_type',
        'itemable_id',
        'product_id',
        'ref',
        'designation',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'discount',
        'discount_type',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function itemable()
    {
        return $this->morphTo();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getDisplayLineTotalAttribute(): float
    {
        return LineItemCalculator::forDisplay($this, $this->resolvePriceMode())['line_total'];
    }

    public function getDisplayUnitPriceHtAttribute(): float
    {
        return LineItemCalculator::forDisplay($this, $this->resolvePriceMode())['unit_price_ht'];
    }

    protected function resolvePriceMode(): string
    {
        if ($this->relationLoaded('itemable') && $this->itemable) {
            return LineItemCalculator::priceModeForDocument($this->itemable);
        }

        return 'sale';
    }
}
