<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShopifyProductImporter
{
    public function import(array $product): Product
    {
        return DB::transaction(function () use ($product) {
            $externalId = (string) ($product['id'] ?? '');
            if ($externalId === '') {
                throw new \InvalidArgumentException('Missing Shopify product id.');
            }

            // Check if product already exists
            $existing = Product::query()
                ->where('source', 'shopify')
                ->where('external_id', $externalId)
                ->first();

            // Extract product data
            $title = (string) ($product['title'] ?? 'Untitled Product');
            $sku = $this->extractSku($product);
            $ref = $sku ?: 'SHOPIFY-' . $externalId;
            
            // Get variant data (use first variant for pricing)
            $variant = $this->getFirstVariant($product);
            $price = $variant ? (float) ($variant['price'] ?? 0) : 0;
            $compareAtPrice = $variant ? (float) ($variant['compare_at_price'] ?? 0) : 0;
            $barcode = $variant ? (string) ($variant['barcode'] ?? '') : '';
            
            // Get inventory data
            $inventoryQuantity = $variant ? (int) ($variant['inventory_quantity'] ?? 0) : 0;
            
            // Get product image
            $imageUrl = $this->extractImageUrl($product);
            
            // Get product status
            $shopifyStatus = strtolower((string) ($product['status'] ?? 'draft'));
            $status = $shopifyStatus === 'active' ? 'Activer' : 'Desactiver';
            
            // Get product type/category
            $productType = (string) ($product['product_type'] ?? '');
            $tags = (string) ($product['tags'] ?? '');
            
            // Get description
            $description = strip_tags((string) ($product['body_html'] ?? ''));
            
            $data = [
                'name' => $title,
                'ref' => $ref,
                'sale_price' => $price,
                'cost_price_ht' => $compareAtPrice > 0 ? $compareAtPrice : null,
                'stock_quantity' => $inventoryQuantity,
                'barcode' => $barcode ?: null,
                'product_category' => $productType ?: null,
                'tag' => $tags ?: null,
                'status' => $status,
                'description' => $description ?: null,
                'source' => 'shopify',
                'external_id' => $externalId,
                'shopify_status' => $shopifyStatus,
                'shopify_synced_at' => now(),
            ];

            // Download and store image if available
            if ($imageUrl && !$existing) {
                try {
                    $imagePath = $this->downloadImage($imageUrl, $externalId);
                    if ($imagePath) {
                        $data['image'] = $imagePath;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to download Shopify product image', [
                        'product_id' => $externalId,
                        'image_url' => $imageUrl,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($existing) {
                // Update existing product
                $existing->update($data);
                Log::info('Updated Shopify product', ['product_id' => $externalId, 'ref' => $ref]);
                return $existing;
            }

            // Check if ref already exists (from manual entry)
            $existingByRef = Product::query()->where('ref', $ref)->first();
            if ($existingByRef && !$existingByRef->source) {
                // Update manual product with Shopify data
                $existingByRef->update($data);
                Log::info('Linked existing product to Shopify', ['product_id' => $externalId, 'ref' => $ref]);
                return $existingByRef;
            }

            // Create new product
            $newProduct = Product::create($data);
            Log::info('Created new Shopify product', ['product_id' => $externalId, 'ref' => $ref]);
            
            return $newProduct;
        });
    }

    /**
     * Extract SKU from product variants
     */
    private function extractSku(array $product): string
    {
        $variants = $product['variants'] ?? [];
        if (!is_array($variants) || empty($variants)) {
            return '';
        }

        $firstVariant = $variants[0];
        return trim((string) ($firstVariant['sku'] ?? ''));
    }

    /**
     * Get first variant from product
     */
    private function getFirstVariant(array $product): ?array
    {
        $variants = $product['variants'] ?? [];
        if (!is_array($variants) || empty($variants)) {
            return null;
        }

        return $variants[0];
    }

    /**
     * Extract main image URL from product
     */
    private function extractImageUrl(array $product): ?string
    {
        // Try main image first
        if (isset($product['image']['src'])) {
            return (string) $product['image']['src'];
        }

        // Try images array
        $images = $product['images'] ?? [];
        if (is_array($images) && !empty($images) && isset($images[0]['src'])) {
            return (string) $images[0]['src'];
        }

        return null;
    }

    /**
     * Download and store product image from URL
     */
    private function downloadImage(string $url, string $productId): ?string
    {
        try {
            $contents = file_get_contents($url);
            if ($contents === false) {
                return null;
            }

            // Generate filename from URL
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (!$extension || !in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $extension = 'jpg';
            }

            $filename = 'shopify-' . $productId . '-' . time() . '.' . $extension;
            $path = 'products/' . $filename;

            // Store in public disk
            Storage::disk('public')->put($path, $contents);

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to download Shopify product image', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Import multiple products in batch
     */
    public function importBatch(array $products): array
    {
        $results = [
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($products as $productData) {
            try {
                $existing = Product::query()
                    ->where('source', 'shopify')
                    ->where('external_id', (string) ($productData['id'] ?? ''))
                    ->exists();

                $this->import($productData);

                if ($existing) {
                    $results['updated']++;
                } else {
                    $results['imported']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'product_id' => $productData['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Failed to import Shopify product', [
                    'product' => $productData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
