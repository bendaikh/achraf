<?php

namespace App\Services\Jumia;

use App\Models\JumiaIntegration;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JumiaApiClient
{
    public function __construct(
        protected JumiaIntegration $integration
    ) {}

    public function testConnection(): bool
    {
        if ($this->integration->usesVendorCenter()) {
            $this->ensureAccessToken();

            $response = $this->vendorRequest('GET', '/orders', ['size' => 1]);

            return is_array($response);
        }

        $response = $this->legacyCall('GetOrders', ['Limit' => '1', 'Offset' => '0']);

        return $response !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(array $filters = []): array
    {
        if ($this->integration->usesVendorCenter()) {
            return $this->getVendorOrdersPage($filters);
        }

        $params = array_merge([
            'Limit' => '100',
            'Offset' => '0',
        ], $filters);

        $response = $this->legacyCall('GetOrders', $params);

        return $this->extractList($response, 'Orders', 'Order');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllOrders(array $filters = []): \Generator
    {
        if ($this->integration->usesVendorCenter()) {
            $pageFilters = $filters;
            unset($pageFilters['token']);

            do {
                $page = $this->getVendorOrdersPage($pageFilters);
                $orders = $page['orders'];

                if ($orders === []) {
                    break;
                }

                yield $orders;

                if ($page['is_last_page'] || ! $page['next_token']) {
                    break;
                }

                $pageFilters['token'] = $page['next_token'];
            } while (true);

            return;
        }

        $offset = (int) ($filters['Offset'] ?? 0);
        $limit = (int) ($filters['Limit'] ?? 100);
        unset($filters['Offset'], $filters['Limit']);

        do {
            $batch = $this->getOrders(array_merge($filters, [
                'Offset' => (string) $offset,
                'Limit' => (string) $limit,
            ]));

            if ($batch === []) {
                break;
            }

            yield $batch;

            if (count($batch) < $limit) {
                break;
            }

            $offset += $limit;
        } while (true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrderItems(string $orderId): array
    {
        if ($this->integration->usesVendorCenter()) {
            $response = $this->vendorRequest('GET', '/orders/items', [
                'orderId' => $orderId,
            ]);

            return $this->normalizeVendorOrderItems($response);
        }

        $response = $this->legacyCall('GetOrderItems', ['OrderId' => $orderId]);

        return $this->extractList($response, 'OrderItems', 'OrderItem');
    }

    public function setStatusToReadyToShip(string $orderItemIds): ?array
    {
        return $this->legacyCall('SetStatusToReadyToShip', [], $this->orderItemsXml($orderItemIds));
    }

    public function setStatusToShipped(string $orderItemIds, string $shippingProvider, ?string $trackingNumber = null): ?array
    {
        $extra = ['ShippingProvider' => $shippingProvider];
        if ($trackingNumber) {
            $extra['TrackingNumber'] = $trackingNumber;
        }

        return $this->legacyCall('SetStatusToShipped', $extra, $this->orderItemsXml($orderItemIds));
    }

    public function setStatusToDelivered(string $orderItemIds): ?array
    {
        return $this->legacyCall('SetStatusToDelivered', [], $this->orderItemsXml($orderItemIds));
    }

    public function setStatusToCanceled(string $orderItemIds, string $reason): ?array
    {
        return $this->legacyCall('SetStatusToCanceled', ['Reason' => $reason], $this->orderItemsXml($orderItemIds));
    }

    /**
     * Push stock quantity to Jumia for a seller SKU.
     */
    public function updateProductStock(string $sellerSku, int $stock, ?string $productSid = null): ?array
    {
        $sellerSku = trim($sellerSku);
        if ($sellerSku === '') {
            throw new \InvalidArgumentException('Seller SKU is required for Jumia stock update.');
        }

        $stock = max(0, $stock);

        if ($this->integration->usesVendorCenter()) {
            return $this->updateVendorProductStock($sellerSku, $stock, $productSid);
        }

        return $this->updateLegacyProductStock($sellerSku, $stock);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCatalogProductBySellerSku(string $sellerSku): ?array
    {
        if (! $this->integration->usesVendorCenter()) {
            return null;
        }

        $response = $this->vendorRequest('GET', '/catalog/products', [
            'sellerSku' => trim($sellerSku),
            'size' => 1,
        ]);

        $products = $response['products'] ?? $response['content'] ?? [];

        if (! is_array($products) || $products === []) {
            return null;
        }

        $product = $products[0] ?? null;

        return is_array($product) ? $product : null;
    }

    protected function updateVendorProductStock(string $sellerSku, int $stock, ?string $productSid): ?array
    {
        if (! $productSid) {
            $catalogProduct = $this->findCatalogProductBySellerSku($sellerSku);
            $productSid = $catalogProduct['productSid'] ?? $catalogProduct['id'] ?? $catalogProduct['sid'] ?? null;
        }

        $entry = [
            'sellerSku' => $sellerSku,
            'stock' => $stock,
        ];

        if ($productSid) {
            $entry['id'] = (string) $productSid;
        }

        $response = $this->vendorRequest('POST', '/feeds/products/stock', [], [
            'products' => [$entry],
        ]);

        if (is_string($productSid) && $productSid !== '') {
            Product::query()
                ->whereRaw('LOWER(ref) = ?', [strtolower($sellerSku)])
                ->update(['jumia_product_sid' => $productSid]);
        }

        return $response;
    }

    protected function updateLegacyProductStock(string $sellerSku, int $stock): ?array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Request><Product>'
            .'<SellerSku>'.htmlspecialchars($sellerSku, ENT_XML1).'</SellerSku>'
            .'<Quantity>'.$stock.'</Quantity>'
            .'</Product></Request>';

        return $this->legacyCall('ProductUpdate', [], $xml);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{orders: array<int, array<string, mixed>>, next_token: ?string, is_last_page: bool}
     */
    protected function getVendorOrdersPage(array $filters = []): array
    {
        $query = [];

        if (! empty($filters['UpdatedAfter'])) {
            $query['updatedAfter'] = $this->formatVendorDate($filters['UpdatedAfter']);
            $query['updatedBefore'] = $this->formatVendorDate($filters['UpdatedBefore'] ?? now());
        } elseif (! empty($filters['updatedAfter'])) {
            $query['updatedAfter'] = $this->formatVendorDate($filters['updatedAfter']);
            if (! empty($filters['updatedBefore'])) {
                $query['updatedBefore'] = $this->formatVendorDate($filters['updatedBefore']);
            } else {
                $query['updatedBefore'] = $this->formatVendorDate(now());
            }
        }

        if (! empty($filters['token'])) {
            $query['token'] = (string) $filters['token'];
        }

        $query['size'] = (int) ($filters['size'] ?? $filters['Size'] ?? 100);

        $response = $this->vendorRequest('GET', '/orders', $query);

        $orders = [];
        if (isset($response['orders']) && is_array($response['orders'])) {
            $orders = array_values(array_filter($response['orders'], 'is_array'));
        } elseif (isset($response['content']) && is_array($response['content'])) {
            $orders = array_values(array_filter($response['content'], 'is_array'));
        } elseif ($this->isAssoc($response) && isset($response['id'])) {
            $orders = [$response];
        } elseif (is_array($response) && array_is_list($response)) {
            $orders = array_values(array_filter($response, 'is_array'));
        }

        return [
            'orders' => $orders,
            'next_token' => isset($response['nextToken']) ? (string) $response['nextToken'] : null,
            'is_last_page' => (bool) ($response['isLastPage'] ?? true),
        ];
    }

    protected function ensureAccessToken(): void
    {
        if (
            $this->integration->access_token
            && $this->integration->access_token_expires_at
            && $this->integration->access_token_expires_at->isFuture()
        ) {
            return;
        }

        $tokenUrl = rtrim($this->vendorApiBaseUrl(), '/').'/token';

        $payload = [
            'client_id' => $this->integration->client_id,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->integration->refresh_token,
        ];

        $response = Http::timeout(30)
            ->asJson()
            ->post($tokenUrl, $payload);

        if ($response->failed()) {
            $response = Http::timeout(30)
                ->asForm()
                ->post($tokenUrl, $payload);
        }

        if ($response->failed()) {
            throw new \RuntimeException(
                sprintf('Jumia token request failed (%d): %s', $response->status(), $response->body())
            );
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('Jumia token response did not include an access_token.');
        }

        $expiresIn = (int) ($data['expires_in'] ?? 43200);

        $this->integration->forceFill([
            'access_token' => $data['access_token'],
            'access_token_expires_at' => now()->addSeconds(max(60, $expiresIn - 60)),
        ]);

        if (! empty($data['refresh_token'])) {
            $this->integration->refresh_token = $data['refresh_token'];
        }

        $this->integration->save();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|array<int, mixed>
     */
    protected function vendorRequest(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if (! $this->integration->isConfigured()) {
            throw new \RuntimeException('Jumia integration is not configured.');
        }

        $this->ensureAccessToken();

        $url = rtrim($this->vendorApiBaseUrl(), '/').'/'.ltrim($path, '/');

        try {
            $request = Http::timeout(30)
                ->withToken((string) $this->integration->access_token)
                ->acceptJson();

            $response = match (strtoupper($method)) {
                'POST' => $request->post($url, $body ?? []),
                'PUT' => $request->put($url, $body ?? []),
                'PATCH' => $request->patch($url, $body ?? []),
                'DELETE' => $request->delete($url, $body ?? []),
                default => $request->get($url, $query),
            };

            if ($response->failed()) {
                throw new \RuntimeException(
                    sprintf('Jumia API HTTP error %d: %s', $response->status(), $response->body())
                );
            }

            $data = $response->json();

            if (! is_array($data)) {
                throw new \RuntimeException('Invalid JSON response from Jumia Vendor API.');
            }

            if (isset($data['error']) && is_array($data['error'])) {
                $message = $data['error']['message'] ?? json_encode($data['error']);

                throw new \RuntimeException((string) $message);
            }

            return $data;
        } catch (\RuntimeException $e) {
            Log::error('Jumia Vendor API call failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function vendorApiBaseUrl(): string
    {
        return rtrim($this->integration->api_base_url ?: JumiaIntegration::DEFAULT_API_BASE_URL, '/');
    }

    protected function formatVendorDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $string = (string) $value;

        try {
            return \Carbon\Carbon::parse($string)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $string;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeVendorOrderItems(?array $response): array
    {
        if (! is_array($response)) {
            return [];
        }

        if (isset($response['items']) && is_array($response['items'])) {
            return array_values(array_filter($response['items'], 'is_array'));
        }

        if (isset($response['orderItems']) && is_array($response['orderItems'])) {
            return array_values(array_filter($response['orderItems'], 'is_array'));
        }

        if ($this->isAssoc($response) && isset($response['id'])) {
            return [$response];
        }

        if (array_is_list($response)) {
            return array_values(array_filter($response, 'is_array'));
        }

        return [];
    }

    protected function legacyCall(string $action, array $extraParams = [], ?string $xmlBody = null): ?array
    {
        if (! $this->integration->usesLegacyApi()) {
            throw new \RuntimeException('Legacy Jumia API credentials are not configured.');
        }

        $params = array_merge([
            'Action' => $action,
            'Format' => 'JSON',
            'Timestamp' => now()->format('c'),
            'UserID' => $this->integration->user_id,
            'Version' => $this->integration->api_version ?? '1.0',
        ], $extraParams);

        $signature = $this->signLegacy($params);
        $params['Signature'] = $signature;

        $url = rtrim((string) $this->integration->api_base_url, '/');

        try {
            $request = Http::timeout(30);

            if ($xmlBody !== null) {
                $response = $request
                    ->withBody($xmlBody, 'application/xml')
                    ->post($url.'?'.$this->buildQuery($params));
            } else {
                $response = $request->get($url, $params);
            }

            if ($response->failed()) {
                throw new \RuntimeException(
                    sprintf('Jumia API HTTP error %d: %s', $response->status(), $response->body())
                );
            }

            $data = $response->json();

            if (! is_array($data)) {
                throw new \RuntimeException('Invalid JSON response from Jumia API.');
            }

            if (isset($data['ErrorResponse'])) {
                $message = $data['ErrorResponse']['Head']['ErrorMessage']
                    ?? $data['ErrorResponse']['Head']['ErrorCode']
                    ?? 'Unknown Jumia API error';

                throw new \RuntimeException((string) $message);
            }

            return $data['SuccessResponse']['Body'] ?? $data['SuccessResponse'] ?? $data;
        } catch (\RuntimeException $e) {
            Log::error('Jumia API call failed', [
                'action' => $action,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function signLegacy(array $params): string
    {
        ksort($params);

        $parts = [];
        foreach ($params as $name => $value) {
            $parts[] = rawurlencode((string) $name).'='.rawurlencode((string) $value);
        }

        $stringToSign = str_replace(['%2D', '%2E'], ['-', '.'], implode('&', $parts));

        return hash_hmac('sha256', $stringToSign, (string) $this->integration->api_key);
    }

    protected function buildQuery(array $params): string
    {
        return http_build_query($params);
    }

    protected function orderItemsXml(string $orderItemIds): string
    {
        $ids = array_filter(array_map('trim', explode(',', $orderItemIds)));

        $items = '';
        foreach ($ids as $id) {
            $items .= '<OrderItem><OrderItemId>'.htmlspecialchars($id, ENT_XML1).'</OrderItemId></OrderItem>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><Request>'.$items.'</Request>';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractList(?array $response, string $containerKey, string $itemKey): array
    {
        if (! is_array($response)) {
            return [];
        }

        $container = $response[$containerKey] ?? $response;

        if (! is_array($container)) {
            return [];
        }

        $items = $container[$itemKey] ?? $container;

        if ($items === null || $items === []) {
            return [];
        }

        if ($this->isAssoc($items)) {
            return [$items];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    protected function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
