<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMatchMemory extends Model
{
    public const TYPE_TRACKING = 'tracking';

    public const TYPE_ORDER = 'order';

    public const TYPE_PHONE = 'phone';

    public const TYPE_PHONE_AMOUNT = 'phone_amount';

    public const TYPE_CLIENT_AMOUNT = 'client_amount';

    public const TYPE_EXTERNAL = 'external';

    protected $fillable = [
        'lookup_type',
        'lookup_value',
        'invoice_id',
        'created_by',
        'hit_count',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
