<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryRecord extends Model
{
    public const AS_BRUT = 'brut';

    public const AS_NET = 'net';

    public const NEGOTIATED_AS = [
        self::AS_BRUT => 'Salaire brut',
        self::AS_NET => 'Net à payer',
    ];

    protected $fillable = [
        'employee_id',
        'effective_date',
        'base_salary',
        'negotiated_as',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'base_salary' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
