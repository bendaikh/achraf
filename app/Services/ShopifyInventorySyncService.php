<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopifyIntegration;
use Illuminate\Support\Facades\Log;

class ShopifyInventorySyncService
{
    public function pushProductStock(Product $product, ?int $variantId = null): void
    {
        if (! $product->isShopifyProduct() || ! $product->tracksStock()) {
            return;
        }

        $integration = $this->enabledIntegration();
        if (! $integration) {
            return;
        }

        $locationId = $this->resolveLocationId($integration);
        if ($locationId === null) {
            return;
        }

        if ($product->hasVariants()) {
            $variants = $product->variants()
                ->when($variantId, fn ($query) => $query->where('id', $variantId))
                ->get();

            foreach ($variants as $variant) {
                $this->pushVariantStock($product, $variant, $locationId);
            }

            return;
        }

        $variant = $this->singleVariant($product);
        if ($variant) {
            $this->pushVariantStock($product, $variant, $locationId);
        }
    }

    protected function pushVariantStock(Product $product, ProductVariant $variant, string $locationId): void
    {
        if (! filled($variant->inventory_item_id)) {
            return;
        }

        $available = max(0, $variant->onlineStock());

        try {
            $client = new ShopifyApiClient(ShopifyIntegration::query()->where('enabled', true)->first());
            $this->setLevel($client, (string) $variant->inventory_item_id, $locationId, $available);

            $variant->inventory_quantity = $available;
            $variant->save();

            Log::info('Shopify inventory pushed', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'sku' => $variant->sku ?: $product->ref,
                'available' => $available,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Shopify inventory push failed', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'sku' => $variant->sku ?: $product->ref,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function applyInventoryLevelUpdate(string $inventoryItemId, string $locationId, int $available): ?Product
    {
        $integration = $this->enabledIntegration();
        $primaryLocationId = $integration ? $this->resolveLocationId($integration) : null;

        if ($primaryLocationId !== null && $primaryLocationId !== $locationId) {
            return null;
        }

        $variant = ProductVariant::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->with('product.variants')
            ->first();

        if (! $variant?->product || ! $variant->product->tracksStock()) {
            return null;
        }

        $available = max(0, $available);
        $product = $variant->product;

        if ((int) $variant->inventory_quantity === $available) {
            $currentSlotQty = $variant->onlineStock();
            if ($currentSlotQty === $available) {
                return null;
            }
        }

        app(StockMovementService::class)->syncVariantOnlineWarehouseFromExternal(
            $variant,
            $available,
            'Webhook inventaire Shopify'
        );

        return $product->fresh();
    }

    protected function setLevel(ShopifyApiClient $client, string $inventoryItemId, string $locationId, int $available): void
    {
        try {
            $client->setInventoryLevel($inventoryItemId, $locationId, $available);
        } catch (\Throwable $e) {
            $client->connectInventoryLevel($inventoryItemId, $locationId);
            $client->setInventoryLevel($inventoryItemId, $locationId, $available);
        }
    }

    protected function singleVariant(Product $product): ?ProductVariant
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get();

        if ($variants->count() !== 1) {
            return null;
        }

        return $variants->first();
    }

    protected function resolveLocationId(ShopifyIntegration $integration): ?string
    {
        if (filled($integration->primary_location_id)) {
            return (string) $integration->primary_location_id;
        }

        try {
            $locations = (new ShopifyApiClient($integration))->getLocations();
        } catch (\Throwable $e) {
            Log::warning('Shopify locations fetch failed', ['message' => $e->getMessage()]);

            return null;
        }

        $primary = collect($locations)->first(fn ($location) => ! empty($location['primary']));
        $active = collect($locations)->first(fn ($location) => ($location['active'] ?? true) && empty($location['legacy']));
        $location = $primary ?? $active ?? ($locations[0] ?? null);
        $locationId = isset($location['id']) ? (string) $location['id'] : null;

        if ($locationId !== null) {
            $integration->forceFill(['primary_location_id' => $locationId])->save();
        }

        return $locationId;
    }

    protected function enabledIntegration(): ?ShopifyIntegration
    {
        $integration = ShopifyIntegration::query()->where('enabled', true)->first();

        if (! $integration) {
            return null;
        }

        return $integration;
    }
}
