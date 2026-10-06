<?php

namespace App\Models;

use App\Support\LineItemCalculator;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchaseable_type', 'purchaseable_id', 'product_id', 'product_variant_id',
        'warehouse_id', 'warehouse_location_id', 'ref', 'designation',
        'description', 'source_document_reference', 'quantity', 'unit_price', 'tax_rate',
        'discount', 'discount_type', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchaseable()
    {
        return $this->morphTo();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseLocation()
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function getDisplayUnitPriceTtcAttribute(): float
    {
        return LineItemCalculator::forDisplay($this, 'purchase')['unit_price_ttc'];
    }

    public function getDisplayLineTotalAttribute(): float
    {
        return LineItemCalculator::forDisplay($this, 'purchase')['line_total'];
    }
}
