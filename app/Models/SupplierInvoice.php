<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentAdjustments;
use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Model;

class SupplierInvoice extends Model
{
    use HasDocumentAdjustments, HasManagedDocuments;

    protected $fillable = [
        'invoice_number', 'supplier_id', 'supplier_purchase_order_id', 'invoice_date', 'due_date', 'reference_invoice',
        'currency', 'stock_location', 'warehouse_id', 'commercial_contact', 'model', 'matricule', 'remarks', 'conditions',
        'subtotal', 'discount', 'adjustment', 'total', 'invoice_file_path', 'stock_applied_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'total' => 'decimal:2',
        'stock_applied_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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

    public function creditNotes()
    {
        return $this->hasMany(SupplierCreditNote::class);
    }

    public function creditNoteAllocations()
    {
        return $this->hasMany(SupplierCreditNoteAllocation::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierInvoicePayment::class);
    }

    public function getTotalPaidAttribute()
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function getCreditsAppliedAttribute()
    {
        return round((float) $this->creditNoteAllocations()->sum('amount'), 2);
    }

    public function getRemainingBalanceAttribute()
    {
        return max(0, round((float) $this->total - (float) $this->total_paid - (float) $this->credits_applied, 2));
    }

    public function getComputedPaymentStatusAttribute(): string
    {
        $settled = round((float) $this->total_paid + (float) $this->credits_applied, 2);

        if ($settled <= 0) {
            return 'unpaid';
        }

        if ($settled >= (float) $this->total) {
            return 'paid';
        }

        return 'partial';
    }
}
