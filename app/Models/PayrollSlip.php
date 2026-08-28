<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollSlip extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'base_salary',
        'gross',
        'net',
        'primes',
        'indemnites',
        'overtime_amount',
        'absence_deduction',
        'retenues',
        'avances',
        'employee_contributions',
        'income_tax',
        'employer_contributions',
        'employer_cost',
        'breakdown',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'gross' => 'decimal:2',
        'net' => 'decimal:2',
        'primes' => 'decimal:2',
        'indemnites' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'absence_deduction' => 'decimal:2',
        'retenues' => 'decimal:2',
        'avances' => 'decimal:2',
        'employee_contributions' => 'decimal:2',
        'income_tax' => 'decimal:2',
        'employer_contributions' => 'decimal:2',
        'employer_cost' => 'decimal:2',
        'breakdown' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }
}
