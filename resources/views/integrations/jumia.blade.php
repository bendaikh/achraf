@extends('layouts.with-sidebar')

@section('title', 'Jumia Integration')

@section('sidebar_page_title', 'Jumia Integration')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-4">
                <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 mt-1 shrink-0">← Retour</a>
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-xl bg-[#F68B1E] flex items-center justify-center text-white font-bold text-lg shrink-0" aria-hidden="true">J</div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Intégration Jumia</h1>
                        <p class="text-sm text-gray-600 mt-0.5">Marketplace — Synchronisation des commandes via l'API Vendor Center</p>
                    </div>
                </div>
            </div>
            @php $isActive = old('enabled', $integration?->enabled ?? false); @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                {{ $isActive ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-gray-900 mb-6">Configuration API</h2>

                    <form action="{{ route('integrations.jumia.update') }}" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="integration_name" class="block text-sm font-medium text-gray-700 mb-1">Nom de l'intégration</label>
                            <input type="text" name="integration_name" id="integration_name" required
                                value="{{ old('integration_name', $integration?->integration_name ?? 'Jumia Store') }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#F68B1E] focus:ring-[#F68B1E]">
                        </div>

                        <div>
                            <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client ID <span class="text-red-500">*</span></label>
                            <input type="text" name="client_id" id="client_id" required
                                value="{{ old('client_id', $integration?->client_id ?? '') }}"
                                placeholder="cd1df3a2-47c2-4db5-8568-42f07d8317f0"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#F68B1E] focus:ring-[#F68B1E]">
                            <p class="mt-1 text-xs text-gray-500">Copié depuis Vendor Center → Settings → Applications → Application Details.</p>
                        </div>

                        <div>
                            <label for="refresh_token" class="block text-sm font-medium text-gray-700 mb-1">Refresh Token <span class="text-red-500">*</span></label>
                            <input type="password" name="refresh_token" id="refresh_token" autocomplete="off"
                                placeholder="{{ $integration && $integration->refresh_token ? '•••••••• (laisser vide pour conserver)' : 'Collez votre Refresh Token Jumia' }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#F68B1E] focus:ring-[#F68B1E]">
                            <p class="mt-1 text-xs text-gray-500">Généré via Self Authorization dans Applications → Generate Token.</p>
                        </div>

                        <div>
                            <label for="api_base_url" class="block text-sm font-medium text-gray-700 mb-1">URL de l'API</label>
                            <input type="url" name="api_base_url" id="api_base_url"
                                value="{{ old('api_base_url', $integration?->api_base_url ?? \App\Models\JumiaIntegration::DEFAULT_API_BASE_URL) }}"
                                placeholder="https://vendor-api.jumia.com"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#F68B1E] focus:ring-[#F68B1E]">
                            <p class="mt-1 text-xs text-gray-500">Laissez la valeur par défaut sauf indication contraire de Jumia.</p>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="enabled" id="enabled" value="1"
                                class="rounded border-gray-300 text-[#F68B1E] focus:ring-[#F68B1E]"
                                @checked(old('enabled', $integration?->enabled ?? false))>
                            <label for="enabled" class="ml-2 text-sm text-gray-700">Activer cette intégration</label>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-[#F68B1E] text-white text-sm font-medium rounded-lg hover:bg-[#e07d15] transition">
                                Enregistrer
                            </button>
                        </div>
                    </form>

                    @if($integration && $integration->isConfigured())
                        <div class="mt-6 pt-6 border-t border-gray-100 flex flex-wrap gap-3">
                            <form action="{{ route('integrations.jumia.test') }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-900 transition">
                                    Tester la connexion
                                </button>
                            </form>

                            @if($integration->enabled)
                                <form action="{{ route('integrations.jumia.sync') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                        Synchroniser les commandes
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif

                    @if($integration)
                        <form action="{{ route('integrations.jumia.destroy') }}" method="POST" class="mt-4 pt-4 border-t border-gray-100"
                            onsubmit="return confirm('Supprimer cette intégration ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                                Supprimer
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Statut</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Dernière sync</dt>
                            <dd class="text-gray-900 text-right">{{ $integration?->last_sync_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Connexion</dt>
                            <dd class="text-gray-900 text-right">{{ $integration?->isConfigured() ? 'Configurée' : 'Incomplète' }}</dd>
                        </div>
                    </dl>
                    @if($integration?->last_error)
                        <div class="mt-4 rounded-lg bg-red-50 border border-red-100 p-3 text-xs text-red-800">
                            {{ $integration->last_error }}
                        </div>
                    @endif
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Documentation</h3>
                    <p class="text-sm text-gray-600 mb-3">Créez une application Self Authorization dans Vendor Center, puis copiez le Client ID et le Refresh Token ici.</p>
                    <a href="https://vendorcenter.jumia.com/api-docs/" target="_blank" rel="noopener"
                        class="text-sm font-medium text-[#F68B1E] hover:underline">
                        vendorcenter.jumia.com/api-docs →
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
