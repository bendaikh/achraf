<?php

namespace App\Services;

use App\Models\ShopifyIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyApiClient
{
    protected ShopifyIntegration $integration;

    /**
     * Stores the next page URL from the Link header for pagination
     */
    protected ?string $nextPageUrl = null;

    public function __construct(ShopifyIntegration $integration)
    {
        $this->integration = $integration;
    }

    public function getOrders(array $params = []): array
    {
        $defaultParams = [
            'status' => 'any',
            'limit' => 250,
        ];

        $queryParams = array_merge($defaultParams, $params);

        $response = $this->makeRequest('GET', 'orders.json', $queryParams);

        return $response['orders'] ?? [];
    }

    public function getOrder(string $orderId): ?array
    {
        $response = $this->makeRequest('GET', "orders/{$orderId}.json");

        return $response['order'] ?? null;
    }

    public function createOrder(array $order): array
    {
        $response = $this->makeRequest('POST', 'orders.json', ['order' => $order]);

        if (! isset($response['order']) || ! is_array($response['order'])) {
            throw new \RuntimeException('Shopify did not return the created order.');
        }

        return $response['order'];
    }

    public function findOrderByNoteAttribute(string $attribute, string $value): ?array
    {
        $createdAtMin = now()->subDays(30)->toIso8601String();

        foreach ($this->getAllOrders(['created_at_min' => $createdAtMin, 'status' => 'any']) as $orders) {
            foreach ($orders as $order) {
                foreach ($order['note_attributes'] ?? [] as $noteAttribute) {
                    if (($noteAttribute['name'] ?? null) === $attribute
                        && (string) ($noteAttribute['value'] ?? '') === $value) {
                        return $order;
                    }
                }
            }
        }

        return null;
    }

    public function getOrdersSince(string $sinceId, int $limit = 250): array
    {
        return $this->getOrders([
            'since_id' => $sinceId,
            'limit' => $limit,
        ]);
    }

    public function getOrdersCreatedAfter(string $dateTime, int $limit = 250): array
    {
        return $this->getOrders([
            'created_at_min' => $dateTime,
            'limit' => $limit,
            'order' => 'created_at desc',
        ]);
    }

    /**
     * Fetch ALL orders using cursor-based pagination.
     * Returns a generator that yields batches of orders.
     *
     * @param  array  $params  Query parameters
     */
    public function getAllOrders(array $params = []): \Generator
    {
        $defaultParams = [
            'status' => 'any',
            'limit' => 250,
        ];

        $queryParams = array_merge($defaultParams, $params);

        // Initial request
        $orders = $this->getOrders($queryParams);

        if (! empty($orders)) {
            yield $orders;
        }

        // Continue fetching next pages as long as there's a next page URL
        while ($this->nextPageUrl !== null) {
            $orders = $this->fetchNextPage();

            if (! empty($orders)) {
                yield $orders;
            }
        }
    }

    /**
     * Fetch the next page using the cursor URL from the Link header
     */
    protected function fetchNextPage(): array
    {
        if ($this->nextPageUrl === null) {
            return [];
        }

        $url = $this->nextPageUrl;
        $this->nextPageUrl = null; // Reset before making the request

        $accessToken = $this->integration->oauth_access_token ?? $this->integration->api_access_token;

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->get($url);

            if ($response->failed()) {
                Log::error('Shopify API pagination request failed', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);

                return [];
            }

            $this->parseNextPageLink($response->header('Link') ?? '');

            $data = $response->json() ?? [];

            return $data['orders'] ?? [];
        } catch (\Exception $e) {
            Log::error('Shopify API pagination exception', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Parse the Link header to extract the next page URL
     * Shopify Link header format:
     * <https://shop.myshopify.com/admin/api/2024-01/orders.json?page_info=xxx>; rel="next"
     */
    protected function parseNextPageLink(string $linkHeader): void
    {
        $this->nextPageUrl = null;

        if ($linkHeader === '') {
            return;
        }

        // Split multiple links
        $links = explode(',', $linkHeader);

        foreach ($links as $link) {
            if (preg_match('/<(.+?)>;\s*rel="next"/i', trim($link), $matches)) {
                $this->nextPageUrl = $matches[1];

                return;
            }
        }
    }

    protected function makeRequest(string $method, string $endpoint, array $params = []): array
    {
        // Use OAuth token if available, otherwise fall back to API token
        $accessToken = $this->integration->oauth_access_token ?? $this->integration->api_access_token;

        if (! $this->integration->shop_name || ! $accessToken) {
            throw new \RuntimeException('Shopify integration not configured properly.');
        }

        $url = sprintf(
            'https://%s.myshopify.com/admin/api/%s/%s',
            $this->integration->shop_name,
            $this->integration->api_version ?? '2024-01',
            $endpoint
        );

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->{strtolower($method)}($url, $params);

            if ($response->failed()) {
                Log::error('Shopify API request failed', [
                    'status' => $response->status(),
                    'url' => $url,
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException(
                    sprintf('Shopify API error: %s', $response->body())
                );
            }

            // Parse pagination link header for cursor-based pagination
            if ($method === 'GET' && ($endpoint === 'orders.json' || $endpoint === 'products.json')) {
                $this->parseNextPageLink($response->header('Link') ?? '');
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('Shopify API exception', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            throw $e;
        }
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', 'shop.json');

            return isset($response['shop']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function hasNextPage(): bool
    {
        return $this->nextPageUrl !== null;
    }

    /**
     * Get products from Shopify
     */
    public function getProducts(array $params = []): array
    {
        $defaultParams = [
            'limit' => 250,
        ];

        $queryParams = array_merge($defaultParams, $params);

        $response = $this->makeRequest('GET', 'products.json', $queryParams);

        return $response['products'] ?? [];
    }

    /**
     * Get a single product by ID
     */
    public function getProduct(string $productId): ?array
    {
        $response = $this->makeRequest('GET', "products/{$productId}.json");

        return $response['product'] ?? null;
    }

    /**
     * Fetch ALL products using cursor-based pagination.
     * Returns a generator that yields batches of products.
     */
    public function getAllProducts(array $params = []): \Generator
    {
        $defaultParams = [
            'limit' => 250,
        ];

        $queryParams = array_merge($defaultParams, $params);

        // Initial request
        $products = $this->getProducts($queryParams);

        if (! empty($products)) {
            yield $products;
        }

        // Continue fetching next pages using the same pagination mechanism
        while ($this->nextPageUrl !== null) {
            $products = $this->fetchNextPageProducts();

            if (! empty($products)) {
                yield $products;
            }
        }
    }

    /**
     * Fetch the next page of products using the cursor URL from the Link header
     */
    protected function fetchNextPageProducts(): array
    {
        if ($this->nextPageUrl === null) {
            return [];
        }

        $url = $this->nextPageUrl;
        $this->nextPageUrl = null; // Reset before making the request

        $accessToken = $this->integration->oauth_access_token ?? $this->integration->api_access_token;

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->get($url);

            if ($response->failed()) {
                Log::error('Shopify API products pagination request failed', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);

                return [];
            }

            $this->parseNextPageLink($response->header('Link') ?? '');

            $data = $response->json() ?? [];

            return $data['products'] ?? [];
        } catch (\Exception $e) {
            Log::error('Shopify API products pagination exception', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLocations(): array
    {
        $response = $this->makeRequest('GET', 'locations.json');

        return $response['locations'] ?? [];
    }

    public function setInventoryLevel(string $inventoryItemId, string $locationId, int $available): array
    {
        return $this->makeRequest('POST', 'inventory_levels/set.json', [
            'location_id' => (int) $locationId,
            'inventory_item_id' => (int) $inventoryItemId,
            'available' => $available,
        ]);
    }

    public function connectInventoryLevel(string $inventoryItemId, string $locationId): array
    {
        return $this->makeRequest('POST', 'inventory_levels/connect.json', [
            'location_id' => (int) $locationId,
            'inventory_item_id' => (int) $inventoryItemId,
            'relocate_if_necessary' => true,
        ]);
    }

    /**
     * Get all registered webhooks
     */
    public function getWebhooks(): array
    {
        $response = $this->makeRequest('GET', 'webhooks.json');

        return $response['webhooks'] ?? [];
    }

    /**
     * Last webhook registration error (human-readable), if any.
     */
    public ?string $lastWebhookError = null;

    /**
     * Register a webhook in Shopify
     */
    public function createWebhook(string $topic, string $address): ?array
    {
        $this->lastWebhookError = null;
        $accessToken = $this->integration->oauth_access_token ?? $this->integration->api_access_token;

        if (! $this->integration->shop_name || ! $accessToken) {
            throw new \RuntimeException('Shopify integration not configured properly.');
        }

        $url = sprintf(
            'https://%s.myshopify.com/admin/api/%s/webhooks.json',
            $this->integration->shop_name,
            $this->integration->api_version ?? '2024-01'
        );

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($url, [
                    'webhook' => [
                        'topic' => $topic,
                        'address' => $address,
                        'format' => 'json',
                    ],
                ]);

            if ($response->failed()) {
                $body = $response->json() ?? [];
                $topicErrors = data_get($body, 'errors.topic');
                $this->lastWebhookError = is_array($topicErrors)
                    ? implode(' ', $topicErrors)
                    : ($response->body() ?: 'HTTP '.$response->status());

                Log::error('Shopify webhook registration failed', [
                    'status' => $response->status(),
                    'topic' => $topic,
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json()['webhook'] ?? null;
        } catch (\Exception $e) {
            $this->lastWebhookError = $e->getMessage();
            Log::error('Shopify webhook registration exception', [
                'message' => $e->getMessage(),
                'topic' => $topic,
            ]);

            return null;
        }
    }

    /**
     * Delete a webhook by ID
     */
    public function deleteWebhook(string $webhookId): bool
    {
        $accessToken = $this->integration->oauth_access_token ?? $this->integration->api_access_token;

        if (! $this->integration->shop_name || ! $accessToken) {
            throw new \RuntimeException('Shopify integration not configured properly.');
        }

        $url = sprintf(
            'https://%s.myshopify.com/admin/api/%s/webhooks/%s.json',
            $this->integration->shop_name,
            $this->integration->api_version ?? '2024-01',
            $webhookId
        );

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->delete($url);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Shopify webhook deletion exception', [
                'message' => $e->getMessage(),
                'webhook_id' => $webhookId,
            ]);

            return false;
        }
    }
}
