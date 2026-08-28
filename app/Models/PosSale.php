<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PosSale extends Model
{
    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_CARD = 'card';

    public const PAYMENT_CHEQUE = 'cheque';

    public const PAYMENT_TRANSFER = 'transfer';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PENDING = 'pending';

    public const SYNC_NOT_SYNCED = 'not_synced';

    public const SYNC_IN_PROGRESS = 'in_progress';

    public const SYNC_SYNCED = 'synced';

    public const SYNC_ERROR = 'error';

    protected $fillable = [
        'ticket_number',
        'creation_token',
        'client_id',
        'user_id',
        'created_by_user_id',
        'assigned_user_id',
        'assigned_employee_id',
        'sold_at',
        'currency',
        'subtotal',
        'discount',
        'discount_type',
        'discount_value',
        'discount_reason',
        'tax_total',
        'shipping_amount',
        'total',
        'payment_method',
        'amount_received',
        'change_amount',
        'status',
        'payment_status',
        'fulfillment_status',
        'physical_stock_processed_at',
        'shopify_synced_at',
        'jumia_synced_at',
        'notes',
        'delivery_note',
        'internal_note',
        'tags',
        'source',
        'sales_channel',
        'external_id',
        'shopify_order_id',
        'shopify_order_number',
        'sync_status',
        'sync_error',
        'sync_attempted_at',
        'shipping_address',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'shipping_method',
        'external_metadata',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'shopify_synced_at' => 'datetime',
        'jumia_synced_at' => 'datetime',
        'sync_attempted_at' => 'datetime',
        'physical_stock_processed_at' => 'datetime',
        'tags' => 'array',
        'external_metadata' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(OrderFulfillment::class);
    }

    public function trackings(): HasMany
    {
        return $this->hasMany(OrderTracking::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function recordActivity(string $event, string $description, ?int $actorUserId = null, array $metadata = []): OrderActivity
    {
        return $this->activities()->create([
            'actor_user_id' => $actorUserId,
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }

    public function primaryTrackingNumber(): ?string
    {
        $fromFulfillments = $this->relationLoaded('fulfillments')
            ? $this->fulfillments
            : $this->fulfillments()->get();

        $withTracking = $fromFulfillments
            ->filter(fn (OrderFulfillment $f) => filled($f->tracking_number))
            ->sortByDesc('shopify_updated_at');

        if ($withTracking->isNotEmpty()) {
            return $withTracking->first()?->tracking_number;
        }

        $fromTrackings = $this->relationLoaded('trackings')
            ? $this->trackings
            : $this->trackings()->get();

        return $fromTrackings
            ->filter(fn (OrderTracking $t) => filled($t->tracking_number))
            ->sortByDesc('shopify_updated_at')
            ->first()
            ?->tracking_number;
    }

    public function trackingNumbers(): array
    {
        $fulfillments = $this->relationLoaded('fulfillments')
            ? $this->fulfillments
            : $this->fulfillments()->get();

        $trackings = $this->relationLoaded('trackings')
            ? $this->trackings
            : $this->trackings()->get();

        return collect()
            ->merge($fulfillments->pluck('tracking_number'))
            ->merge($trackings->pluck('tracking_number'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isPaidAndFulfilled(): bool
    {
        return $this->payment_status === 'paid' && $this->fulfillment_status === 'fulfilled';
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
