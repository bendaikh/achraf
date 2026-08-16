<?php

namespace App\Models;

use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Model;

class SupplierDeliveryNote extends Model
{
    use HasManagedDocuments;

    protected $fillable = [
        'delivery_number', 'supplier_id', 'converted_supplier_invoice_id', 'converted_at',
        'delivery_date', 'expected_reception_date',
        'reference', 'currency', 'status', 'stock_location', 'model',
        'remarks', 'subtotal', 'discount', 'adjustment', 'total',
        'document_file_path',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'expected_reception_date' => 'date',
        'converted_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function convertedSupplierInvoice()
    {
        return $this->belongsTo(SupplierInvoice::class, 'converted_supplier_invoice_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_supplier_invoice_id !== null;
    }

    public function items()
    {
        return $this->morphMany(PurchaseItem::class, 'purchaseable');
    }
}
