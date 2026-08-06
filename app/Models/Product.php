<?php

namespace App\Models;

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
        'stock_magasin',
        'stock_enligne',
        'location',
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

    public function isStockLow(): bool
    {
        if (! $this->tracksStock()) {
            return false;
        }

        if ($this->minimum_alert_stock !== null) {
            return $this->stock_quantity <= $this->minimum_alert_stock;
        }
        if ($this->minimum_safety_stock !== null) {
            return $this->stock_quantity <= $this->minimum_safety_stock;
        }

        return false;
    }

    public function scopeStocked($query)
    {
        return $query->where('item_kind', self::KIND_STOCKED);
    }

    public function scopeTracksStock($query)
    {
        return $query->where('item_kind', self::KIND_STOCKED);
    }

    public function scopeServices($query)
    {
        return $query->where('item_kind', self::KIND_SERVICE);
    }

    public function scopeLowStock($query)
    {
        return $query->where('item_kind', self::KIND_STOCKED)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('minimum_alert_stock')
                        ->whereColumn('stock_quantity', '<=', 'minimum_alert_stock');
                })->orWhere(function ($q2) {
                    $q2->whereNull('minimum_alert_stock')
                        ->whereNotNull('minimum_safety_stock')
                        ->whereColumn('stock_quantity', '<=', 'minimum_safety_stock');
                });
            });
    }

    public function isOutOfStock(): bool
    {
        if (! $this->tracksStock()) {
            return false;
        }

        return $this->stock_quantity <= 0;
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
