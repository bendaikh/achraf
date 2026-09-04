<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use Concerns\HasCommercialAttribution;

    protected $fillable = [
        'quote_number',
        'client_id',
        'collaborator_id',
        'created_by_user_id',
        'quote_date',
        'expiry_date',
        'currency',
        'stock_location',
        'status',
        'converted_purchase_order_id', 'converted_to_purchase_order_at',
        'converted_delivery_note_id', 'converted_to_delivery_note_at',
        'converted_invoice_id', 'converted_to_invoice_at',
        'model',
        'matricule',
        'remarks',
        'conditions',
        'subtotal',
        'discount',
        'adjustment',
        'total',
        'document_file_path',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'expiry_date' => 'date',
        'converted_to_purchase_order_at' => 'datetime',
        'converted_to_delivery_note_at' => 'datetime',
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

    public function convertedPurchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'converted_purchase_order_id');
    }

    public function convertedDeliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class, 'converted_delivery_note_id');
    }

    public function convertedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function isConvertedToPurchaseOrder(): bool
    {
        return $this->converted_purchase_order_id !== null;
    }

    public function isConvertedToDeliveryNote(): bool
    {
        return $this->converted_delivery_note_id !== null;
    }

    public function isConvertedToInvoice(): bool
    {
        return $this->converted_invoice_id !== null;
    }
}
