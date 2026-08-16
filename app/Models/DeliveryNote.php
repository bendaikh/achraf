<?php

namespace App\Models;

use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Model;

class DeliveryNote extends Model
{
    use HasManagedDocuments;

    protected $fillable = [
        'delivery_number', 'client_id', 'delivery_date', 'shipping_date',
        'reference', 'currency', 'status', 'stock_location', 'model', 'matricule',
        'remarks', 'conditions', 'subtotal', 'discount', 'adjustment', 'total',
        'document_file_path',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'shipping_date' => 'date',
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
}
