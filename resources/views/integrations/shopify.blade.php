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
                        <p class="text-sm text-gray-600 mt-0.5">E-commerce Platform — Receive orders automatically via webhooks</p>
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
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-6">Webhook settings</h2>

                    <form action="{{ route('integrations.shopify.update') }}" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="integration_name" class="block text-sm font-medium text-gray-700 mb-1">Integration Name</label>
                            <input type="text" name="integration_name" id="integration_name" required
                                value="{{ old('integration_name', $integration?->integration_name ?? 'Shopify Store') }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48]">
                        </div>

                        <div>
                            <label for="shop_name" class="block text-sm font-medium text-gray-700 mb-1">Shop Name (Optional)</label>
                            <input type="text" name="shop_name" id="shop_name"
                                value="{{ old('shop_name', $integration?->shop_name ?? '') }}"
                                placeholder="your-store"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48]">
                            <p class="mt-1 text-xs text-gray-500">Your Shopify store name (e.g., your-store from your-store.myshopify.com)</p>
                        </div>

                        <div>
                            <label for="webhook_secret" class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret</label>
                            <input type="password" name="webhook_secret" id="webhook_secret" autocomplete="off"
                                placeholder="{{ $integration && $integration->webhook_secret ? '•••••••• (leave blank to keep current)' : 'Paste secret from Shopify' }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#96BF48] focus:ring-[#96BF48]">
                            <p class="mt-1 text-xs text-gray-500">Get this from your Shopify Admin → Settings → Notifications → Webhooks</p>
                        </div>

                        <div class="rounded-lg bg-sky-50 border border-sky-100 p-4">
                            <p class="text-sm font-medium text-sky-900 mb-2">Your Webhook URL</p>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input type="text" readonly id="webhook-url" value="{{ $webhookUrl }}"
                                    class="flex-1 rounded-lg border-sky-200 bg-white text-sm font-mono text-gray-800">
                                <button type="button" id="copy-webhook-url"
                                    class="shrink-0 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                    Copy
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-sky-800">Use this URL when creating the webhook in Shopify. Subscribe to <strong>Order creation</strong> events.</p>
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
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">How it works</h3>
                    <ol class="list-decimal list-inside text-sm text-gray-600 space-y-2">
                        <li>Copy your webhook URL from the form.</li>
                        <li>Go to Shopify Admin → Settings → Notifications → Webhooks.</li>
                        <li>Create a new webhook for <strong>Order creation</strong> events.</li>
                        <li>Paste the webhook URL and save.</li>
                        <li>Copy the webhook secret from Shopify and paste it in the form above.</li>
                    </ol>
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
                    <h3 class="text-sm font-semibold text-green-900 mb-3">Need Help?</h3>
                    <ul class="text-sm text-green-900 space-y-2 list-disc list-inside">
                        <li>No API token needed — webhooks only!</li>
                        <li>Match Shopify variant SKU to your product <strong>ref</strong> field for stock links.</li>
                        <li>Orders are automatically pushed by Shopify.</li>
                        <li>Webhook secret verifies authenticity.</li>
                        <li>Real-time order synchronization.</li>
                        <li>Secure and reliable integration.</li>
                    </ul>
                    <a href="https://shopify.dev/docs/apps/webhooks/configuration/https" target="_blank" rel="noopener noreferrer"
                        class="mt-4 inline-block text-sm font-medium text-green-800 hover:text-green-950 underline">
                        View Shopify Webhook Docs →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-auto border-t border-gray-200 bg-white py-6 px-4 text-center text-sm text-gray-500">
        <p>© {{ date('Y') }}. Tous droits réservés.</p>
        <p class="mt-1">Conçu pour une meilleure gestion.</p>
    </footer>
</main>

@push('scripts')
<script>
document.getElementById('copy-webhook-url')?.addEventListener('click', function () {
    var el = document.getElementById('webhook-url');
    if (!el) return;
    el.select();
    el.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(el.value).then(function () {
        var btn = document.getElementById('copy-webhook-url');
        if (!btn) return;
        var t = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = t; }, 2000);
    });
});
</script>
@endpush
@endsection
