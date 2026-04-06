<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_CARD = 'card';

    public const PAYMENT_CHEQUE = 'cheque';

    public const PAYMENT_TRANSFER = 'transfer';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'ticket_number',
        'client_id',
        'user_id',
        'sold_at',
        'currency',
        'subtotal',
        'discount',
        'tax_total',
        'total',
        'payment_method',
        'amount_received',
        'change_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public static function paymentLabels(): array
    {
        return [
            self::PAYMENT_CASH => 'Espèces',
            self::PAYMENT_CARD => 'Carte bancaire',
            self::PAYMENT_CHEQUE => 'Chèque',
            self::PAYMENT_TRANSFER => 'Virement',
        ];
    }

    public function paymentLabel(): string
    {
        return self::paymentLabels()[$this->payment_method] ?? $this->payment_method;
    }
}
