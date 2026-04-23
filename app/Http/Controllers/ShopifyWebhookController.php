<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopifyIntegration;
use App\Services\ShopifyOrderImporter;
use App\Services\ShopifyProductImporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    /**
     * Verify the webhook HMAC signature
     */
    private function verifyWebhook(Request $request, ShopifyIntegration $integration): ?Response
    {
        $secret = $integration->webhook_secret;
        if ($secret === null || $secret === '') {
            return response('Webhook secret not configured', 401);
        }

        $raw = $request->getContent();
        $hmacHeader = (string) $request->header('X-Shopify-Hmac-Sha256', '');

        $calculated = base64_encode(hash_hmac('sha256', $raw, $secret, true));

        if ($hmacHeader === '' || ! hash_equals($calculated, $hmacHeader)) {
            Log::warning('Shopify webhook HMAC verification failed.');
            return response('Unauthorized', 401);
        }

        return null;
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
            $importer->import($order);
            $integration->forceFill(['last_sync_at' => now()])->save();
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
}
