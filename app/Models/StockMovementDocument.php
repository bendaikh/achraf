<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

class StockMovementDocument extends Model
{
    protected $fillable = [
        'stock_movement_id',
        'document_type',
        'document_id',
        'document_reference',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function url(): ?string
    {
        $routes = [
            'reception' => 'receptions.show',
            'supplier_delivery_note' => 'supplier-delivery-notes.show',
            'supplier_invoice' => 'supplier-invoices.show',
            'supplier_purchase_order' => 'supplier-purchase-orders.show',
        ];

        $name = $routes[$this->document_type] ?? null;
        if (! $name || ! Route::has($name) || ! $this->document_id) {
            return null;
        }

        return route($name, $this->document_id);
    }

    public function label(): string
    {
        if ($this->document_reference) {
            return $this->document_reference;
        }

        return match ($this->document_type) {
            'reception' => 'BR #'.$this->document_id,
            'supplier_delivery_note' => 'BL #'.$this->document_id,
            'supplier_invoice' => 'Facture #'.$this->document_id,
            'supplier_purchase_order' => 'BC #'.$this->document_id,
            default => ($this->document_type ?: 'Document').' #'.$this->document_id,
        };
    }
}
