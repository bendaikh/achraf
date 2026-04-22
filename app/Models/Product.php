<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'ref',
        'image',
        'cost_price_ht',
        'cost_price_ttc',
        'last_purchase_price',
        'sale_price',
        'minimum_safety_stock',
        'minimum_alert_stock',
        'stock_quantity',
        'barcode',
        'vat_category',
        'element_type',
        'tag',
        'status',
        'product_category',
        'description',
        'source',
        'external_id',
        'shopify_status',
        'shopify_synced_at',
    ];

    protected $casts = [
        'cost_price_ht' => 'decimal:2',
        'cost_price_ttc' => 'decimal:2',
        'last_purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'minimum_safety_stock' => 'integer',
        'minimum_alert_stock' => 'integer',
        'stock_quantity' => 'integer',
        'shopify_synced_at' => 'datetime',
    ];

    public function isStockLow(): bool
    {
        if ($this->minimum_alert_stock !== null) {
            return $this->stock_quantity <= $this->minimum_alert_stock;
        }
        if ($this->minimum_safety_stock !== null) {
            return $this->stock_quantity <= $this->minimum_safety_stock;
        }

        return false;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    /**
     * Get the full URL for the product image
     * Works in both local development and production (shared hosting)
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        // For shared hosting where symlinks don't work via HTTP
        // Set STORAGE_DIRECT_PATH=true in your .env file
        if (config('app.storage_direct_path', false)) {
            return asset('storage/app/public/' . $this->image);
        }
        
        // Standard Laravel storage path (works with symlink)
        return asset('storage/' . $this->image);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
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
        if (!$this->isShopifyProduct() || !$this->external_id) {
            return null;
        }

        $integration = \App\Models\ShopifyIntegration::first();
        if (!$integration || !$integration->shop_name) {
            return null;
        }

        return sprintf(
            'https://%s.myshopify.com/admin/products/%s',
            $integration->shop_name,
            $this->external_id
        );
    }
}
