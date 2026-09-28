<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceOpeningBalance extends Model
{
    protected $fillable = [
        'account',
        'as_of_date',
        'amount',
        'currency',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'as_of_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
