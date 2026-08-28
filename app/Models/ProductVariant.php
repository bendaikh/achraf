<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'shopify_variant_id',
        'inventory_item_id',
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

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function totalStock(): int
    {
        return (int) $this->stocks()->sum('quantity');
    }

    public function onlineStock(): int
    {
        $onlineIds = Warehouse::query()->online()->pluck('id');

        return (int) $this->stocks()
            ->when($onlineIds->isNotEmpty(), fn ($query) => $query->whereIn('warehouse_id', $onlineIds), fn ($query) => $query->whereRaw('1 = 0'))
            ->sum('quantity');
    }

    public function physicalStock(): int
    {
        $onlineIds = Warehouse::query()->online()->pluck('id');

        return (int) $this->stocks()
            ->when($onlineIds->isNotEmpty(), fn ($query) => $query->whereNotIn('warehouse_id', $onlineIds))
            ->sum('quantity');
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
