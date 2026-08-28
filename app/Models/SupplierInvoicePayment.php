<?php

namespace App\Models;

use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoicePayment extends Model
{
    use HasManagedDocuments;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_BULK = 'bulk';

    public const SOURCE_IMPORT = 'import';

    public const CHEQUE_STATUSES = [
        'prepared' => 'Préparé',
        'handed' => 'Remis',
        'in_circulation' => 'En circulation',
        'cashed' => 'Encaissé',
        'rejected' => 'Rejeté',
        'cancelled' => 'Annulé',
    ];

    protected $fillable = [
        'supplier_id',
        'supplier_payment_id',
        'supplier_invoice_id',
        'payment_date',
        'amount',
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
        'allow_overpayment',
        'is_cash_movement',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'cheque_date' => 'date',
        'cheque_due_date' => 'date',
        'amount' => 'decimal:2',
        'allow_overpayment' => 'boolean',
        'is_cash_movement' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
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
