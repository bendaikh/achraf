<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'client_id',
        'invoice_date',
        'due_date',
        'currency',
        'stock_location',
        'commercial_contact',
        'model',
        'matricule',
        'remarks',
        'conditions',
        'subtotal',
        'discount',
        'adjustment',
        'total',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->morphMany(InvoiceItem::class, 'itemable');
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }
}
