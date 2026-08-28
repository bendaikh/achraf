<?php

namespace App\Models;

use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    use HasManagedDocuments;

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'supplier_id',
        'payment_number',
        'payment_date',
        'amount',
        'unallocated_amount',
        'payment_method',
        'payment_reference',
        'cheque_number',
        'cheque_bank',
        'cheque_date',
        'cheque_due_date',
        'cheque_beneficiary',
        'cheque_status',
        'payment_file_path',
        'notes',
        'source',
        'tracking_number',
        'user_id',
        'payment_import_id',
        'payment_import_row_id',
        'dedupe_key',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'allocation_snapshot',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'cheque_date' => 'date',
        'cheque_due_date' => 'date',
        'amount' => 'decimal:2',
        'unallocated_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'allocation_snapshot' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(SupplierInvoicePayment::class);
    }

    public function creditNoteAllocations(): HasMany
    {
        return $this->hasMany(SupplierCreditNoteAllocation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(SupplierPaymentAudit::class)->orderByDesc('id');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isValidated(): bool
    {
        return ! $this->isCancelled();
    }

    public function statusLabel(): string
    {
        return $this->isCancelled() ? 'Annulé' : 'Validé';
    }

    public function chequeLabel(): ?string
    {
        if ($this->payment_method !== 'Chèque') {
            return $this->payment_reference;
        }

        return $this->cheque_number ? 'CHQ-'.$this->cheque_number : $this->payment_reference;
    }
}
