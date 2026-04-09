<?php

namespace App\Services;

use App\Models\ShopifyIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyApiClient
{
    protected ShopifyIntegration $integration;

    public function __construct(ShopifyIntegration $integration)
    {
        $this->integration = $integration;
    }

    public function getOrders(array $params = []): array
    {
        $defaultParams = [
            'status' => 'any',
            'limit' => 50,
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

    public function getOrdersSince(string $sinceId, int $limit = 50): array
    {
        return $this->getOrders([
            'since_id' => $sinceId,
            'limit' => $limit,
            'order' => 'created_at asc',
        ]);
    }

    public function getOrdersCreatedAfter(string $dateTime, int $limit = 50): array
    {
        return $this->getOrders([
            'created_at_min' => $dateTime,
            'limit' => $limit,
            'order' => 'created_at asc',
        ]);
    }

    protected function makeRequest(string $method, string $endpoint, array $params = []): array
    {
        if (! $this->integration->shop_name || ! $this->integration->api_access_token) {
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
                'X-Shopify-Access-Token' => $this->integration->api_access_token,
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
}
