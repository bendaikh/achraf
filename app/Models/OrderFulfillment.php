<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFulfillment extends Model
{
    protected $fillable = [
        'pos_sale_id',
        'shopify_order_id',
        'shopify_fulfillment_id',
        'tracking_number',
        'tracking_company',
        'tracking_url',
        'status',
        'shopify_created_at',
        'shopify_updated_at',
        'raw_payload',
    ];

    protected $casts = [
        'shopify_created_at' => 'datetime',
        'shopify_updated_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class);
    }
}
