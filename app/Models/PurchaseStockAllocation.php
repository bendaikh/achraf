<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PurchaseStockAllocation extends Model
{
    protected $fillable = [
        'allocatable_type',
        'allocatable_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'warehouse_location_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }
}
