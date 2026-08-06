<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPiece extends Model
{
    protected $fillable = [
        'piece_date',
        'label',
        'category',
        'file_path',
        'user_id',
        'financial_declaration_id',
        'notes',
    ];

    protected $casts = [
        'piece_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(FinancialDeclaration::class, 'financial_declaration_id');
    }
}
