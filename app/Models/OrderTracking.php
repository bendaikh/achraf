<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTracking extends Model
{
    protected $fillable = [
        'pos_sale_id',
        'tracking_number',
        'carrier',
        'shopify_fulfillment_id',
        'status',
        'shopify_created_at',
        'shopify_updated_at',
    ];

    protected $casts = [
        'shopify_created_at' => 'datetime',
        'shopify_updated_at' => 'datetime',
    ];

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class);
    }
}
