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
        'primary_location_id',
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

    /**
     * Granted scope handles, expanded with implied scopes.
     * Shopify omits implied read scopes from the OAuth "scope" string / access_scopes:
     * write_X always implies read_X (e.g. write_fulfillments => read_fulfillments).
     *
     * @return list<string>
     */
    public function grantedScopes(): array
    {
        return static::expandScopes((string) $this->oauth_scope);
    }

    public function hasScope(string $scope): bool
    {
        return in_array(strtolower(trim($scope)), $this->grantedScopes(), true);
    }

    /**
     * @return list<string>
     */
    public static function expandScopes(string $scopes): array
    {
        $handles = collect(preg_split('/[\s,]+/', strtolower($scopes)) ?: [])
            ->map(fn ($h) => trim($h))
            ->filter();

        $implied = $handles
            ->filter(fn ($h) => str_starts_with($h, 'write_'))
            ->map(fn ($h) => 'read_'.substr($h, strlen('write_')));

        return $handles->merge($implied)->unique()->sort()->values()->all();
    }
}
