<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyIntegration extends Model
{
    protected $fillable = [
        'integration_name',
        'shop_name',
        'shop_domain',
        'webhook_secret',
        'api_access_token',
        'oauth_client_id',
        'oauth_client_secret',
        'oauth_access_token',
        'oauth_scope',
        'oauth_state',
        'api_version',
        'enabled',
        'last_sync_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_sync_at' => 'datetime',
        'webhook_secret' => 'encrypted',
        'api_access_token' => 'encrypted',
        'oauth_client_id' => 'encrypted',
        'oauth_client_secret' => 'encrypted',
        'oauth_access_token' => 'encrypted',
    ];
}
