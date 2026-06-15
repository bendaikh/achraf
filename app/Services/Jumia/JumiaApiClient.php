<?php

namespace App\Services\Jumia;

use App\Models\JumiaIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JumiaApiClient
{
    public function __construct(
        protected JumiaIntegration $integration
    ) {}

    public function testConnection(): bool
    {
        $response = $this->call('GetOrders', ['Limit' => '1', 'Offset' => '0']);

        return $response !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(array $filters = []): array
    {
        $params = array_merge([
            'Limit' => '100',
            'Offset' => '0',
        ], $filters);

        $response = $this->call('GetOrders', $params);

        return $this->extractList($response, 'Orders', 'Order');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllOrders(array $filters = []): \Generator
    {
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
        $response = $this->call('GetOrderItems', ['OrderId' => $orderId]);

        return $this->extractList($response, 'OrderItems', 'OrderItem');
    }

    public function setStatusToReadyToShip(string $orderItemIds): ?array
    {
        return $this->call('SetStatusToReadyToShip', [], $this->orderItemsXml($orderItemIds));
    }

    public function setStatusToShipped(string $orderItemIds, string $shippingProvider, ?string $trackingNumber = null): ?array
    {
        $extra = ['ShippingProvider' => $shippingProvider];
        if ($trackingNumber) {
            $extra['TrackingNumber'] = $trackingNumber;
        }

        return $this->call('SetStatusToShipped', $extra, $this->orderItemsXml($orderItemIds));
    }

    public function setStatusToDelivered(string $orderItemIds): ?array
    {
        return $this->call('SetStatusToDelivered', [], $this->orderItemsXml($orderItemIds));
    }

    public function setStatusToCanceled(string $orderItemIds, string $reason): ?array
    {
        return $this->call('SetStatusToCanceled', ['Reason' => $reason], $this->orderItemsXml($orderItemIds));
    }

    protected function call(string $action, array $extraParams = [], ?string $xmlBody = null): ?array
    {
        if (! $this->integration->isConfigured()) {
            throw new \RuntimeException('Jumia integration is not configured.');
        }

        $params = array_merge([
            'Action' => $action,
            'Format' => 'JSON',
            'Timestamp' => now()->format('c'),
            'UserID' => $this->integration->user_id,
            'Version' => $this->integration->api_version ?? '1.0',
        ], $extraParams);

        $signature = $this->sign($params);
        $params['Signature'] = $signature;

        $url = rtrim($this->integration->api_base_url, '/');

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

    protected function sign(array $params): string
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
