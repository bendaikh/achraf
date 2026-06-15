<?php

namespace App\Http\Controllers;

use App\Models\JumiaIntegration;
use App\Services\Jumia\JumiaApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class JumiaIntegrationController extends Controller
{
    public function edit(): View
    {
        return view('integrations.jumia', [
            'integration' => JumiaIntegration::query()->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'integration_name' => 'required|string|max:255',
            'api_base_url' => 'required|url|max:500',
            'user_id' => 'required|email|max:255',
            'api_key' => 'nullable|string|max:2000',
            'api_version' => 'nullable|string|max:10',
            'enabled' => 'sometimes|boolean',
        ]);

        $integration = JumiaIntegration::query()->first() ?? new JumiaIntegration;

        $integration->fill([
            'integration_name' => $validated['integration_name'],
            'api_base_url' => rtrim($validated['api_base_url'], '/'),
            'user_id' => $validated['user_id'],
            'api_version' => $validated['api_version'] ?? '1.0',
            'enabled' => $request->boolean('enabled'),
        ]);

        if ($request->filled('api_key')) {
            $integration->api_key = $validated['api_key'];
        } elseif (! $integration->exists) {
            return redirect()
                ->route('integrations.jumia.edit')
                ->withErrors(['api_key' => 'API key is required for a new integration.']);
        }

        $integration->save();

        if ($integration->enabled && $integration->isConfigured()) {
            try {
                $client = new JumiaApiClient($integration);
                if (! $client->testConnection()) {
                    $integration->update(['last_error' => 'Connection test failed after save.']);

                    return redirect()
                        ->route('integrations.jumia.edit')
                        ->with('error', 'Settings saved but the Jumia API connection test failed. Verify your API URL, User ID, and API key.');
                }

                $integration->update(['last_error' => null]);
            } catch (\Throwable $e) {
                $integration->update(['last_error' => $e->getMessage()]);

                return redirect()
                    ->route('integrations.jumia.edit')
                    ->with('error', 'Settings saved but connection test failed: '.$e->getMessage());
            }
        }

        return redirect()
            ->route('integrations.jumia.edit')
            ->with('success', 'Jumia integration updated successfully.');
    }

    public function test(): RedirectResponse
    {
        $integration = JumiaIntegration::query()->first();

        if (! $integration || ! $integration->isConfigured()) {
            return redirect()
                ->route('integrations.jumia.edit')
                ->with('error', 'Configure API URL, User ID, and API key first.');
        }

        try {
            $client = new JumiaApiClient($integration);
            if ($client->testConnection()) {
                $integration->update(['last_error' => null]);

                return redirect()
                    ->route('integrations.jumia.edit')
                    ->with('success', 'Connection to Jumia API successful.');
            }

            $integration->update(['last_error' => 'Connection test returned no data.']);

            return redirect()
                ->route('integrations.jumia.edit')
                ->with('error', 'Connection test failed. No data returned from Jumia API.');
        } catch (\Throwable $e) {
            $integration->update(['last_error' => $e->getMessage()]);

            return redirect()
                ->route('integrations.jumia.edit')
                ->with('error', 'Connection test failed: '.$e->getMessage());
        }
    }

    public function sync(): RedirectResponse
    {
        $integration = JumiaIntegration::query()->first();

        if (! $integration || ! $integration->enabled) {
            return redirect()
                ->route('integrations.jumia.edit')
                ->with('error', 'Jumia integration is not enabled.');
        }

        if (! $integration->isConfigured()) {
            return redirect()
                ->route('integrations.jumia.edit')
                ->with('error', 'Jumia API credentials are not configured.');
        }

        try {
            Artisan::call('jumia:sync-orders', ['--days' => 30]);

            return redirect()
                ->route('integrations.jumia.edit')
                ->with('success', 'Jumia order sync completed. '.trim(Artisan::output()));
        } catch (\Throwable $e) {
            return redirect()
                ->route('integrations.jumia.edit')
                ->with('error', 'Sync failed: '.$e->getMessage());
        }
    }

    public function destroy(): RedirectResponse
    {
        JumiaIntegration::query()->first()?->delete();

        return redirect()
            ->route('integrations.jumia.edit')
            ->with('success', 'Jumia integration removed.');
    }
}
