<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const KIND_PHYSICAL = 'physical';

    public const KIND_ONLINE = 'online';

    protected $fillable = [
        'name',
        'code',
        'kind',
        'address',
        'city',
        'status',
        'is_primary',
        'is_fulfillment_default',
        'comment',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_fulfillment_default' => 'boolean',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class)->orderBy('code');
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function displayLabel(): string
    {
        $parts = [$this->name];
        if ($this->city) {
            $parts[] = $this->city;
        }

        return implode(' – ', $parts);
    }

    public static function primary(): ?self
    {
        return static::query()->active()->where('is_primary', true)->first()
            ?? static::query()->active()->orderBy('id')->first();
    }

    public function isOnline(): bool
    {
        return ($this->kind ?? self::KIND_PHYSICAL) === self::KIND_ONLINE;
    }

    public function isPhysical(): bool
    {
        return ! $this->isOnline();
    }

    public function scopePhysical($query)
    {
        return $query->where(function ($q) {
            $q->where('kind', self::KIND_PHYSICAL)->orWhereNull('kind');
        });
    }

    public function scopeOnline($query)
    {
        return $query->where('kind', self::KIND_ONLINE);
    }

    public static function onlineWarehouse(): ?self
    {
        return static::query()->active()->online()->orderBy('id')->first()
            ?? static::query()->active()->where('code', 'SHOPIFY')->first();
    }

    public static function fulfillmentWarehouse(): ?self
    {
        return static::query()->active()->where('is_fulfillment_default', true)->first()
            ?? static::query()->active()->where('code', 'BELVEDERE')->first()
            ?? static::query()->active()->physical()->orderBy('id')->first();
    }

    public static function findByStockLocation(?string $stockLocation): ?self
    {
        $normalized = mb_strtolower(trim((string) $stockLocation));
        if ($normalized === '') {
            return null;
        }

        if (
            str_contains($normalized, 'shopify')
            || str_contains($normalized, 'en ligne')
            || str_contains($normalized, 'enligne')
        ) {
            return static::onlineWarehouse();
        }

        if (str_contains($normalized, 'belv')) {
            return static::fulfillmentWarehouse();
        }

        $byCode = static::query()->active()->whereRaw('LOWER(code) = ?', [$normalized])->first();
        if ($byCode) {
            return $byCode;
        }

        $byName = static::query()->active()->whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($byName) {
            return $byName;
        }

        $like = static::query()->active()->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])->first();
        if ($like) {
            return $like;
        }

        if (str_contains($normalized, 'magasin')) {
            return static::fulfillmentWarehouse();
        }

        if (str_contains($normalized, 'depot') || str_contains($normalized, 'dépôt') || str_contains($normalized, 'principal')) {
            return static::query()->active()->physical()->where('is_primary', true)->first()
                ?? static::query()->active()->where('code', 'PRINCIPAL')->first();
        }

        return null;
    }
}
