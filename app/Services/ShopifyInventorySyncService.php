<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopifyIntegration;
use Illuminate\Support\Facades\Log;

class ShopifyInventorySyncService
{
    public function pushProductStock(Product $product): void
    {
        if (! $product->isShopifyProduct() || ! $product->tracksStock()) {
            return;
        }

        $integration = $this->enabledIntegration();
        if (! $integration) {
            return;
        }

        $variant = $this->singleVariant($product);
        if (! $variant || ! filled($variant->inventory_item_id)) {
            return;
        }

        $locationId = $this->resolveLocationId($integration);
        if ($locationId === null) {
            return;
        }

        $available = max(0, (int) $product->stock_enligne);

        try {
            $client = new ShopifyApiClient($integration);
            $this->setLevel($client, (string) $variant->inventory_item_id, $locationId, $available);

            Log::info('Shopify inventory pushed', [
                'product_id' => $product->id,
                'sku' => $product->ref,
                'available' => $available,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Shopify inventory push failed', [
                'product_id' => $product->id,
                'sku' => $product->ref,
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
            $total = (int) $product->variants->sum('inventory_quantity');
            if ((int) $product->stock_enligne === $total) {
                return null;
            }
        }

        $variant->inventory_quantity = $available;
        $variant->save();

        $product->unsetRelation('variants');
        $total = max(0, (int) $product->variants()->sum('inventory_quantity'));
        $product->stock_enligne = $total;
        $product->stock_quantity = $total;
        $product->save();

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

        $token = $integration->oauth_access_token ?? $integration->api_access_token;
        if (! $integration->shop_name || ! $token) {
            return null;
        }

        return $integration;
    }
}
