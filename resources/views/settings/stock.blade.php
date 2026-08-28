@extends('layouts.with-sidebar')

@section('title', 'Stock & Logistique — Paramètres')
@section('sidebar_page_title', 'Paramètres')

@php
    $tab = $tab ?? request('tab', 'regles');
@endphp

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-6 lg:p-8 max-w-6xl mx-auto pb-16">
        @include('settings.partials.alerts')

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-start gap-3 px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-[#0a5d8a]/10 text-[#0a5d8a]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Stock & Logistique</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Dépôts, emplacements et règles de stock centralisées</p>
                </div>
            </div>

            <div class="px-6 pt-4 border-b border-gray-100">
                <nav class="-mb-px flex flex-wrap gap-1">
                    @foreach([
                        'regles' => 'Règles de stock',
                        'depots' => 'Dépôts',
                        'emplacements' => 'Emplacements',
                    ] as $key => $label)
                        <a href="{{ route('settings.stock', ['tab' => $key]) }}"
                           class="px-4 py-2.5 text-sm font-medium border-b-2 {{ $tab === $key ? 'border-[#0a5d8a] text-[#0a5d8a]' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                @if($tab === 'regles')
                    <form action="{{ route('settings.update') }}" method="POST" class="space-y-6 max-w-2xl">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="settings_type" value="stock">
                        <input type="hidden" name="tab" value="regles">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Seuil de stock faible par défaut</label>
                            <input type="number" name="stock_low_threshold" min="0" value="{{ old('stock_low_threshold', $settings['stock_low_threshold'] ?? 3) }}"
                                   class="w-full max-w-xs px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                            <p class="mt-1 text-xs text-gray-500">Disponible ≤ ce seuil → Stock faible. Exemple avec 3 : 4 = En stock, 3 = Stock faible, 0 = Rupture.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stock minimum par défaut</label>
                            <input type="number" name="stock_minimum_default" min="0" value="{{ old('stock_minimum_default', $settings['stock_minimum_default'] ?? 0) }}"
                                   class="w-full max-w-xs px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 cursor-pointer">
                                <input type="hidden" name="stock_allow_negative" value="0">
                                <input type="checkbox" name="stock_allow_negative" value="1" {{ ($settings['stock_allow_negative'] ?? '0') === '1' ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] rounded">
                                <span class="text-sm text-gray-700">Autoriser stock négatif</span>
                            </label>
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 cursor-pointer">
                                <input type="hidden" name="stock_multi_warehouse" value="0">
                                <input type="checkbox" name="stock_multi_warehouse" value="1" {{ ($settings['stock_multi_warehouse'] ?? '1') === '1' ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] rounded">
                                <span class="text-sm text-gray-700">Gestion multi-dépôts</span>
                            </label>
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 cursor-pointer">
                                <input type="hidden" name="stock_control_enabled" value="0">
                                <input type="checkbox" name="stock_control_enabled" value="1" {{ ($settings['stock_control_enabled'] ?? '1') === '1' ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] rounded">
                                <span class="text-sm text-gray-700">Contrôle de stock activé (ventes / achats)</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Méthode de valorisation du stock</label>
                            <select name="stock_valuation_method" class="w-full max-w-xs px-4 py-2 border border-gray-300 rounded-lg bg-white">
                                <option value="">Non définie (ultérieur)</option>
                                <option value="fifo" @selected(($settings['stock_valuation_method'] ?? '') === 'fifo')>FIFO</option>
                                <option value="lifo" @selected(($settings['stock_valuation_method'] ?? '') === 'lifo')>LIFO</option>
                                <option value="average" @selected(($settings['stock_valuation_method'] ?? '') === 'average')>Coût moyen pondéré</option>
                            </select>
                        </div>

                        <button type="submit" class="px-5 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold hover:bg-[#074866]">Enregistrer les règles</button>
                    </form>
                @endif

                @if($tab === 'depots')
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-1">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3">Nouveau dépôt</h3>
                            <form action="{{ route('warehouses.store') }}" method="POST" class="space-y-3 bg-slate-50 border border-slate-200 rounded-xl p-4">
                                @csrf
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nom *</label>
                                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Dépôt principal – Belvédère">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Code *</label>
                                    <input type="text" name="code" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="PRINCIPAL">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Adresse</label>
                                    <input type="text" name="address" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Ville</label>
                                    <input type="text" name="city" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Statut</label>
                                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                        <option value="active">Actif</option>
                                        <option value="inactive">Inactif</option>
                                    </select>
                                </div>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="is_primary" value="1" class="rounded text-[#0a5d8a]">
                                    Dépôt principal
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="is_fulfillment_default" value="1" class="rounded text-[#0a5d8a]">
                                    Préparation des commandes (Belvédère)
                                </label>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                                    <select name="kind" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                        <option value="physical">Physique (magasin / dépôt)</option>
                                        <option value="online">En ligne (Shopify)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Commentaire</label>
                                    <textarea name="comment" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                                </div>
                                <button type="submit" class="w-full px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Ajouter</button>
                            </form>
                        </div>
                        <div class="lg:col-span-2 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Nom</th>
                                        <th class="px-3 py-2">Code</th>
                                        <th class="px-3 py-2">Ville</th>
                                        <th class="px-3 py-2">Empl.</th>
                                        <th class="px-3 py-2">Statut</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($warehouses as $warehouse)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <div class="font-medium text-slate-900">{{ $warehouse->name }}</div>
                                                @if($warehouse->is_primary)
                                                    <span class="text-[10px] font-semibold uppercase text-[#0a5d8a]">Principal</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 font-mono text-xs">{{ $warehouse->code }}</td>
                                            <td class="px-3 py-2">{{ $warehouse->city ?: '—' }}</td>
                                            <td class="px-3 py-2">{{ $warehouse->locations_count }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded-full text-xs {{ $warehouse->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                                    {{ $warehouse->isActive() ? 'Actif' : 'Inactif' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <form action="{{ route('warehouses.destroy', $warehouse) }}" method="POST" onsubmit="return confirm('Supprimer ce dépôt ?')">
                                                    @csrf @method('DELETE')
                                                    <button class="text-red-600 text-xs hover:underline">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">Aucun dépôt</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if($tab === 'emplacements')
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-1">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3">Nouvel emplacement</h3>
                            <form action="{{ route('warehouse-locations.store') }}" method="POST" class="space-y-3 bg-slate-50 border border-slate-200 rounded-xl p-4">
                                @csrf
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Dépôt associé *</label>
                                    <select name="warehouse_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                        <option value="">Sélectionner…</option>
                                        @foreach($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->displayLabel() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Code emplacement *</label>
                                    <input type="text" name="code" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="A-01-01">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nom / description</label>
                                    <input type="text" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Zone</label>
                                    <input type="text" name="zone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Statut</label>
                                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                                        <option value="active">Actif</option>
                                        <option value="inactive">Inactif</option>
                                    </select>
                                </div>
                                <button type="submit" class="w-full px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Ajouter</button>
                            </form>
                        </div>
                        <div class="lg:col-span-2 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Code</th>
                                        <th class="px-3 py-2">Description</th>
                                        <th class="px-3 py-2">Dépôt</th>
                                        <th class="px-3 py-2">Zone</th>
                                        <th class="px-3 py-2">Statut</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($locations as $location)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-xs">{{ $location->code }}</td>
                                            <td class="px-3 py-2">{{ $location->name ?: '—' }}</td>
                                            <td class="px-3 py-2">{{ $location->warehouse?->name ?: '—' }}</td>
                                            <td class="px-3 py-2">{{ $location->zone ?: '—' }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded-full text-xs {{ $location->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                                    {{ $location->isActive() ? 'Actif' : 'Inactif' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <form action="{{ route('warehouse-locations.destroy', $location) }}" method="POST" onsubmit="return confirm('Supprimer cet emplacement ?')">
                                                    @csrf @method('DELETE')
                                                    <button class="text-red-600 text-xs hover:underline">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">Aucun emplacement</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</main>
@endsection
