<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    public const TYPES = [
        'percent_ca' => '% du CA',
        'fixed' => 'Montant fixe',
        'percent_margin' => '% de marge',
    ];

    public const BASES = [
        'ca_ht' => 'CA HT',
        'ca_ttc' => 'CA TTC',
        'collected' => 'Montant encaissé',
        'margin' => 'Marge',
        'fixed' => 'Fixe',
    ];

    public const TRIGGERS = [
        'invoice_validated' => 'Facture validée',
        'delivered' => 'Commande livrée',
        'paid' => 'Facture payée',
        'delivered_paid' => 'Livrée + payée',
    ];

    protected $fillable = [
        'name',
        'collaborator_id',
        'type',
        'base',
        'rate',
        'fixed_amount',
        'trigger',
        'is_active',
        'is_default',
        'filters',
        'notes',
        'archived_at',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'fixed_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'filters' => 'array',
        'archived_at' => 'datetime',
    ];

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function hasHistory(): bool
    {
        return $this->commissions()->exists();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function baseLabel(): string
    {
        return self::BASES[$this->base] ?? $this->base;
    }

    public function triggerLabel(): string
    {
        return self::TRIGGERS[$this->trigger] ?? $this->trigger;
    }

    public function typeBaseLabel(): string
    {
        return $this->typeLabel().' / '.$this->baseLabel();
    }

    public function commercialLabel(): string
    {
        if ($this->collaborator_id && $this->collaborator) {
            return $this->collaborator->fullName();
        }

        return 'Tous les commerciaux';
    }
}
