<?php

namespace App\Http\Controllers;

use App\Models\ShopifyIntegration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopifyIntegrationController extends Controller
{
    public function edit(): View
    {
        $integration = ShopifyIntegration::query()->first();

        return view('integrations.shopify', [
            'integration' => $integration,
            'webhookUrl' => url('/api/webhooks/shopify/orders/create'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'integration_name' => 'required|string|max:255',
            'shop_name' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:2000',
            'enabled' => 'sometimes|boolean',
        ]);

        $integration = ShopifyIntegration::query()->first() ?? new ShopifyIntegration;

        $integration->integration_name = $validated['integration_name'];
        $integration->shop_name = $validated['shop_name'] ?? null;
        $integration->enabled = $request->boolean('enabled');

        if ($request->filled('webhook_secret')) {
            $integration->webhook_secret = $validated['webhook_secret'];
        }

        $integration->save();

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
}
