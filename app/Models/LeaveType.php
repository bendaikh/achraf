<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'paid',
        'requires_justification',
        'impacts_balance',
        'impacts_payroll',
    ];

    protected $casts = [
        'paid' => 'boolean',
        'requires_justification' => 'boolean',
        'impacts_balance' => 'boolean',
        'impacts_payroll' => 'boolean',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
