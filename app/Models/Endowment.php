<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Endowment extends Model
{
    protected $fillable = [
        'label',
        'type',
        'currency',
        'initial_amount',
        'consumed_amount',
        'starts_on',
        'ends_on',
        'bank_account',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'consumed_amount' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(BankCard::class, 'endowment_bank_card');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(EndowmentConsumption::class)->orderByDesc('consumed_on');
    }

    public function remaining(): float
    {
        return round(max(0, (float) $this->initial_amount - (float) $this->consumed_amount), 2);
    }

    public function usedPercent(): float
    {
        $initial = (float) $this->initial_amount;
        if ($initial <= 0) {
            return 0;
        }

        return round(min(100, ((float) $this->consumed_amount / $initial) * 100), 1);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
