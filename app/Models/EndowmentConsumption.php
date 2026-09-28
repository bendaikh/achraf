<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EndowmentConsumption extends Model
{
    protected $fillable = [
        'endowment_id',
        'bank_card_id',
        'consumed_on',
        'source_type',
        'source_id',
        'reference',
        'supplier_name',
        'currency',
        'amount_currency',
        'exchange_rate',
        'amount_mad',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'consumed_on' => 'date',
        'amount_currency' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'amount_mad' => 'decimal:2',
    ];

    public function endowment(): BelongsTo
    {
        return $this->belongsTo(Endowment::class);
    }

    public function bankCard(): BelongsTo
    {
        return $this->belongsTo(BankCard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
