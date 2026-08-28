<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopifyIntegration;
use App\Services\MarketplaceStockSyncService;
use App\Services\ShopifyApiClient;
use App\Services\ShopifyFulfillmentSyncService;
use App\Services\ShopifyInventorySyncService;
use App\Services\ShopifyOrderImporter;
use App\Services\ShopifyProductImporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    /**
     * Verify the webhook HMAC signature
     *
     * Shopify signs webhooks with the app's API secret key. For webhooks registered
     * via the Admin API (programmatic), this is the oauth_client_secret. For webhooks
     * registered manually in the Shopify admin, a separate webhook_secret may be used.
     * We try all available secrets for maximum compatibility.
     */
    private function verifyWebhook(Request $request, ShopifyIntegration $integration): ?Response
    {
        $raw = $request->getContent();
        $hmacHeader = (string) $request->header('X-Shopify-Hmac-Sha256', '');

        if ($hmacHeader === '') {
            Log::warning('Shopify webhook missing HMAC header.');

            return response('Unauthorized', 401);
        }

        // Collect all possible secrets to try
        $secrets = array_filter([
            $integration->oauth_client_secret,
            $integration->webhook_secret,
        ]);

        if (empty($secrets)) {
            Log::error('No Shopify secret configured for webhook verification.');

            return response('Webhook secret not configured', 401);
        }

        foreach ($secrets as $secret) {
            $calculated = base64_encode(hash_hmac('sha256', $raw, $secret, true));
            if (hash_equals($calculated, $hmacHeader)) {
                return null;
            }
        }

        Log::warning('Shopify webhook HMAC verification failed.', [
            'hmac_header' => substr($hmacHeader, 0, 10).'...',
            'secrets_tried' => count($secrets),
        ]);

        return response('Unauthorized', 401);
    }

    /**
     * Handle orders/create webhook from Shopify
     */
    public function ordersCreate(Request $request, ShopifyOrderImporter $importer): Response
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration || ! $integration->enabled) {
            return response('Integration disabled', 401);
        }

        $verifyError = $this->verifyWebhook($request, $integration);
        if ($verifyError) {
            return $verifyError;
        }

        $order = json_decode($request->getContent(), true);
        if (! is_array($order)) {
            return response('Invalid payload', 400);
        }

        try {
            // Re-fetch from Admin API so we never apply a stale/out-of-order webhook body
            // (Shopify can deliver delayed updates after a newer refund/fulfillment state).
            try {
                $fresh = (new ShopifyApiClient($integration))->getOrder((string) ($order['id'] ?? ''));
                if (is_array($fresh) && ! empty($fresh['id'])) {
                    $order = $fresh;
                }
            } catch (\Throwable $fetchError) {
                Log::warning('Shopify webhook could not refresh order from API; using payload', [
                    'order_id' => $order['id'] ?? 'unknown',
                    'error' => $fetchError->getMessage(),
                ]);
            }

            $importer->import($order);
            // Do not advance last_sync_at here — webhooks would push the cron cursor past
            // older modified orders and permanently prevent API recovery.
            Log::info('Shopify order webhook processed', ['order_id' => $order['id'] ?? 'unknown']);
        } catch (\Throwable $e) {
            Log::error('Shopify order import failed: '.$e->getMessage(), ['exception' => $e]);

            return response('Processing error', 500);
        }

        return response('OK', 200);
    }

    /**
     * Handle orders/updated webhook from Shopify
     */
    public function ordersUpdated(Request $request, ShopifyOrderImporter $importer): Response
    {
        return $this->ordersCreate($request, $importer);
    }

    /**
     * Handle products/create webhook from Shopify
     */
    public function productsCreate(Request $request, ShopifyProductImporter $importer): Response
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration || ! $integration->enabled) {
            return response('Integration disabled', 401);
        }

        $verifyError = $this->verifyWebhook($request, $integration);
        if ($verifyError) {
            return $verifyError;
        }

        $product = json_decode($request->getContent(), true);
        if (! is_array($product)) {
            return response('Invalid payload', 400);
        }

        try {
            $importer->import($product);
            Log::info('Shopify product webhook processed (create)', ['product_id' => $product['id'] ?? 'unknown']);
        } catch (\Throwable $e) {
            Log::error('Shopify product import failed: '.$e->getMessage(), ['exception' => $e]);

            return response('Processing error', 500);
        }

        return response('OK', 200);
    }

    /**
     * Handle products/update webhook from Shopify
     */
    public function productsUpdate(Request $request, ShopifyProductImporter $importer): Response
    {
        return $this->productsCreate($request, $importer);
    }

    /**
     * Handle products/delete webhook from Shopify
     */
    public function productsDelete(Request $request): Response
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration || ! $integration->enabled) {
            return response('Integration disabled', 401);
        }

        $verifyError = $this->verifyWebhook($request, $integration);
        if ($verifyError) {
            return $verifyError;
        }

        $product = json_decode($request->getContent(), true);
        if (! is_array($product) || empty($product['id'])) {
            return response('Invalid payload', 400);
        }

        try {
            $externalId = (string) $product['id'];
            $deleted = Product::query()
                ->where('source', 'shopify')
                ->where('external_id', $externalId)
                ->update(['status' => 'Desactiver', 'shopify_status' => 'deleted']);

            Log::info('Shopify product webhook processed (delete)', [
                'product_id' => $externalId,
                'records_updated' => $deleted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Shopify product delete failed: '.$e->getMessage(), ['exception' => $e]);

            return response('Processing error', 500);
        }

        return response('OK', 200);
    }

    /**
     * Handle inventory_levels/update webhook from Shopify.
     */
    public function inventoryLevelsUpdate(
        Request $request,
        ShopifyInventorySyncService $inventorySync,
        MarketplaceStockSyncService $marketplaceStockSync
    ): Response {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration || ! $integration->enabled) {
            return response('Integration disabled', 401);
        }

        $verifyError = $this->verifyWebhook($request, $integration);
        if ($verifyError) {
            return $verifyError;
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload) || empty($payload['inventory_item_id'])) {
            return response('Invalid payload', 400);
        }

        try {
            $product = $inventorySync->applyInventoryLevelUpdate(
                (string) $payload['inventory_item_id'],
                (string) ($payload['location_id'] ?? ''),
                (int) ($payload['available'] ?? 0)
            );

            if ($product && filled($product->jumia_product_sid)) {
                $marketplaceStockSync->pushProductStockToJumia($product);
            }

            Log::info('Shopify inventory webhook processed', [
                'inventory_item_id' => $payload['inventory_item_id'] ?? null,
                'available' => $payload['available'] ?? null,
                'product_id' => $product?->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Shopify inventory webhook failed: '.$e->getMessage(), ['exception' => $e]);

            return response('Processing error', 500);
        }

        return response('OK', 200);
    }

    /**
     * Handle refunds/create — re-fetch and re-import the parent order so totals/lines update.
     */
    public function refundsCreate(Request $request, ShopifyOrderImporter $importer): Response
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration || ! $integration->enabled) {
            return response('Integration disabled', 401);
        }

        $verifyError = $this->verifyWebhook($request, $integration);
        if ($verifyError) {
            return $verifyError;
        }

        $refund = json_decode($request->getContent(), true);
        if (! is_array($refund)) {
            return response('Invalid payload', 400);
        }

        $orderId = (string) ($refund['order_id'] ?? '');
        if ($orderId === '') {
            return response('Missing order_id', 400);
        }

        try {
            $order = (new ShopifyApiClient($integration))->getOrder($orderId);
            if (! is_array($order) || empty($order['id'])) {
                Log::warning('Shopify refund webhook: order not found via API', [
                    'order_id' => $orderId,
                    'refund_id' => $refund['id'] ?? null,
                ]);

                return response('Order not found', 404);
            }

            $importer->import($order);
            Log::info('Shopify refund webhook processed — order re-imported', [
                'order_id' => $orderId,
                'refund_id' => $refund['id'] ?? null,
                'current_total_price' => $order['current_total_price'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Shopify refund webhook failed: '.$e->getMessage(), ['exception' => $e]);

            return response('Processing error', 500);
        }

        return response('OK', 200);
    }

    /**
     * Handle fulfillments/create webhook from Shopify
     */
    public function fulfillmentsCreate(Request $request, ShopifyFulfillmentSyncService $sync): Response
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration || ! $integration->enabled) {
            return response('Integration disabled', 401);
        }

        $verifyError = $this->verifyWebhook($request, $integration);
        if ($verifyError) {
            return $verifyError;
        }

        $fulfillment = json_decode($request->getContent(), true);
        if (! is_array($fulfillment)) {
            return response('Invalid payload', 400);
        }

        try {
            // Fulfillments can coincide with refunds/edits — refresh full order totals too.
            $orderId = (string) ($fulfillment['order_id'] ?? '');
            if ($orderId !== '') {
                try {
                    $order = (new ShopifyApiClient($integration))->getOrder($orderId);
                    if (is_array($order) && ! empty($order['id'])) {
                        app(ShopifyOrderImporter::class)->import($order);
                    }
                } catch (\Throwable $orderRefreshError) {
                    Log::warning('Shopify fulfillment webhook: order refresh failed', [
                        'order_id' => $orderId,
                        'error' => $orderRefreshError->getMessage(),
                    ]);
                }
            }

            $sync->syncFromFulfillmentWebhook($fulfillment);
            Log::info('Shopify fulfillment webhook processed', [
                'fulfillment_id' => $fulfillment['id'] ?? 'unknown',
                'order_id' => $fulfillment['order_id'] ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            Log::error('Shopify fulfillment sync failed: '.$e->getMessage(), ['exception' => $e]);

            return response('Processing error', 500);
        }

        return response('OK', 200);
    }

    /**
     * Handle fulfillments/update webhook from Shopify
     */
    public function fulfillmentsUpdate(Request $request, ShopifyFulfillmentSyncService $sync): Response
    {
        return $this->fulfillmentsCreate($request, $sync);
    }
}
