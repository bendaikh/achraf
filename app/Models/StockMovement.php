<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SALE = 'sale';

    public const TYPE_CUSTOMER_RETURN = 'customer_return';

    public const TYPE_SUPPLIER_RETURN = 'supplier_return';

    public const TYPE_INVENTORY_ADJUSTMENT = 'inventory_adjustment';

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_MANUAL_IN = 'manual_in';

    public const TYPE_MANUAL_OUT = 'manual_out';

    public const TYPE_PHYSICAL_IN = 'physical_in';

    public const TYPE_STOCK_ADJUSTMENT = 'stock_adjustment';

    public const TYPE_ORDER_OUT = 'order_out';

    public const REASON_PURCHASE = 'purchase';

    public const REASON_SUPPLIER_RECEPTION = 'supplier_reception';

    public const REASON_INVENTORY = 'inventory';

    public const REASON_ADJUSTMENT = 'adjustment';

    public const REASON_OTHER = 'other';

    public const REASON_INVENTORY_CORRECTION = 'inventory_correction';

    public const REASON_SALE = 'sale';

    public const REASON_BREAKAGE = 'breakage';

    public const REASON_LOSS = 'loss';

    public const PHYSICAL_STOCK_REASONS = [
        self::REASON_PURCHASE => 'Achat',
        self::REASON_SUPPLIER_RECEPTION => 'Réception fournisseur',
        self::REASON_INVENTORY => 'Inventaire',
        self::REASON_ADJUSTMENT => 'Ajustement',
        self::REASON_OTHER => 'Autre',
    ];

    public const STOCK_ADJUSTMENT_REASONS = [
        self::REASON_INVENTORY_CORRECTION => 'Inventaire / Correction de stock',
        self::REASON_SALE => 'Vente (sortie)',
        self::REASON_BREAKAGE => 'Casse',
        self::REASON_LOSS => 'Perte',
        self::REASON_OTHER => 'Autre',
    ];

    public const TYPES = [
        self::TYPE_PURCHASE => 'Réception fournisseur',
        self::TYPE_SALE => 'Vente',
        self::TYPE_ORDER_OUT => 'Sortie commande',
        self::TYPE_CUSTOMER_RETURN => 'Retour client',
        self::TYPE_SUPPLIER_RETURN => 'Retour fournisseur',
        self::TYPE_INVENTORY_ADJUSTMENT => 'Ajustement inventaire',
        self::TYPE_TRANSFER_OUT => 'Transfert sortant',
        self::TYPE_TRANSFER_IN => 'Transfert entrant',
        self::TYPE_MANUAL_IN => 'Ajustement manuel (entrée)',
        self::TYPE_MANUAL_OUT => 'Ajustement manuel (sortie)',
        self::TYPE_PHYSICAL_IN => 'Entrée stock',
        self::TYPE_STOCK_ADJUSTMENT => 'Ajustement de stock',
    ];

    public static function physicalStockReasonLabel(?string $reason): string
    {
        if (! $reason) {
            return '—';
        }

        return self::PHYSICAL_STOCK_REASONS[$reason]
            ?? self::STOCK_ADJUSTMENT_REASONS[$reason]
            ?? $reason;
    }

    public static function stockAdjustmentReasonLabel(?string $reason): string
    {
        return self::physicalStockReasonLabel($reason);
    }

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'warehouse_location_id',
        'quantity',
        'type',
        'moved_at',
        'document_type',
        'document_id',
        'document_reference',
        'user_id',
        'transfer_group_id',
        'notes',
        'quantity_before',
        'quantity_after',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'moved_at' => 'datetime',
    ];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StockMovementDocument::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isEntry(): bool
    {
        return (int) $this->quantity > 0;
    }
}
