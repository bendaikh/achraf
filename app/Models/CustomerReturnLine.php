<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReturnLine extends Model
{
    public const CONDITION_VENDABLE = 'vendable';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_TO_CHECK = 'to_check';

    public const CONDITION_MISSING = 'missing';

    public const CONDITIONS = [
        self::CONDITION_VENDABLE => 'Vendable',
        self::CONDITION_DAMAGED => 'Endommagé',
        self::CONDITION_TO_CHECK => 'À contrôler',
        self::CONDITION_MISSING => 'Manquant',
    ];

    protected $fillable = [
        'customer_return_id',
        'pos_sale_item_id',
        'product_id',
        'product_variant_id',
        'sku',
        'designation',
        'quantity_ordered',
        'quantity_expected',
        'quantity_received',
        'condition',
        'warehouse_id',
        'warehouse_location_id',
        'stock_applied',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_expected' => 'integer',
        'quantity_received' => 'integer',
        'stock_applied' => 'boolean',
    ];

    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class, 'pos_sale_item_id');
    }

    public function isSellableCondition(): bool
    {
        return $this->condition === self::CONDITION_VENDABLE;
    }

    public function shouldRestock(): bool
    {
        return $this->quantity_received > 0
            && in_array($this->condition, [
                self::CONDITION_VENDABLE,
                self::CONDITION_DAMAGED,
                self::CONDITION_TO_CHECK,
            ], true);
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }
}
