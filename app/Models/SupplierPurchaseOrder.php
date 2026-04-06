<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPurchaseOrder extends Model
{
    protected $fillable = [
        'order_number', 'supplier_id', 'order_date', 'due_date', 'reference_invoice',
        'currency', 'stock_location', 'model', 'remarks', 'conditions',
        'subtotal', 'discount', 'adjustment', 'total',
    ];

    protected $casts = [
        'order_date' => 'date',
        'due_date' => 'date',
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
