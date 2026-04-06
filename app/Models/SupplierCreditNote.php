<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCreditNote extends Model
{
    protected $fillable = [
        'credit_note_number', 'supplier_id', 'supplier_invoice_id', 'credit_note_date',
        'invoice', 'currency', 'stock_location', 'model', 'remarks',
        'subtotal', 'discount', 'adjustment', 'total',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierInvoice()
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function items()
    {
        return $this->morphMany(PurchaseItem::class, 'purchaseable');
    }
}
