<?php

namespace App\Models;

use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Model;

class DeliveryNote extends Model
{
    use HasManagedDocuments;

    protected $fillable = [
        'delivery_number', 'client_id', 'delivery_date', 'shipping_date',
        'reference', 'currency', 'status',
        'converted_invoice_id', 'converted_to_invoice_at',
        'stock_location', 'model', 'matricule',
        'remarks', 'conditions', 'subtotal', 'discount', 'adjustment', 'total',
        'document_file_path',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'shipping_date' => 'date',
        'converted_to_invoice_at' => 'datetime',
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

    public function convertedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function sourceQuotes()
    {
        return $this->hasMany(Quote::class, 'converted_delivery_note_id');
    }

    public function sourcePurchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'converted_delivery_note_id');
    }

    public function isConvertedToInvoice(): bool
    {
        return $this->converted_invoice_id !== null;
    }
}
