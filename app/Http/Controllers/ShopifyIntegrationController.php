<?php

namespace App\Http\Controllers;

use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'integration_name' => 'required|string|max:255',
            'shop_name' => 'required|string|max:255',
            'api_access_token' => 'nullable|string|max:2000',
            'api_version' => 'nullable|string|max:50',
            'enabled' => 'sometimes|boolean',
        ]);

        $integration = ShopifyIntegration::query()->first() ?? new ShopifyIntegration;

        $integration->integration_name = $validated['integration_name'];
        $integration->shop_name = $validated['shop_name'];
        $integration->api_version = $validated['api_version'] ?? '2024-01';
        $integration->enabled = $request->boolean('enabled');

        if ($request->filled('api_access_token')) {
            $integration->api_access_token = $validated['api_access_token'];
        }

        $integration->save();

        if ($integration->enabled && $integration->api_access_token && $integration->shop_name) {
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

        if (! $integration->api_access_token || ! $integration->shop_name) {
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

