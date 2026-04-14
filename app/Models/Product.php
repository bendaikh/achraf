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
    ];

    protected $casts = [
        'cost_price_ht' => 'decimal:2',
        'cost_price_ttc' => 'decimal:2',
        'last_purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'minimum_safety_stock' => 'integer',
        'minimum_alert_stock' => 'integer',
        'stock_quantity' => 'integer',
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

        // Check if standard symlink path exists and is accessible
        $symlinkPath = public_path('storage/' . $this->image);
        $directPath = storage_path('app/public/' . $this->image);
        
        // If the symlink works (local dev), use standard path
        if (file_exists($symlinkPath) && is_readable($symlinkPath)) {
            return asset('storage/' . $this->image);
        }
        
        // If direct path exists (production shared hosting), use that
        if (file_exists($directPath)) {
            return asset('storage/app/public/' . $this->image);
        }
        
        // Fallback to standard path
        return asset('storage/' . $this->image);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
