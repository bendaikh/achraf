<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentAdjustments;
use App\Support\DocumentTaxBreakdown;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasDocumentAdjustments;

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'invoice_number',
        'client_id',
        'pos_sale_id',
        'is_auto_generated',
        'invoice_date',
        'due_date',
        'currency',
        'stock_location',
        'commercial_contact',
        'model',
        'matricule',
        'remarks',
        'conditions',
        'subtotal',
        'discount',
        'adjustment',
        'total',
        'document_file_path',
        'payment_status',
        'commercial_status',
        'source',
    ];

    protected $casts = [
        'is_auto_generated' => 'boolean',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function posSale()
    {
        return $this->belongsTo(PosSale::class);
    }

    public function items()
    {
        return $this->morphMany(InvoiceItem::class, 'itemable');
    }

    public function sourceQuotes()
    {
        return $this->hasMany(Quote::class, 'converted_invoice_id');
    }

    public function sourcePurchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'converted_invoice_id');
    }

    public function sourceDeliveryNotes()
    {
        return $this->hasMany(DeliveryNote::class, 'converted_invoice_id');
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function activities()
    {
        return $this->hasMany(InvoiceActivity::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function recordActivity(string $event, string $description, ?int $actorUserId = null, array $metadata = []): InvoiceActivity
    {
        return $this->activities()->create([
            'actor_user_id' => $actorUserId,
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getComputedTotalAttribute(): float
    {
        $this->loadMissing('items', 'adjustments');

        $items = $this->items;

        if ($items->isEmpty()) {
            return (float) $this->total;
        }

        return DocumentTaxBreakdown::fromDocument($this, $items)['total_ttc'];
    }

    public function refunds()
    {
        return $this->hasMany(ClientRefund::class);
    }

    public function getTotalCreditsAttribute(): float
    {
        return (float) $this->creditNotes()->get()->sum(fn (CreditNote $cn) => (float) $cn->computed_total);
    }

    public function getNetSaleAttribute(): float
    {
        return max(0, round($this->computed_total - $this->total_credits, 2));
    }

    public function getTotalRefundedAttribute(): float
    {
        return (float) $this->refunds()->sum('amount');
    }

    public function getRemainingToRefundAttribute(): float
    {
        return max(0, round($this->total_credits - $this->total_refunded, 2));
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, $this->computed_total - $this->total_paid);
    }

    public function getComputedPaymentStatusAttribute(): string
    {
        if ($this->total_paid <= 0) {
            return 'unpaid';
        }

        if ($this->total_paid >= $this->computed_total) {
            return 'paid';
        }

        return 'partial';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function syncPaymentStatus(): void
    {
        $this->update([
            'payment_status' => $this->computed_payment_status,
        ]);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', self::PAYMENT_PAID);
    }
}
