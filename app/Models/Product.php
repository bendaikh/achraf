<?php

namespace App\Models;

use App\Support\StockSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const KIND_STOCKED = 'stocked';

    public const KIND_NON_STOCKED = 'non_stocked';

    public const KIND_SERVICE = 'service';

    public const ITEM_KINDS = [
        self::KIND_STOCKED => 'Produit stocké',
        self::KIND_NON_STOCKED => 'Produit non stocké',
        self::KIND_SERVICE => 'Service',
    ];

    public const BILLING_UNITS = [
        'heure' => 'Heure',
        'prestation' => 'Prestation',
        'forfait' => 'Forfait',
        'unite' => 'Unité',
        'jour' => 'Jour',
    ];

    public const STOCK_STATUS_IN_STOCK = 'in_stock';

    public const STOCK_STATUS_LOW = 'low_stock';

    public const STOCK_STATUS_OUT = 'out_of_stock';

    public const STOCK_STATUS_NO_TRACKING = 'no_tracking';

    public const STOCK_STATUSES = [
        self::STOCK_STATUS_IN_STOCK => 'En stock',
        self::STOCK_STATUS_LOW => 'Stock faible',
        self::STOCK_STATUS_OUT => 'Rupture de stock',
        self::STOCK_STATUS_NO_TRACKING => 'Sans gestion de stock',
    ];

    protected $fillable = [
        'name',
        'ref',
        'image',
        'cost_price_ht',
        'last_purchase_price',
        'sale_price',
        'sale_price_ht',
        'product_margin',
        'minimum_safety_stock',
        'minimum_alert_stock',
        'maximum_stock',
        'stock_quantity',
        'stock_reserved',
        'stock_magasin',
        'stock_enligne',
        'location',
        'depot',
        'warehouse_id',
        'warehouse_location_id',
        'primary_supplier_id',
        'barcode',
        'vat_category',
        'product_type_category',
        'element_type',
        'item_kind',
        'tag',
        'status',
        'product_category',
        'service_category',
        'estimated_duration',
        'billing_unit',
        'technician_required',
        'description',
        'source',
        'external_id',
        'shopify_status',
        'shopify_synced_at',
        'shopify_image_url',
        'jumia_product_sid',
        'jumia_stock_synced_at',
    ];

    protected $casts = [
        'cost_price_ht' => 'decimal:2',
        'last_purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'sale_price_ht' => 'decimal:2',
        'product_margin' => 'decimal:2',
        'minimum_safety_stock' => 'integer',
        'minimum_alert_stock' => 'integer',
        'maximum_stock' => 'integer',
        'stock_quantity' => 'integer',
        'stock_reserved' => 'integer',
        'stock_magasin' => 'integer',
        'stock_enligne' => 'integer',
        'technician_required' => 'boolean',
        'shopify_synced_at' => 'datetime',
        'jumia_stock_synced_at' => 'datetime',
    ];

    public function primarySupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'primary_supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('moved_at');
    }

    public function isStocked(): bool
    {
        return ($this->item_kind ?? self::KIND_STOCKED) === self::KIND_STOCKED;
    }

    public function isNonStocked(): bool
    {
        return ($this->item_kind ?? '') === self::KIND_NON_STOCKED;
    }

    public function isService(): bool
    {
        return ($this->item_kind ?? '') === self::KIND_SERVICE
            || strcasecmp((string) $this->element_type, 'Service') === 0;
    }

    /**
     * Whether this article participates in inventory (entries, exits, alerts, blocking).
     */
    public function tracksStock(): bool
    {
        return $this->isStocked();
    }

    public function getItemKindLabelAttribute(): string
    {
        return self::ITEM_KINDS[$this->item_kind ?? self::KIND_STOCKED] ?? 'Produit stocké';
    }

    /**
     * Keep legacy element_type aligned with item_kind for older screens/filters.
     */
    public static function elementTypeForKind(string $kind): string
    {
        return $kind === self::KIND_SERVICE ? 'Service' : 'Produit';
    }

    /**
     * Stock disponible = stock physique − stock réservé.
     */
    public function availableStock(): int
    {
        if (! $this->tracksStock()) {
            return 0;
        }

        return max(0, (int) $this->stock_quantity - (int) ($this->stock_reserved ?? 0));
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->availableStock();
    }

    /**
     * Seuil d’alerte effectif.
     * Priorité : stock d’alerte produit → stock minimum produit → seuil global (défaut 3).
     *
     * Règle : disponible > seuil = En stock ; 1..seuil = Stock faible ; 0 = Rupture.
     */
    public function alertThreshold(): int
    {
        if ($this->minimum_alert_stock !== null) {
            return (int) $this->minimum_alert_stock;
        }

        if ($this->minimum_safety_stock !== null) {
            return (int) $this->minimum_safety_stock;
        }

        return StockSettings::lowThreshold();
    }

    /**
     * État de stock pour badges / filtres.
     */
    public function stockStatus(): string
    {
        if (! $this->tracksStock()) {
            return self::STOCK_STATUS_NO_TRACKING;
        }

        $available = $this->availableStock();

        if ($available <= 0) {
            return self::STOCK_STATUS_OUT;
        }

        if ($available <= $this->alertThreshold()) {
            return self::STOCK_STATUS_LOW;
        }

        return self::STOCK_STATUS_IN_STOCK;
    }

    public function getStockStatusLabelAttribute(): string
    {
        if ($this->isService()) {
            return 'Sans gestion de stock';
        }

        if ($this->isNonStocked()) {
            return 'Vendu sans contrôle de stock';
        }

        return self::STOCK_STATUSES[$this->stockStatus()] ?? '—';
    }

    public function isStockLow(): bool
    {
        return $this->stockStatus() === self::STOCK_STATUS_LOW;
    }

    public function isOutOfStock(): bool
    {
        return $this->stockStatus() === self::STOCK_STATUS_OUT;
    }

    public function isInStock(): bool
    {
        return $this->stockStatus() === self::STOCK_STATUS_IN_STOCK;
    }

    public function isActive(): bool
    {
        return ($this->status ?? '') === 'Activer';
    }

    public function scopeStocked($query)
    {
        return $query->where('item_kind', self::KIND_STOCKED);
    }

    public function scopeNonStocked($query)
    {
        return $query->where('item_kind', self::KIND_NON_STOCKED);
    }

    public function scopeTracksStock($query)
    {
        return $query->where('item_kind', self::KIND_STOCKED);
    }

    public function scopeServices($query)
    {
        return $query->where('item_kind', self::KIND_SERVICE);
    }

    public function scopeManual($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('source')->orWhere('source', '!=', 'shopify');
        });
    }

    public function scopeShopify($query)
    {
        return $query->where('source', 'shopify');
    }

    /**
     * SQL expression for available stock (physical − reserved).
     */
    public static function availableStockSql(): string
    {
        return 'GREATEST(0, COALESCE(stock_quantity, 0) - COALESCE(stock_reserved, 0))';
    }

    /**
     * SQL expression for effective alert threshold (product override or global default).
     */
    public static function alertThresholdSql(): string
    {
        $fallback = (int) StockSettings::lowThreshold();

        return 'COALESCE(minimum_alert_stock, minimum_safety_stock, '.$fallback.')';
    }

    public function scopeInStock($query)
    {
        $available = self::availableStockSql();
        $threshold = self::alertThresholdSql();

        return $query->where('item_kind', self::KIND_STOCKED)
            ->whereRaw("{$available} > {$threshold}");
    }

    public function scopeLowStock($query)
    {
        $available = self::availableStockSql();
        $threshold = self::alertThresholdSql();

        return $query->where('item_kind', self::KIND_STOCKED)
            ->whereRaw("{$available} > 0")
            ->whereRaw("{$available} <= {$threshold}");
    }

    public function scopeOutOfStock($query)
    {
        $available = self::availableStockSql();

        return $query->where('item_kind', self::KIND_STOCKED)
            ->whereRaw("{$available} <= 0");
    }

    public function scopeNoStockTracking($query)
    {
        return $query->whereIn('item_kind', [self::KIND_NON_STOCKED, self::KIND_SERVICE]);
    }

    /**
     * Get the full URL for the product image.
     */
    public function getImageUrlAttribute(): ?string
    {
        return \App\Support\PublicStorage::url($this->image);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function hasVariants(): bool
    {
        return $this->variants()->count() > 1;
    }

    /**
     * Check if this product is synced from Shopify
     */
    public function isShopifyProduct(): bool
    {
        return $this->source === 'shopify';
    }

    /**
     * Get the Shopify admin URL for this product
     */
    public function getShopifyUrlAttribute(): ?string
    {
        if (! $this->isShopifyProduct() || ! $this->external_id) {
            return null;
        }

        $integration = \App\Models\ShopifyIntegration::first();
        if (! $integration || ! $integration->shop_name) {
            return null;
        }

        return sprintf(
            'https://%s.myshopify.com/admin/products/%s',
            $integration->shop_name,
            $this->external_id
        );
    }
}
