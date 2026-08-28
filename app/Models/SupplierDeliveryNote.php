<?php

namespace App\Models;

use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Model;

class SupplierDeliveryNote extends Model
{
    use HasManagedDocuments;

    protected $fillable = [
        'delivery_number', 'supplier_id', 'supplier_purchase_order_id', 'converted_supplier_invoice_id', 'converted_at',
        'delivery_date', 'expected_reception_date',
        'reference', 'currency', 'status', 'stock_location', 'warehouse_id', 'model',
        'remarks', 'subtotal', 'discount', 'adjustment', 'total',
        'document_file_path', 'stock_applied_at',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'expected_reception_date' => 'date',
        'converted_at' => 'datetime',
        'stock_applied_at' => 'datetime',
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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(SupplierPurchaseOrder::class, 'supplier_purchase_order_id');
    }

    public function stockAllocations()
    {
        return $this->morphMany(PurchaseStockAllocation::class, 'allocatable');
    }

    public function receptions()
    {
        return $this->hasMany(Reception::class, 'supplier_delivery_note_id');
    }
}
