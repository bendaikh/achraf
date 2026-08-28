<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReplenishmentNeed extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'pos_sale_id',
        'quantity_needed',
        'quantity_ordered',
        'suggested_supplier_id',
        'supplier_id',
        'supplier_purchase_order_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity_needed' => 'integer',
        'quantity_ordered' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class);
    }

    public function suggestedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'suggested_supplier_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseOrder::class, 'supplier_purchase_order_id');
    }

    public function remaining(): int
    {
        return max(0, (int) $this->quantity_needed - (int) $this->quantity_ordered);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
