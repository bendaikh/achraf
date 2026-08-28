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
}
