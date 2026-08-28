<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends Model
{
    public const METHODS = [
        'virement' => 'Virement',
        'especes' => 'Espèces',
        'cheque' => 'Chèque',
    ];

    protected $fillable = [
        'payroll_slip_id',
        'paid_at',
        'amount',
        'method',
        'account',
        'reference',
        'proof_path',
        'notes',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function slip(): BelongsTo
    {
        return $this->belongsTo(PayrollSlip::class, 'payroll_slip_id');
    }
}
