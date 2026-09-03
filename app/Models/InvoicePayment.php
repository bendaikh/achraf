<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_BULK = 'bulk';

    public const SOURCE_IMPORT = 'import';

    protected $fillable = [
        'invoice_id',
        'pos_sale_id',
        'order_tracking_id',
        'payment_date',
        'amount',
        'payment_method',
        'payment_reference',
        'payment_file_path',
        'notes',
        'source',
        'payment_batch_id',
        'tracking_number',
        'carrier',
        'created_by',
        'payment_import_id',
        'payment_import_row_id',
        'dedupe_key',
        'allow_overpayment',
        'gross_amount',
        'delivery_fees',
        'net_received',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'delivery_fees' => 'decimal:2',
        'net_received' => 'decimal:2',
        'allow_overpayment' => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentImport(): BelongsTo
    {
        return $this->belongsTo(PaymentImport::class);
    }

    public function paymentImportLine(): BelongsTo
    {
        return $this->belongsTo(PaymentImportLine::class, 'payment_import_row_id');
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class);
    }

    /**
     * Montant facture / CRBT used for invoice balance.
     */
    public function resolvedGrossAmount(): float
    {
        if ($this->gross_amount !== null) {
            return (float) $this->gross_amount;
        }

        $fromImport = $this->paymentImportLine?->file_amount;
        if ($fromImport !== null) {
            return (float) $fromImport;
        }

        return (float) $this->amount;
    }

    /**
     * Frais livraison / commission (from payment row or linked import line).
     */
    public function resolvedDeliveryFees(): ?float
    {
        if ($this->delivery_fees !== null) {
            return (float) $this->delivery_fees;
        }

        $fromImport = $this->paymentImportLine?->file_delivery_fees;
        if ($fromImport !== null) {
            return (float) $fromImport;
        }

        $net = $this->net_received ?? $this->paymentImportLine?->file_net_amount;
        if ($net !== null) {
            return round($this->resolvedGrossAmount() - (float) $net, 2);
        }

        return null;
    }

    /**
     * Net réellement encaissé (from payment row, import line, or gross − fees).
     */
    public function resolvedNetReceived(): ?float
    {
        if ($this->net_received !== null) {
            return (float) $this->net_received;
        }

        $fromImport = $this->paymentImportLine?->file_net_amount;
        if ($fromImport !== null) {
            return (float) $fromImport;
        }

        $fees = $this->resolvedDeliveryFees();
        if ($fees !== null) {
            return round($this->resolvedGrossAmount() - $fees, 2);
        }

        return null;
    }

    public function resolvedTrackingNumber(?Invoice $invoice = null): ?string
    {
        if ($this->tracking_number) {
            return $this->tracking_number;
        }

        $fromImport = $this->paymentImportLine?->resolved_tracking
            ?? $this->paymentImportLine?->file_tracking;
        if ($fromImport) {
            return $fromImport;
        }

        $invoice ??= $this->invoice;

        return $invoice?->posSale?->primaryTrackingNumber();
    }

    public function sourceLabel(): string
    {
        return match ($this->source ?? self::SOURCE_MANUAL) {
            self::SOURCE_IMPORT => 'Import',
            self::SOURCE_BULK => 'Groupé',
            default => 'Manuel',
        };
    }
}
