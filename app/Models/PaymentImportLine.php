<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentImportLine extends Model
{
    protected $table = 'payment_import_rows';

    public const MATCH_MATCHED = 'matched';

    public const MATCH_AMBIGUOUS = 'ambiguous';

    public const MATCH_UNMATCHED = 'unmatched';

    public const MATCH_DUPLICATE = 'duplicate';

    public const MATCH_SKIPPED = 'skipped';

    public const AMOUNT_OK = 'ok';

    public const AMOUNT_DISCREPANCY = 'discrepancy';

    public const AMOUNT_OVERPAYMENT = 'overpayment';

    protected $fillable = [
        'payment_import_id',
        'row_number',
        'file_reference',
        'file_tracking',
        'file_order_ref',
        'file_amount',
        'file_delivery_fees',
        'file_net_amount',
        'file_raw',
        'normalized_lookup',
        'match_status',
        'match_confidence',
        'match_criteria',
        'match_score',
        'amount_status',
        'expected_amount',
        'amount_variance',
        'override_amount',
        'pos_sale_id',
        'invoice_id',
        'supplier_invoice_id',
        'order_tracking_id',
        'resolved_tracking',
        'candidate_matches',
        'allow_overpayment',
        'include_in_validation',
        'exclude',
        'duplicate_payment_id',
        'invoice_payment_id',
        'supplier_invoice_payment_id',
        'notes',
    ];

    protected $casts = [
        'file_amount' => 'decimal:2',
        'file_delivery_fees' => 'decimal:2',
        'file_net_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'amount_variance' => 'decimal:2',
        'override_amount' => 'decimal:2',
        'file_raw' => 'array',
        'candidate_matches' => 'array',
        'match_criteria' => 'array',
        'allow_overpayment' => 'boolean',
        'include_in_validation' => 'boolean',
        'exclude' => 'boolean',
    ];

    protected $appends = [
        'line_number',
        'amount_difference',
        'candidate_invoice_ids',
        'match_notes',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(PaymentImport::class, 'payment_import_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class);
    }

    public function invoicePayment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class);
    }

    public function supplierInvoicePayment(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoicePayment::class);
    }

    public function isReadyToValidate(): bool
    {
        if ($this->exclude || $this->include_in_validation === false) {
            return false;
        }

        if ($this->match_status !== self::MATCH_MATCHED) {
            return false;
        }

        if ($this->amount_status === self::AMOUNT_OVERPAYMENT && ! $this->allow_overpayment) {
            return false;
        }

        return true;
    }

    public function getLineNumberAttribute(): int
    {
        return (int) ($this->attributes['row_number'] ?? 0);
    }

    public function setLineNumberAttribute($value): void
    {
        $this->attributes['row_number'] = (int) $value;
    }

    public function getAmountDifferenceAttribute(): ?float
    {
        return isset($this->attributes['amount_variance'])
            ? (float) $this->attributes['amount_variance']
            : null;
    }

    public function setAmountDifferenceAttribute($value): void
    {
        $this->attributes['amount_variance'] = $value;
    }

    public function getCandidateInvoiceIdsAttribute(): ?array
    {
        $raw = $this->attributes['candidate_matches'] ?? null;
        if ($raw === null) {
            return null;
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : null;
    }

    public function setCandidateInvoiceIdsAttribute($value): void
    {
        $this->attributes['candidate_matches'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getMatchNotesAttribute(): ?string
    {
        return $this->attributes['notes'] ?? null;
    }

    public function setMatchNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = $value;
    }
}
