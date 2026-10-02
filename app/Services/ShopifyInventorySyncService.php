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

        $hintItemId = $product->variants()
            ->when($variantId, fn ($query) => $query->where('id', $variantId))
            ->whereNotNull('inventory_item_id')
            ->value('inventory_item_id');

        $locationId = $this->resolveLocationId($integration, $hintItemId ? (string) $hintItemId : null);
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

        // Absolute quantity from authorized physical warehouses (Shopify = sales channel).
        $available = max(0, app(StockMovementService::class)->shopifyAvailableQuantity($product, (int) $variant->id));

        try {
            $client = new ShopifyApiClient(ShopifyIntegration::query()->where('enabled', true)->first());
            $this->setLevel($client, (string) $variant->inventory_item_id, $locationId, $available);

            $variant->inventory_quantity = $available;
            $variant->save();

            Log::info('Shopify inventory pushed (absolute from physical feed warehouses)', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'sku' => $variant->sku ?: $product->ref,
                'available' => $available,
            ]);
        } catch (\Throwable $e) {
            // Never roll back Libromart physical stock on Shopify API failure.
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
        $primaryLocationId = $integration ? $this->resolveLocationId($integration, $inventoryItemId) : null;

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

        // Loop protection: if Shopify mirrors the quantity we would push from physical feed, ignore.
        $expected = max(0, app(StockMovementService::class)->shopifyAvailableQuantity($product, (int) $variant->id));
        if ($available === $expected) {
            if ((int) $variant->inventory_quantity !== $available) {
                $variant->inventory_quantity = $available;
                $variant->save();
            }

            return null;
        }

        if ((int) $variant->inventory_quantity === $available) {
            $currentSlotQty = $variant->onlineStock();
            if ($currentSlotQty === $available) {
                return null;
            }
        }

        // Update online mirror only — never modify physical warehouses from Shopify webhooks.
        app(StockMovementService::class)->syncVariantOnlineWarehouseFromExternal(
            $variant,
            $available,
            'Webhook inventaire Shopify (miroir canal)'
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

    protected function resolveLocationId(ShopifyIntegration $integration, ?string $hintInventoryItemId = null): ?string
    {
        if (filled($integration->primary_location_id)) {
            return (string) $integration->primary_location_id;
        }

        $locations = [];
        // locations.json needs read_locations; skip the call (and the 403 log noise) when not granted.
        if ($integration->oauth_scope === null || $integration->oauth_scope === '' || $integration->hasScope('read_locations')) {
            try {
                $locations = (new ShopifyApiClient($integration))->getLocations();
            } catch (\Throwable $e) {
                Log::warning('Shopify locations fetch failed', ['message' => $e->getMessage()]);
            }
        }

        if ($locations === []) {
            return $this->resolveLocationIdFromInventoryLevels($integration, $hintInventoryItemId);
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

    /**
     * Fallback without read_locations: derive the stocking location from inventory_levels
     * (read_inventory). Uses the single location holding the item(s) when unambiguous.
     */
    protected function resolveLocationIdFromInventoryLevels(ShopifyIntegration $integration, ?string $hintInventoryItemId): ?string
    {
        $itemIds = ProductVariant::query()
            ->whereNotNull('inventory_item_id')
            ->where('inventory_item_id', '!=', '')
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('inventory_item_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (filled($hintInventoryItemId)) {
            array_unshift($itemIds, (string) $hintInventoryItemId);
        }

        $itemIds = array_values(array_unique($itemIds));
        if ($itemIds === []) {
            return null;
        }

        try {
            $levels = (new ShopifyApiClient($integration))->getInventoryLevels($itemIds);
        } catch (\Throwable $e) {
            Log::warning('Shopify inventory_levels location fallback failed', ['message' => $e->getMessage()]);

            return null;
        }

        $counts = collect($levels)->pluck('location_id')->filter()->map(fn ($id) => (string) $id)->countBy();
        if ($counts->isEmpty()) {
            return null;
        }

        if ($counts->count() > 1) {
            Log::warning('Shopify location fallback: several locations found, using the most common one (grant read_locations to pick the primary location)', [
                'locations' => $counts->all(),
            ]);
        }

        $locationId = (string) $counts->sortDesc()->keys()->first();
        $integration->forceFill(['primary_location_id' => $locationId])->save();
        Log::info('Shopify primary location resolved via inventory_levels', ['location_id' => $locationId]);

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
