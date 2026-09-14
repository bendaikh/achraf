<?php

namespace App\Models;

use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCreditNote extends Model
{
    use HasManagedDocuments;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'credit_note_number', 'supplier_id', 'supplier_invoice_id', 'credit_note_date',
        'invoice', 'currency', 'stock_location', 'model', 'remarks',
        'subtotal', 'discount', 'adjustment', 'total', 'receipt_file_path',
        'manually_consumed_at', 'manually_consumed_date', 'manually_consumed_note', 'manually_consumed_by',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'manually_consumed_at' => 'datetime',
        'manually_consumed_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierInvoice()
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function items()
    {
        return $this->morphMany(PurchaseItem::class, 'purchaseable');
    }

    public function allocations()
    {
        return $this->hasMany(SupplierCreditNoteAllocation::class);
    }

    public function manuallyConsumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manually_consumed_by');
    }

    public function isManuallyConsumed(): bool
    {
        return $this->manually_consumed_at !== null;
    }

    public function getAmountAppliedAttribute(): float
    {
        if (array_key_exists('allocations_sum', $this->attributes)) {
            return round((float) $this->attributes['allocations_sum'], 2);
        }

        return round((float) $this->allocations()->sum('amount'), 2);
    }

    public function getAmountAvailableAttribute(): float
    {
        if ($this->isManuallyConsumed()) {
            return 0.0;
        }

        return max(0, round((float) $this->total - $this->amount_applied, 2));
    }

    /**
     * @return self::STATUS_*
     */
    public function consumptionStatus(): string
    {
        if ($this->isManuallyConsumed() || $this->amount_available <= 0.009) {
            return self::STATUS_CONSUMED;
        }

        if ($this->amount_applied > 0.009) {
            return self::STATUS_PARTIAL;
        }

        return self::STATUS_AVAILABLE;
    }

    public function consumptionStatusLabel(): string
    {
        return match ($this->consumptionStatus()) {
            self::STATUS_PARTIAL => 'Partiellement consommé',
            self::STATUS_CONSUMED => 'Déjà consommé',
            default => 'Disponible',
        };
    }

    public function consumptionStatusBadgeClass(): string
    {
        return match ($this->consumptionStatus()) {
            self::STATUS_PARTIAL => 'bg-amber-100 text-amber-800',
            self::STATUS_CONSUMED => 'bg-red-100 text-red-800',
            default => 'bg-emerald-100 text-emerald-800',
        };
    }
}
