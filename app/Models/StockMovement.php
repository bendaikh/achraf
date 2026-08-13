<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public const TYPES = [
        self::TYPE_PURCHASE => 'Achat fournisseur',
        self::TYPE_SALE => 'Vente',
        self::TYPE_CUSTOMER_RETURN => 'Retour client',
        self::TYPE_SUPPLIER_RETURN => 'Retour fournisseur',
        self::TYPE_INVENTORY_ADJUSTMENT => 'Ajustement inventaire',
        self::TYPE_TRANSFER_OUT => 'Transfert (sortie)',
        self::TYPE_TRANSFER_IN => 'Transfert (entrée)',
        self::TYPE_MANUAL_IN => 'Entrée manuelle',
        self::TYPE_MANUAL_OUT => 'Sortie manuelle',
    ];

    protected $fillable = [
        'product_id',
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
    ];

    protected $casts = [
        'quantity' => 'integer',
        'moved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isEntry(): bool
    {
        return (int) $this->quantity > 0;
    }
}
