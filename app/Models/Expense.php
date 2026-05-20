<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'designation', 'expense_type', 'expense_category', 'expense_date', 'amount', 'currency',
        'reference', 'client_id', 'payment_method', 'account', 'tax_type', 'invoice_file_path',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
