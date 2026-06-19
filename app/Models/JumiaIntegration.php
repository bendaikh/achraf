<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JumiaIntegration extends Model
{
    public const DEFAULT_API_BASE_URL = 'https://vendor-api.jumia.com';

    protected $fillable = [
        'integration_name',
        'client_id',
        'refresh_token',
        'access_token',
        'access_token_expires_at',
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
        'access_token_expires_at' => 'datetime',
        'api_key' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token' => 'encrypted',
    ];

    public function usesVendorCenter(): bool
    {
        return filled($this->client_id) && filled($this->refresh_token);
    }

    public function usesLegacyApi(): bool
    {
        return ! $this->usesVendorCenter()
            && filled($this->api_base_url)
            && filled($this->user_id)
            && filled($this->api_key);
    }

    public function isConfigured(): bool
    {
        return $this->usesVendorCenter() || $this->usesLegacyApi();
    }
}
