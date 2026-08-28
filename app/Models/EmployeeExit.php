<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExit extends Model
{
    protected $fillable = [
        'employee_id',
        'exit_date',
        'last_work_date',
        'reason',
        'leave_balance_settlement',
        'notes',
    ];

    protected $casts = [
        'exit_date' => 'date',
        'last_work_date' => 'date',
        'leave_balance_settlement' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
