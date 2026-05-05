<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'shopify_variant_id',
        'title',
        'sku',
        'price',
        'compare_at_price',
        'barcode',
        'inventory_quantity',
        'option1',
        'option2',
        'option3',
        'weight',
        'weight_unit',
        'position',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'inventory_quantity' => 'integer',
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getFullTitleAttribute(): string
    {
        $parts = array_filter([
            $this->option1,
            $this->option2,
            $this->option3,
        ]);

        return $parts ? implode(' / ', $parts) : $this->title;
    }
}
