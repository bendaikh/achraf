<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    protected $fillable = [
        'name',
        'type',
        'base',
        'rate',
        'fixed_amount',
        'trigger',
        'is_active',
        'filters',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'fixed_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'filters' => 'array',
    ];

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
