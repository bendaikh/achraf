<?php

namespace App\Models;

use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Model;

class Reception extends Model
{
    use HasManagedDocuments;

    protected $fillable = [
        'reception_number', 'supplier_id', 'converted_supplier_invoice_id', 'converted_at',
        'reference', 'reception_date', 'delivery_date',
        'currency', 'status', 'stock_location', 'model', 'remarks',
        'subtotal', 'discount', 'adjustment', 'total', 'document_file_path',
    ];

    protected $casts = [
        'reception_date' => 'date',
        'delivery_date' => 'date',
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
