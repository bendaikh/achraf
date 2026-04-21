<?php

namespace App\Http\Controllers;

use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopifyIntegrationController extends Controller
{
    public function edit(): View
    {
        $integration = ShopifyIntegration::query()->first();

        return view('integrations.shopify', [
            'integration' => $integration,
        ]);
    }

    /**
     * Initiate OAuth flow with Shopify
     */
    public function install(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop' => 'required|string|max:255',
        ]);

        $shop = $this->normalizeShopDomain($validated['shop']);

        if (!$shop) {
            return redirect()
                ->route('integrations.shopify.edit')
                ->with('error', 'Invalid shop domain. Please enter your Shopify store domain (e.g., your-store.myshopify.com).');
        }

        $integration = ShopifyIntegration::query()->first();
        
        // Check if client credentials are configured
        $clientId = $integration?->oauth_client_id ?? config('services.shopify.client_id');
        
        if (!$clientId) {
            return redirect()
                ->route('integrations.shopify.edit')
                ->with('error', 'OAuth Client ID is not configured. Please enter your Client ID first.');
        }

        $scopes = config('services.shopify.scopes', 'read_orders,read_products,read_customers');
        $redirectUri = route('integrations.shopify.callback');
        $state = Str::random(40);

        // Store state temporarily
        if (!$integration) {
            $integration = new ShopifyIntegration;
        }
        $integration->shop_domain = $shop;
        $integration->oauth_state = $state;
        $integration->save();

        // Build OAuth URL
        $authUrl = sprintf(
            'https://%s/admin/oauth/authorize?client_id=%s&scope=%s&redirect_uri=%s&state=%s',
            $shop,
            $clientId,
            $scopes,
            urlencode($redirectUri),
            $state
        );

        return redirect($authUrl);
    }

    /**
     * Handle OAuth callback from Shopify
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        $shop = $request->input('shop');
        $state = $request->input('state');

        // Validate required parameters
        if (!$code || !$shop || !$state) {
            return redirect()
                ->route('integrations.shopify.edit')
                ->with('error', 'Invalid OAuth callback parameters.');
        }

        // Verify state to prevent CSRF
        $integration = ShopifyIntegration::query()->first();
        if (!$integration || $integration->oauth_state !== $state) {
            return redirect()
                ->route('integrations.shopify.edit')
                ->with('error', 'Invalid OAuth state. Please try again.');
        }

        // Verify HMAC signature
        if (!$this->verifyHmac($request->all(), $integration)) {
            return redirect()
                ->route('integrations.shopify.edit')
                ->with('error', 'Invalid HMAC signature.');
        }

        // Exchange code for access token
        try {
            $clientId = $integration->oauth_client_id ?? config('services.shopify.client_id');
            $clientSecret = $integration->oauth_client_secret ?? config('services.shopify.client_secret');
            
            if (!$clientId || !$clientSecret) {
                return redirect()
                    ->route('integrations.shopify.edit')
                    ->with('error', 'OAuth credentials not configured. Please enter your Client ID and Secret.');
            }

            $response = Http::post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
            ]);

            if ($response->failed()) {
                Log::error('Shopify OAuth token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return redirect()
                    ->route('integrations.shopify.edit')
                    ->with('error', 'Failed to obtain access token from Shopify.');
            }

            $data = $response->json();
            $accessToken = $data['access_token'];
            $scope = $data['scope'];

            // Update integration with OAuth token
            $integration->oauth_access_token = $accessToken;
            $integration->oauth_scope = $scope;
            $integration->oauth_state = null; // Clear state
            $integration->enabled = true;
            
            // Extract shop name from domain
            $shopName = str_replace('.myshopify.com', '', $shop);
            $integration->shop_name = $shopName;
            $integration->shop_domain = $shop;
            
            if (!$integration->integration_name) {
                $integration->integration_name = ucfirst($shopName) . ' Store';
            }
            
            if (!$integration->api_version) {
                $integration->api_version = config('services.shopify.api_version', '2024-01');
            }

            $integration->save();

            return redirect()
                ->route('integrations.shopify.edit')
                ->with('success', 'Successfully connected to Shopify! Your store is now integrated.');
        } catch (\Exception $e) {
            Log::error('Shopify OAuth callback exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('integrations.shopify.edit')
                ->with('error', 'An error occurred during OAuth: ' . $e->getMessage());
        }
    }

    /**
     * Normalize the Shopify shop domain to the canonical format: store.myshopify.com
     * Handles various user inputs like:
     *  - "store"
     *  - "store.myshopify.com"
     *  - "https://store.myshopify.com"
     *  - "https://store.myshopify.com/admin"
     *  - "my-store.com" (custom domains → require .myshopify.com)
     */
    protected function normalizeShopDomain(string $input): ?string
    {
        $shop = trim($input);

        // Remove protocol (http:// or https://)
        $shop = preg_replace('#^https?://#i', '', $shop);

        // Remove trailing slashes and any path (e.g., /admin)
        $shop = strtok($shop, '/');

        // Remove any port
        $shop = strtok($shop, ':');

        $shop = strtolower(trim((string) $shop));

        if ($shop === '' || $shop === false) {
            return null;
        }

        // If user entered just the store name, append .myshopify.com
        if (!str_contains($shop, '.myshopify.com')) {
            // If it contains dots, it might be a custom domain - strip them and try
            if (str_contains($shop, '.')) {
                // Take only the first segment (e.g., "fasttuningcar.com" → "fasttuningcar")
                $parts = explode('.', $shop);
                $shop = $parts[0];
            }
            $shop = $shop . '.myshopify.com';
        }

        // Validate format: must be something.myshopify.com
        if (!preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/i', $shop)) {
            return null;
        }

        return $shop;
    }

    /**
     * Verify HMAC signature from Shopify
     */
    protected function verifyHmac(array $params, ShopifyIntegration $integration): bool
    {
        $hmac = $params['hmac'] ?? '';
        unset($params['hmac']);

        // Build query string
        ksort($params);
        $queryString = http_build_query($params);

        // Calculate expected HMAC
        $clientSecret = $integration->oauth_client_secret ?? config('services.shopify.client_secret');
        
        if (!$clientSecret) {
            return false;
        }
        
        $calculatedHmac = hash_hmac('sha256', $queryString, $clientSecret);

        return hash_equals($calculatedHmac, $hmac);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'integration_name' => 'required|string|max:255',
            'shop_name' => 'nullable|string|max:255',
            'oauth_client_id' => 'nullable|string|max:500',
            'oauth_client_secret' => 'nullable|string|max:500',
            'api_access_token' => 'nullable|string|max:2000',
            'api_version' => 'nullable|string|max:50',
            'enabled' => 'sometimes|boolean',
        ]);

        $integration = ShopifyIntegration::query()->first() ?? new ShopifyIntegration;

        $integration->integration_name = $validated['integration_name'];
        
        if ($request->filled('shop_name')) {
            $integration->shop_name = $validated['shop_name'];
        }
        
        // Save OAuth credentials if provided
        if ($request->filled('oauth_client_id')) {
            $integration->oauth_client_id = $validated['oauth_client_id'];
        }
        
        if ($request->filled('oauth_client_secret')) {
            $integration->oauth_client_secret = $validated['oauth_client_secret'];
        }
        
        $integration->api_version = $validated['api_version'] ?? '2024-01';
        $integration->enabled = $request->boolean('enabled');

        // Only allow manual token if OAuth token doesn't exist (backward compatibility)
        if ($request->filled('api_access_token') && !$integration->oauth_access_token) {
            $integration->api_access_token = $validated['api_access_token'];
        }

        $integration->save();

        // Test connection if enabled
        $token = $integration->oauth_access_token ?? $integration->api_access_token;
        if ($integration->enabled && $token && $integration->shop_name) {
            try {
                $client = new ShopifyApiClient($integration);
                if (! $client->testConnection()) {
                    return redirect()
                        ->route('integrations.shopify.edit')
                        ->with('error', 'Integration saved but failed to connect to Shopify API. Please verify your credentials.');
                }
            } catch (\Exception $e) {
                return redirect()
                    ->route('integrations.shopify.edit')
                    ->with('error', 'Integration saved but connection test failed: '.$e->getMessage());
            }
        }

        return redirect()
            ->route('integrations.shopify.edit')
            ->with('success', 'Integration updated successfully.');
    }

    public function destroy(): RedirectResponse
    {
        ShopifyIntegration::query()->first()?->delete();

        return redirect()
            ->route('integrations.shopify.edit')
            ->with('success', 'Integration removed.');
    }

    public function sync(): RedirectResponse
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration || ! $integration->enabled) {
            return redirect()
                ->route('integrations.shopify.edit')
                ->with('error', 'Integration is not enabled.');
        }

        $token = $integration->oauth_access_token ?? $integration->api_access_token;
        if (! $token || ! $integration->shop_name) {
            return redirect()
                ->route('integrations.shopify.edit')
                ->with('error', 'API credentials not configured.');
        }

        try {
            Artisan::call('shopify:sync-orders', [
                '--days' => 7,
                '--limit' => 50,
            ]);

            $output = Artisan::output();

            return redirect()
                ->route('integrations.shopify.edit')
                ->with('success', 'Order sync initiated successfully. Check the logs for details.');
        } catch (\Exception $e) {
            return redirect()
                ->route('integrations.shopify.edit')
                ->with('error', 'Sync failed: '.$e->getMessage());
        }
    }
}

