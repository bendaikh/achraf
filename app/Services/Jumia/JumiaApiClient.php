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
     * Read the current stock quantity for a seller SKU on Jumia.
     *
     * @return array{stock: int|null, variation_id: ?string}|null Null when the product is not found.
     *         `stock` may be null when the Vendor catalog does not expose inventory fields.
     */
    public function getStockForSellerSku(string $sellerSku): ?array
    {
        $sellerSku = trim($sellerSku);
        if ($sellerSku === '') {
            return null;
        }

        if ($this->integration->usesVendorCenter()) {
            $catalogProduct = $this->findCatalogProductBySellerSku($sellerSku);

            if (! is_array($catalogProduct)) {
                return null;
            }

            $stock = $this->extractStockFromCatalogProduct($catalogProduct, $sellerSku);

            // Vendor catalog often omits stock fields; null means "unknown", not zero.
            return [
                'stock' => $stock,
                'variation_id' => $this->resolveCatalogVariationId($catalogProduct, $sellerSku),
            ];
        }

        return $this->getLegacyProductStock($sellerSku);
    }

    /**
     * Push stock updates for multiple products in a single feed request.
     *
     * @param  array<int, array{sellerSku: string, stock: int, variationId?: ?string}>  $products
     * @return array<string, mixed>|null
     */
    public function updateProductStockBatch(array $products): ?array
    {
        if ($products === []) {
            return null;
        }

        if ($this->integration->usesVendorCenter()) {
            return $this->updateVendorProductStockBatch($products);
        }

        $lastResponse = null;

        foreach ($products as $product) {
            $sellerSku = trim((string) ($product['sellerSku'] ?? ''));
            if ($sellerSku === '') {
                continue;
            }

            $lastResponse = $this->updateLegacyProductStock(
                $sellerSku,
                max(0, (int) ($product['stock'] ?? 0))
            );
        }

        return $lastResponse;
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

    /**
     * Page through the full Jumia vendor catalog.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function getAllCatalogProducts(int $pageSize = 50): \Generator
    {
        if (! $this->integration->usesVendorCenter()) {
            return;
        }

        $token = null;
        $pageSize = max(1, min(100, $pageSize));

        do {
            $query = ['size' => $pageSize];
            if ($token) {
                $query['token'] = $token;
            }

            $response = $this->vendorRequest('GET', '/catalog/products', $query);
            $products = $response['products'] ?? $response['content'] ?? [];

            if (is_array($products) && $products !== []) {
                yield array_values(array_filter($products, 'is_array'));
            }

            $isLastPage = (bool) ($response['isLastPage'] ?? true);
            $token = isset($response['nextToken']) ? (string) $response['nextToken'] : null;

            if ($isLastPage || $token === null || $token === '') {
                break;
            }
        } while (true);
    }

    /**
     * Build a lowercase sellerSku => variationId map from the full Jumia catalog.
     *
     * @return array<string, string>
     */
    public function getCatalogSellerSkuMap(int $pageSize = 50): array
    {
        $map = [];

        foreach ($this->getAllCatalogProducts($pageSize) as $products) {
            foreach ($products as $catalogProduct) {
                $variations = $catalogProduct['variations'] ?? [];

                if (is_array($variations) && $variations !== []) {
                    foreach ($variations as $variation) {
                        if (! is_array($variation)) {
                            continue;
                        }

                        $sellerSku = trim((string) ($variation['sellerSku'] ?? ''));
                        $variationId = $variation['id'] ?? $variation['productSid'] ?? $variation['sid'] ?? null;

                        if ($sellerSku !== '' && $variationId !== null && $variationId !== '') {
                            $map[strtolower($sellerSku)] = (string) $variationId;
                        }
                    }

                    continue;
                }

                $sellerSku = trim((string) ($catalogProduct['sellerSku'] ?? $catalogProduct['parentSku'] ?? ''));
                $variationId = $this->resolveCatalogVariationId($catalogProduct, $sellerSku);

                if ($sellerSku !== '' && $variationId) {
                    $map[strtolower($sellerSku)] = $variationId;
                }
            }
        }

        return $map;
    }

    protected function updateVendorProductStock(string $sellerSku, int $stock, ?string $productSid): ?array
    {
        $variationId = $productSid !== null ? trim($productSid) : '';

        // Prefer the cached variation id to avoid a catalog GET (and 429 rate limits).
        if ($variationId === '') {
            $catalogProduct = $this->findCatalogProductBySellerSku($sellerSku);

            if (! is_array($catalogProduct)) {
                throw new \RuntimeException('SKU « '.$sellerSku.' » introuvable dans le catalogue Jumia.');
            }

            $variationId = (string) ($this->resolveCatalogVariationId($catalogProduct, $sellerSku) ?? '');
        }

        if ($variationId === '') {
            throw new \RuntimeException('Aucune variation Jumia trouvée pour le SKU « '.$sellerSku.' ».');
        }

        $response = $this->updateVendorProductStockBatch([
            [
                'sellerSku' => $sellerSku,
                'stock' => $stock,
                'variationId' => $variationId,
            ],
        ]);

        Product::query()
            ->whereRaw('LOWER(ref) = ?', [strtolower($sellerSku)])
            ->update(['jumia_product_sid' => $variationId]);

        return $response;
    }

    /**
     * @param  array<int, array{sellerSku: string, stock: int, variationId?: ?string}>  $products
     */
    protected function updateVendorProductStockBatch(array $products): ?array
    {
        $entries = [];

        foreach ($products as $product) {
            $sellerSku = trim((string) ($product['sellerSku'] ?? ''));
            if ($sellerSku === '') {
                continue;
            }

            $stock = max(0, (int) ($product['stock'] ?? 0));
            $variationId = trim((string) ($product['variationId'] ?? ''));

            if ($variationId === '') {
                $catalogProduct = $this->findCatalogProductBySellerSku($sellerSku);

                if (! is_array($catalogProduct)) {
                    throw new \RuntimeException('SKU « '.$sellerSku.' » introuvable dans le catalogue Jumia.');
                }

                $variationId = (string) ($this->resolveCatalogVariationId($catalogProduct, $sellerSku) ?? '');

                if ($variationId === '') {
                    throw new \RuntimeException('Aucune variation Jumia trouvée pour le SKU « '.$sellerSku.' ».');
                }
            }

            $entries[] = [
                'id' => $variationId,
                'sellerSku' => $sellerSku,
                'stock' => $stock,
            ];
        }

        if ($entries === []) {
            return null;
        }

        return $this->vendorRequest('POST', '/feeds/products/stock', [], [
            'products' => $entries,
        ]);
    }

    /**
     * @param  array<string, mixed>  $catalogProduct
     */
    protected function extractStockFromCatalogProduct(array $catalogProduct, string $sellerSku): ?int
    {
        $sellerSku = trim($sellerSku);
        $variations = $catalogProduct['variations'] ?? [];

        if (is_array($variations)) {
            foreach ($variations as $variation) {
                if (! is_array($variation)) {
                    continue;
                }

                $variationSku = trim((string) ($variation['sellerSku'] ?? ''));

                if ($variationSku !== '' && strcasecmp($variationSku, $sellerSku) === 0) {
                    return $this->normalizeStockValue($variation);
                }
            }
        }

        return $this->normalizeStockValue($catalogProduct);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function normalizeStockValue(array $data): ?int
    {
        foreach (['stock', 'quantity', 'availableQuantity', 'available', 'sellableStock', 'Quantity', 'Available'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                return max(0, (int) $data[$field]);
            }
        }

        return null;
    }

    /**
     * @return array{stock: int, variation_id: ?string}|null
     */
    protected function getLegacyProductStock(string $sellerSku): ?array
    {
        $response = $this->legacyCall('GetProducts', [
            'Search' => $sellerSku,
            'Limit' => '25',
            'Offset' => '0',
        ]);

        $products = $this->extractList($response, 'Products', 'Product');

        foreach ($products as $product) {
            $sku = trim((string) ($product['SellerSku'] ?? ''));

            if ($sku !== '' && strcasecmp($sku, $sellerSku) === 0) {
                $stock = $this->normalizeStockValue($product);

                return [
                    'stock' => $stock ?? 0,
                    'variation_id' => null,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $catalogProduct
     */
    protected function resolveCatalogVariationId(array $catalogProduct, string $sellerSku): ?string
    {
        $sellerSku = trim($sellerSku);
        $variations = $catalogProduct['variations'] ?? [];

        if (is_array($variations)) {
            foreach ($variations as $variation) {
                if (! is_array($variation)) {
                    continue;
                }

                $variationSku = trim((string) ($variation['sellerSku'] ?? ''));

                if ($variationSku !== '' && strcasecmp($variationSku, $sellerSku) === 0) {
                    $id = $variation['id'] ?? $variation['productSid'] ?? $variation['sid'] ?? null;

                    return $id !== null ? (string) $id : null;
                }
            }
        }

        $fallbackId = $catalogProduct['productSid'] ?? $catalogProduct['id'] ?? $catalogProduct['sid'] ?? null;

        return $fallbackId !== null ? (string) $fallbackId : null;
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
                'POST' => $request->asJson()->post($url, $body ?? []),
                'PUT' => $request->asJson()->put($url, $body ?? []),
                'PATCH' => $request->asJson()->patch($url, $body ?? []),
                'DELETE' => $body !== null
                    ? $request->asJson()->delete($url, $body)
                    : $request->delete($url, $query),
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

        $entries = [];

        if (isset($response['items']) && is_array($response['items'])) {
            $entries = array_values(array_filter($response['items'], 'is_array'));
        } elseif (isset($response['orderItems']) && is_array($response['orderItems'])) {
            $entries = array_values(array_filter($response['orderItems'], 'is_array'));
        } elseif ($this->isAssoc($response) && isset($response['id'])) {
            $entries = [$response];
        } elseif (array_is_list($response)) {
            $entries = array_values(array_filter($response, 'is_array'));
        }

        return $this->flattenVendorOrderItems($entries);
    }

    /**
     * Vendor Center returns order groups that contain a nested `items` array.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    protected function flattenVendorOrderItems(array $entries): array
    {
        $flat = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (isset($entry['items']) && is_array($entry['items'])) {
                foreach ($entry['items'] as $lineItem) {
                    if (is_array($lineItem)) {
                        $flat[] = $lineItem;
                    }
                }

                continue;
            }

            $flat[] = $entry;
        }

        return array_values($flat);
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
