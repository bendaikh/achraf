<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRefund extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SHOPIFY = 'shopify';

    public const SOURCE_JUMIA = 'jumia';

    protected $fillable = [
        'refund_number',
        'client_id',
        'invoice_id',
        'credit_note_id',
        'pos_sale_id',
        'source',
        'refund_date',
        'amount',
        'payment_method',
        'payment_reference',
        'payment_file_path',
        'notes',
        'created_by',
        'external_id',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
