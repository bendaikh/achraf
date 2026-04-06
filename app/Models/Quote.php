<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = [
        'quote_number',
        'client_id',
        'quote_date',
        'expiry_date',
        'currency',
        'stock_location',
        'status',
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
        'quote_date' => 'date',
        'expiry_date' => 'date',
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
}
