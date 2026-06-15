<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JumiaIntegration extends Model
{
    protected $fillable = [
        'integration_name',
        'api_base_url',
        'user_id',
        'api_key',
        'api_version',
        'enabled',
        'last_sync_at',
        'last_error',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_sync_at' => 'datetime',
        'api_key' => 'encrypted',
    ];

    public function isConfigured(): bool
    {
        return $this->api_base_url && $this->user_id && $this->api_key;
    }
}
