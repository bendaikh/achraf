<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyIntegration extends Model
{
    protected $fillable = [
        'integration_name',
        'shop_name',
        'webhook_secret',
        'enabled',
        'last_sync_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_sync_at' => 'datetime',
        'webhook_secret' => 'encrypted',
    ];
}
