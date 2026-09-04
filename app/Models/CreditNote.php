<?php

namespace App\Models;

use App\Support\DocumentTaxBreakdown;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use Concerns\HasCommercialAttribution;

    protected $fillable = [
        'credit_note_number',
        'client_id',
        'collaborator_id',
        'created_by_user_id',
        'invoice_id',
        'pos_sale_id',
        'source',
        'external_id',
        'return_type',
        'physical_return',
        'restock',
        'product_condition',
        'return_location',
        'created_by',
        'credit_note_date',
        'currency',
        'stock_location',
        'remarks',
        'conditions',
        'subtotal',
        'discount',
        'adjustment',
        'total',
        'receipt_file_path',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'physical_return' => 'boolean',
        'restock' => 'boolean',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->morphMany(InvoiceItem::class, 'itemable');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(ClientRefund::class);
    }

    public function getComputedTotalAttribute(): float
    {
        $this->loadMissing('items');

        $items = $this->items;

        if ($items->isEmpty()) {
            return (float) $this->total;
        }

        return DocumentTaxBreakdown::fromDocument($this, $items)['total_ttc'];
    }
}
