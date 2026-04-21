@extends('layouts.with-sidebar')

@section('title', 'Shopify Integration')

@section('sidebar_page_title', 'Shopify Integration')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-4">
                <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 mt-1 shrink-0">← Back</a>
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-xl bg-[#96BF48] flex items-center justify-center text-white font-bold text-lg shrink-0" aria-hidden="true">S</div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Shopify Integration</h1>
                        <p class="text-sm text-gray-600 mt-0.5">E-commerce Platform — Sync orders automatically via API</p>
                    </div>
                </div>
            </div>
            @php
                $isActive = old('enabled', $integration?->enabled ?? false);
            @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                {{ $isActive ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <!-- OAuth Credentials Configuration (Required First) -->
                @if(!$integration || !$integration->oauth_access_token)
                <div class="bg-white rounded-xl border border-amber-200 shadow-sm p-6 sm:p-8">
                    <div class="flex items-start mb-4">
                        <svg class="w-6 h-6 text-amber-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Step 1: Enter Your OAuth Credentials</h2>
                            <p class="text-sm text-gray-600 mt-1">Add your Shopify app's Client ID and Secret from your app settings</p>
                        </div>
                    </div>

                    <form action="{{ route('integrations.shopify.update') }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="integration_name" value="{{ old('integration_name', $integration?->integration_name ?? 'Shopify Store') }}">
                        <input type="hidden" name="api_version" value="{{ old('api_version', $integration?->api_version ?? '2024-01') }}">

                        <div>
                            <label for="oauth_client_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Client ID <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="oauth_client_id" id="oauth_client_id"
                                value="{{ old('oauth_client_id', $integration?->oauth_client_id ?? '') }}"
                                placeholder="41bc4eaef71d627965ded15a8ec82c9e"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48] font-mono text-sm">
                            <p class="mt-1 text-xs text-gray-500">From your Shopify app settings (ID client)</p>
                        </div>

                        <div>
                            <label for="oauth_client_secret" class="block text-sm font-medium text-gray-700 mb-1">
                                Client Secret <span class="text-red-500">*</span>
                            </label>
                            <input type="password" name="oauth_client_secret" id="oauth_client_secret" autocomplete="off"
                                placeholder="{{ $integration && $integration->oauth_client_secret ? '•••••••• (leave blank to keep current)' : 'Paste your client secret' }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48] font-mono text-sm">
                            <p class="mt-1 text-xs text-gray-500">From your Shopify app settings (click eye icon or "Faire pivoter" to reveal)</p>
                        </div>

                        <div class="rounded-lg bg-blue-50 border border-blue-100 p-4">
                            <p class="text-sm font-medium text-blue-900 mb-2">Where to Find These?</p>
                            <ol class="text-xs text-blue-800 space-y-1 list-decimal list-inside ml-2">
                                <li>Log in to your Shopify admin or Partner Dashboard</li>
                                <li>Go to Settings → Apps and sales channels → Develop apps</li>
                                <li>Click on your app → API credentials tab</li>
                                <li>Copy the <strong>Client ID</strong> and reveal the <strong>Client secret</strong></li>
                                <li>Paste them in the fields above and click Save</li>
                            </ol>
                        </div>

                        <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-[#96BF48] text-white text-sm font-medium rounded-lg hover:bg-[#7da03a] transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                            </svg>
                            Save OAuth Credentials
                        </button>
                    </form>
                </div>

                <!-- OAuth Installation (Primary Method) -->
                @if($integration && $integration->oauth_client_id && $integration->oauth_client_secret)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
                    <div class="flex items-start mb-4">
                        <svg class="w-6 h-6 text-green-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Step 2: Install App on Shopify</h2>
                            <p class="text-sm text-gray-600 mt-1">Connect your store using OAuth (one-click installation)</p>
                        </div>
                    </div>

                    <form action="{{ route('integrations.shopify.install') }}" method="GET" class="space-y-4">
                        <div>
                            <label for="shop" class="block text-sm font-medium text-gray-700 mb-1">Shop Domain <span class="text-red-500">*</span></label>
                            <input type="text" name="shop" id="shop" required
                                value="{{ old('shop', $integration?->shop_domain ?? '') }}"
                                placeholder="your-store.myshopify.com"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48]">
                            <p class="mt-1 text-xs text-gray-500">Enter your <strong>myshopify.com</strong> domain only — not your custom domain. Example: <code class="bg-gray-100 px-1 py-0.5 rounded">your-store.myshopify.com</code> (do not include <code class="bg-gray-100 px-1 py-0.5 rounded">https://</code>)</p>
                        </div>

                        <div class="rounded-lg bg-blue-50 border border-blue-100 p-4">
                            <p class="text-sm font-medium text-blue-900 mb-2">Important: Configure Redirect URL First</p>
                            <p class="text-xs text-blue-800 mb-2">In your Shopify app settings, add this OAuth redirect URL:</p>
                            <code class="block bg-blue-100 px-3 py-2 rounded text-xs break-all">{{ route('integrations.shopify.callback') }}</code>
                            <p class="text-xs text-blue-800 mt-2">Also ensure your app has these scopes: <code class="bg-blue-100 px-1 py-0.5 rounded">read_orders, read_products, read_customers</code></p>
                        </div>

                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition shadow-sm">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Install App on Shopify
                        </button>
                    </form>
                </div>
                @endif
                @endif

                <!-- Integration Settings (After OAuth or Manual) -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-6">
                        @if($integration && $integration->oauth_access_token)
                            Integration Settings
                        @else
                            Manual Configuration (Advanced)
                        @endif
                    </h2>

                    <form action="{{ route('integrations.shopify.update') }}" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="integration_name" class="block text-sm font-medium text-gray-700 mb-1">Integration Name</label>
                            <input type="text" name="integration_name" id="integration_name" required
                                value="{{ old('integration_name', $integration?->integration_name ?? 'Shopify Store') }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48]">
                        </div>

                        @if($integration && $integration->oauth_access_token)
                            <!-- OAuth Connected - Show Read-only Info -->
                            <div class="rounded-lg bg-green-50 border border-green-200 p-4">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-green-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-green-900">Connected via OAuth</p>
                                        <p class="text-xs text-green-800 mt-1">Shop: <strong>{{ $integration->shop_domain }}</strong></p>
                                        <p class="text-xs text-green-800">Scopes: <code class="bg-green-100 px-1 py-0.5 rounded">{{ $integration->oauth_scope }}</code></p>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="shop_name" value="{{ $integration->shop_name }}">
                        @else
                            <!-- Manual Configuration Fields -->
                            <div>
                                <label for="shop_name" class="block text-sm font-medium text-gray-700 mb-1">Shop Name <span class="text-red-500">*</span></label>
                                <input type="text" name="shop_name" id="shop_name"
                                    value="{{ old('shop_name', $integration?->shop_name ?? '') }}"
                                    placeholder="your-store"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48]">
                                <p class="mt-1 text-xs text-gray-500">Your Shopify store name (e.g., your-store from your-store.myshopify.com)</p>
                            </div>

                            <div>
                                <label for="api_access_token" class="block text-sm font-medium text-gray-700 mb-1">API Access Token</label>
                                <input type="password" name="api_access_token" id="api_access_token" autocomplete="off"
                                    placeholder="{{ $integration && $integration->api_access_token ? '•••••••• (leave blank to keep current)' : 'Paste your Admin API access token' }}"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48]">
                                <p class="mt-1 text-xs text-gray-500">Only needed if not using OAuth. Create a custom app in Shopify Admin to get this token.</p>
                            </div>
                        @endif

                        <div>
                            <label for="api_version" class="block text-sm font-medium text-gray-700 mb-1">API Version</label>
                            <input type="text" name="api_version" id="api_version"
                                value="{{ old('api_version', $integration?->api_version ?? '2024-01') }}"
                                placeholder="2024-01"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48]">
                            <p class="mt-1 text-xs text-gray-500">Shopify API version (e.g., 2024-01, 2024-04)</p>
                        </div>

                        <div class="rounded-lg bg-blue-50 border border-blue-100 p-4">
                            <p class="text-sm font-medium text-blue-900 mb-2">Required API Scopes</p>
                            <p class="text-xs text-blue-800 mb-2">When creating your custom app, make sure to grant these permissions:</p>
                            <ul class="text-xs text-blue-800 space-y-1 list-disc list-inside ml-2">
                                <li><code class="bg-blue-100 px-1 py-0.5 rounded">read_orders</code> - To fetch order data</li>
                                <li><code class="bg-blue-100 px-1 py-0.5 rounded">read_products</code> - To match products (optional)</li>
                                <li><code class="bg-blue-100 px-1 py-0.5 rounded">read_customers</code> - To sync customer info (optional)</li>
                            </ul>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="enabled" id="enabled" value="1"
                                class="rounded border-gray-300 text-[#96BF48] focus:ring-[#96BF48]"
                                @checked(old('enabled', $integration?->enabled ?? false))>
                            <label for="enabled" class="ml-2 text-sm text-gray-700">Enable this integration</label>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                                Update Integration
                            </button>
                        </div>
                    </form>

                    @php
                        $hasToken = $integration && ($integration->oauth_access_token || $integration->api_access_token);
                    @endphp

                    @if($integration && $integration->enabled && $hasToken)
                        <form action="{{ route('integrations.shopify.sync') }}" method="POST" class="mt-6 pt-6 border-t border-gray-100">
                            @csrf
                            <h3 class="text-sm font-semibold text-gray-900 mb-3">Manual Sync</h3>
                            <p class="text-sm text-gray-600 mb-4">Fetch orders from the last 7 days from Shopify now.</p>
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                Sync Orders Now
                            </button>
                        </form>
                    @endif

                    @if($integration)
                        <form action="{{ route('integrations.shopify.destroy') }}" method="POST" class="mt-4 pt-4 border-t border-gray-100"
                            onsubmit="return confirm('Delete this integration? You can set it up again later.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                                Delete
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">How to set up with OAuth</h3>
                    <ol class="list-decimal list-inside text-sm text-gray-600 space-y-2">
                        <li>Create a Shopify app in your Partner Dashboard or Admin</li>
                        <li>Add the OAuth redirect URL to your app settings</li>
                        <li>Configure the required API scopes</li>
                        <li>Add your Client ID and Secret to your <code>.env</code> file</li>
                        <li>Enter your shop domain above and click <strong>Install App</strong></li>
                        <li>Authorize the app on Shopify</li>
                        <li>You'll be redirected back and ready to sync!</li>
                    </ol>

                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs font-medium text-gray-700 mb-2">Environment Variables Needed:</p>
                        <pre class="text-xs bg-gray-100 text-gray-800 p-2 rounded overflow-x-auto"><code>SHOPIFY_CLIENT_ID=your_client_id
SHOPIFY_CLIENT_SECRET=your_secret
SHOPIFY_API_VERSION=2024-01
SHOPIFY_SCOPES=read_orders,read_products</code></pre>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Status</h3>
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Last Sync</dt>
                            <dd class="text-gray-900 font-medium">
                                {{ $integration?->last_sync_at ? $integration->last_sync_at->format('M j, Y g:i A') : 'Never' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Created</dt>
                            <dd class="text-gray-900 font-medium">
                                {{ $integration?->created_at ? $integration->created_at->format('n/j/Y, g:i A') : '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-green-200 bg-green-50 p-6">
                    <h3 class="text-sm font-semibold text-green-900 mb-3">OAuth Integration Benefits</h3>
                    <ul class="text-sm text-green-900 space-y-2 list-disc list-inside">
                        <li>Secure OAuth 2.0 authentication</li>
                        <li>No manual token management required</li>
                        <li>Pull orders on demand from Shopify</li>
                        <li>Match Shopify variant SKU to your product <strong>ref</strong> field</li>
                        <li>Sync historical orders anytime</li>
                        <li>Automatic scheduled syncing (via cron/scheduler)</li>
                        <li>Easy app installation process</li>
                    </ul>
                    <a href="https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens/authorization-code-grant" target="_blank" rel="noopener noreferrer"
                        class="mt-4 inline-block text-sm font-medium text-green-800 hover:text-green-950 underline">
                        View Shopify OAuth Docs →
                    </a>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-6">
                    <h3 class="text-sm font-semibold text-amber-900 mb-3">Automated Sync (Optional)</h3>
                    <p class="text-sm text-amber-900 mb-2">To automatically sync orders every hour, add this to your scheduler:</p>
                    <pre class="text-xs bg-amber-100 text-amber-900 p-2 rounded mt-2 overflow-x-auto"><code>$schedule->command('shopify:sync-orders')->hourly();</code></pre>
                    <p class="text-xs text-amber-800 mt-2">Add this in <code>app/Console/Kernel.php</code> or <code>routes/console.php</code></p>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-auto border-t border-gray-200 bg-white py-6 px-4 text-center text-sm text-gray-500">
        <p>© {{ date('Y') }}. Tous droits réservés.</p>
        <p class="mt-1">Conçu pour une meilleure gestion.</p>
    </footer>
</main>

@endsection
