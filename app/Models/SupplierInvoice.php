<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierInvoice extends Model
{
    protected $fillable = [
        'invoice_number', 'supplier_id', 'invoice_date', 'due_date', 'reference_invoice',
        'currency', 'stock_location', 'commercial_contact', 'model', 'matricule', 'remarks', 'conditions',
        'subtotal', 'discount', 'adjustment', 'total',
    ];

    protected $casts = [
        'invoice_date' => 'date',
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

    public function creditNotes()
    {
        return $this->hasMany(SupplierCreditNote::class);
    }
}
