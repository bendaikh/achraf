<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_VARIANCE = 'variance';

    protected $fillable = [
        'bank_statement_import_id',
        'operation_date',
        'value_date',
        'label',
        'reference',
        'debit',
        'credit',
        'balance',
        'status',
        'financial_movement_id',
        'notes',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'value_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(FinancialMovement::class, 'financial_movement_id');
    }

    public function signedAmount(): float
    {
        return round((float) $this->credit - (float) $this->debit, 2);
    }
}
