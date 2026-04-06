<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reception extends Model
{
    protected $fillable = [
        'reception_number', 'supplier_id', 'reference', 'reception_date', 'delivery_date',
        'currency', 'status', 'stock_location', 'model', 'remarks',
        'subtotal', 'discount', 'adjustment', 'total',
    ];

    protected $casts = [
        'reception_date' => 'date',
        'delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->morphMany(PurchaseItem::class, 'purchaseable');
    }
}
