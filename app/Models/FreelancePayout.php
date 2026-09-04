<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FreelancePayout extends Model
{
    protected $fillable = [
        'collaborator_id',
        'amount_due',
        'amount_validated',
        'amount_paid',
        'paid_at',
        'payment_method',
        'payment_reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_validated' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function commissions(): BelongsToMany
    {
        return $this->belongsToMany(Commission::class, 'freelance_payout_commission');
    }

    public function remaining(): float
    {
        return round((float) $this->amount_due - (float) $this->amount_paid, 2);
    }
}
