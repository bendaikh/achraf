<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankCard extends Model
{
    protected $fillable = [
        'label',
        'account',
        'last_four',
        'currency',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function endowments(): BelongsToMany
    {
        return $this->belongsToMany(Endowment::class, 'endowment_bank_card');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(EndowmentConsumption::class);
    }

    public function displayLabel(): string
    {
        $four = $this->last_four ? ' •••• '.$this->last_four : '';

        return $this->label.$four;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
