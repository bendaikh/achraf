<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoicePayment extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_BULK = 'bulk';

    public const SOURCE_IMPORT = 'import';

    protected $fillable = [
        'supplier_invoice_id',
        'payment_date',
        'amount',
        'payment_method',
        'payment_reference',
        'payment_file_path',
        'notes',
        'source',
        'tracking_number',
        'user_id',
        'payment_import_id',
        'payment_import_row_id',
        'dedupe_key',
        'allow_overpayment',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'allow_overpayment' => 'boolean',
    ];

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentImport(): BelongsTo
    {
        return $this->belongsTo(PaymentImport::class);
    }

    public function paymentImportLine(): BelongsTo
    {
        return $this->belongsTo(PaymentImportLine::class, 'payment_import_row_id');
    }
}
