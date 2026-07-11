<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierDeliveryNote extends Model
{
    protected $fillable = [
        'delivery_number', 'supplier_id', 'delivery_date', 'expected_reception_date',
        'reference', 'currency', 'status', 'stock_location', 'model',
        'remarks', 'subtotal', 'discount', 'adjustment', 'total',
        'document_file_path',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'expected_reception_date' => 'date',
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
