<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'status',
        'is_primary',
        'comment',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
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
}
