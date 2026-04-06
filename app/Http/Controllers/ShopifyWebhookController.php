<?php

namespace App\Http\Controllers;

use App\Models\ShopifyIntegration;
use App\Services\ShopifyOrderImporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function ordersCreate(Request $request, ShopifyOrderImporter $importer): Response
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration || ! $integration->enabled) {
            return response('Integration disabled', 401);
        }

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

        $order = json_decode($raw, true);
        if (! is_array($order)) {
            return response('Invalid payload', 400);
        }

        try {
            $importer->import($order);
            $integration->forceFill(['last_sync_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::error('Shopify order import failed: '.$e->getMessage(), ['exception' => $e]);

            return response('Processing error', 500);
        }

        return response('OK', 200);
    }
}
