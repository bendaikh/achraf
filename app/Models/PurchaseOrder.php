<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'reference',
        'client_id',
        'order_date',
        'expiry_date',
        'currency',
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
        'order_date' => 'date',
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
