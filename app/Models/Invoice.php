<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public const PAYMENT_UNPAID = 'unpaid';

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

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, (float) $this->total - $this->total_paid);
    }

    public function getComputedPaymentStatusAttribute(): string
    {
        if ($this->total_paid <= 0) {
            return 'unpaid';
        }

        if ($this->total_paid >= (float) $this->total) {
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
            'payment_status' => $this->total_paid >= (float) $this->total
                ? self::PAYMENT_PAID
                : self::PAYMENT_UNPAID,
        ]);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', self::PAYMENT_PAID);
    }
}
