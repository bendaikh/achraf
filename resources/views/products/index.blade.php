@extends('layouts.with-sidebar')

@section('title', 'Gestion des produits')

@section('sidebar_page_title', 'Produits')

@php
    $currentKind = request('item_kind', '');
    $currentSource = request('source', '');
    $currentStockStatus = request('stock_status', '');
    $queryBase = request()->except(['page']);

    $kindTabs = [
        '' => ['label' => 'Tous', 'count' => $tabCounts['all'], 'icon' => null, 'color' => 'slate'],
        'stocked' => ['label' => 'Produits stockés', 'count' => $tabCounts['stocked'], 'icon' => 'box', 'color' => 'blue'],
        'non_stocked' => ['label' => 'Produits non stockés', 'count' => $tabCounts['non_stocked'], 'icon' => 'box-slash', 'color' => 'amber'],
        'service' => ['label' => 'Services', 'count' => $tabCounts['service'], 'icon' => 'wrench', 'color' => 'violet'],
    ];

    $sourceFilters = [
        '' => 'Tous',
        'shopify' => 'Shopify',
        'manual' => 'Manuel',
    ];

    $pct = function (int $n) use ($stats) {
        if (($stats['total'] ?? 0) <= 0) {
            return '0%';
        }

        return number_format(($n / $stats['total']) * 100, 1).'%';
    };

    $filterUrl = function (array $overrides = []) use ($queryBase) {
        $params = array_merge($queryBase, $overrides);
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
            }
        }

        return route('products.index', $params);
    };

    $kindColors = [
        'stocked' => 'bg-blue-100 text-blue-800',
        'non_stocked' => 'bg-amber-100 text-amber-800',
        'service' => 'bg-violet-100 text-violet-800',
    ];
@endphp

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-start">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Gestion des produits</h1>
                <p class="text-sm text-slate-600 mt-0.5">Gérez vos produits, services et stocks (POS, Shopify, etc.)</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                @if($shopifyIntegration && $shopifyIntegration->enabled)
                <form action="{{ route('products.sync-shopify') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="w-full sm:w-auto justify-center px-4 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition flex items-center gap-2 text-sm font-medium">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Synchroniser Shopify
                    </button>
                </form>
                @endif
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open"
                        class="w-full sm:w-auto justify-center px-5 py-2.5 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#074866] transition flex items-center gap-2 text-sm font-semibold shadow-sm">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Ajouter un produit / service
                        <svg class="h-4 w-4 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 mt-2 w-72 rounded-xl border border-slate-200 bg-white shadow-lg z-30 overflow-hidden">
                        <a href="{{ route('products.create', ['kind' => 'stocked']) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-blue-50 transition">
                            <span class="h-9 w-9 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">Produit stocké</span>
                                <span class="block text-xs text-slate-500 mt-0.5">Gestion complète du stock</span>
                            </span>
                        </a>
                        <a href="{{ route('products.create', ['kind' => 'non_stocked']) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-amber-50 transition border-t border-slate-100">
                            <span class="h-9 w-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">Produit non stocké</span>
                                <span class="block text-xs text-slate-500 mt-0.5">Sans contrôle de quantité</span>
                            </span>
                        </a>
                        <a href="{{ route('products.create', ['kind' => 'service']) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-violet-50 transition border-t border-slate-100">
                            <span class="h-9 w-9 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center shrink-0">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">Service</span>
                                <span class="block text-xs text-slate-500 mt-0.5">Prestation sans stock</span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded">
                <p class="text-green-700">{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                <p class="text-red-700">{{ session('error') }}</p>
            </div>
        @endif

        {{-- Type tabs (primary) --}}
        <div class="mb-4 border-b border-slate-200">
            <nav class="-mb-px flex flex-wrap gap-1" aria-label="Type d'élément">
                @foreach($kindTabs as $kindKey => $tab)
                    @php $active = (string) $currentKind === (string) $kindKey; @endphp
                    <a href="{{ $filterUrl(['item_kind' => $kindKey ?: null, 'stock_status' => null]) }}"
                       class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition {{ $active ? 'border-[#0a5d8a] text-[#0a5d8a]' : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300' }}">
                        @if($tab['icon'] === 'box')
                            <span class="h-5 w-5 rounded bg-blue-100 text-blue-600 flex items-center justify-center">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </span>
                        @elseif($tab['icon'] === 'box-slash')
                            <span class="h-5 w-5 rounded bg-amber-100 text-amber-600 flex items-center justify-center">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </span>
                        @elseif($tab['icon'] === 'wrench')
                            <span class="h-5 w-5 rounded bg-violet-100 text-violet-600 flex items-center justify-center">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
                            </span>
                        @endif
                        <span>{{ $tab['label'] }}</span>
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-semibold {{ $active ? 'bg-[#0a5d8a]/10 text-[#0a5d8a]' : 'bg-slate-100 text-slate-600' }}">
                            {{ number_format($tab['count']) }}
                        </span>
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Source filter (secondary) --}}
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 mr-1">Source :</span>
            @foreach($sourceFilters as $sourceKey => $sourceLabel)
                @php $active = (string) $currentSource === (string) $sourceKey; @endphp
                <a href="{{ $filterUrl(['source' => $sourceKey ?: null]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border transition {{ $active ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300' }}">
                    @if($sourceKey === 'shopify')
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M16.88 9.1A4 4 0 0 1 16 17H5a5 5 0 0 1-1-9.9V7a3 3 0 0 1 4.52-2.59A4.98 4.98 0 0 1 17 8c0 .38-.04.74-.12 1.1z"/></svg>
                    @endif
                    {{ $sourceLabel }}
                </a>
            @endforeach
        </div>

        {{-- Stats cards (clickable) --}}
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-6">
            @php
                $statCards = [
                    [
                        'label' => 'Produits en stock',
                        'value' => $stats['in_stock'],
                        'pct' => $pct($stats['in_stock']),
                        'url' => $filterUrl(['item_kind' => 'stocked', 'stock_status' => 'in_stock']),
                        'active' => $currentStockStatus === 'in_stock',
                        'tone' => 'green',
                    ],
                    [
                        'label' => 'Stock faible',
                        'value' => $stats['low_stock'],
                        'pct' => $pct($stats['low_stock']),
                        'url' => $filterUrl(['item_kind' => 'stocked', 'stock_status' => 'low_stock']),
                        'active' => $currentStockStatus === 'low_stock',
                        'tone' => 'orange',
                    ],
                    [
                        'label' => 'Rupture de stock',
                        'value' => $stats['out_of_stock'],
                        'pct' => $pct($stats['out_of_stock']),
                        'url' => $filterUrl(['item_kind' => 'stocked', 'stock_status' => 'out_of_stock']),
                        'active' => $currentStockStatus === 'out_of_stock',
                        'tone' => 'red',
                    ],
                    [
                        'label' => 'Produits non stockés',
                        'value' => $stats['non_stocked'],
                        'pct' => $pct($stats['non_stocked']),
                        'url' => $filterUrl(['item_kind' => 'non_stocked', 'stock_status' => null]),
                        'active' => $currentKind === 'non_stocked' && $currentStockStatus === '',
                        'tone' => 'slate',
                    ],
                    [
                        'label' => 'Services',
                        'value' => $stats['services'],
                        'pct' => $pct($stats['services']),
                        'url' => $filterUrl(['item_kind' => 'service', 'stock_status' => null]),
                        'active' => $currentKind === 'service',
                        'tone' => 'violet',
                    ],
                ];
                $toneMap = [
                    'green' => ['border' => 'border-green-200', 'bg' => 'bg-green-50', 'text' => 'text-green-700', 'value' => 'text-green-800', 'ring' => 'ring-green-300'],
                    'orange' => ['border' => 'border-orange-200', 'bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'value' => 'text-orange-800', 'ring' => 'ring-orange-300'],
                    'red' => ['border' => 'border-red-200', 'bg' => 'bg-red-50', 'text' => 'text-red-700', 'value' => 'text-red-800', 'ring' => 'ring-red-300'],
                    'slate' => ['border' => 'border-slate-200', 'bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'value' => 'text-slate-800', 'ring' => 'ring-slate-300'],
                    'violet' => ['border' => 'border-violet-200', 'bg' => 'bg-violet-50', 'text' => 'text-violet-700', 'value' => 'text-violet-800', 'ring' => 'ring-violet-300'],
                ];
            @endphp
            @foreach($statCards as $card)
                @php $t = $toneMap[$card['tone']]; @endphp
                <a href="{{ $card['url'] }}"
                   class="rounded-xl border {{ $t['border'] }} {{ $t['bg'] }} p-4 transition hover:shadow-sm {{ $card['active'] ? 'ring-2 '.$t['ring'] : '' }}">
                    <p class="text-xs font-medium {{ $t['text'] }}">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $t['value'] }}">{{ number_format($card['value']) }}</p>
                    <p class="text-xs {{ $t['text'] }} mt-0.5 opacity-80">{{ $card['pct'] }} du catalogue</p>
                </a>
            @endforeach
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl border border-slate-200 p-4 mb-6" x-data="{ more: {{ request()->hasAny(['price_min','price_max','vat_category','date_from','date_to','service_category']) ? 'true' : 'false' }} }">
            <form method="GET" action="{{ route('products.index') }}" class="space-y-4">
                @if($currentKind !== '')
                    <input type="hidden" name="item_kind" value="{{ $currentKind }}">
                @endif
                @if($currentSource !== '')
                    <input type="hidden" name="source" value="{{ $currentSource }}">
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Nom, référence SKU, code-barres…"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Catégorie</label>
                        <select name="category" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                            <option value="">Tous</option>
                            @foreach($categories as $value => $label)
                                <option value="{{ $value }}" @selected(request('category') == $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Sous-catégorie</label>
                        <select name="subcategory" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                            <option value="">Tous</option>
                            @foreach($subcategories as $value => $label)
                                <option value="{{ $value }}" @selected(request('subcategory') == $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">État du stock</label>
                        <select name="stock_status" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                            <option value="">Tous</option>
                            @foreach($stockStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('stock_status') == $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Fournisseur</label>
                        <select name="supplier_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                            <option value="">Tous</option>
                            @foreach($suppliers as $id => $name)
                                <option value="{{ $id }}" @selected(request('supplier_id') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Dépôt</label>
                        <select name="depot" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                            <option value="">Tous</option>
                            @foreach($depots as $value => $label)
                                <option value="{{ $value }}" @selected(request('depot') == $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Emplacement</label>
                        <select name="location" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                            <option value="">Tous</option>
                            @foreach($locations as $value => $label)
                                <option value="{{ $value }}" @selected(request('location') == $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
                        <select name="status" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                            <option value="">Tous</option>
                            <option value="Activer" @selected(request('status') === 'Activer')>Actif</option>
                            <option value="Désactiver" @selected(request('status') === 'Désactiver')>Inactif</option>
                        </select>
                    </div>
                </div>

                <div x-show="more" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Prix min</label>
                        <input type="number" step="0.01" min="0" name="price_min" value="{{ request('price_min') }}"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Prix max</label>
                        <input type="number" step="0.01" min="0" name="price_max" value="{{ request('price_max') }}"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">TVA</label>
                        <select name="vat_category" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                            <option value="">Tous</option>
                            @foreach($vatCategories as $vat)
                                <option value="{{ $vat }}" @selected(request('vat_category') == $vat)>{{ $vat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Catégorie service</label>
                        <select name="service_category" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                            <option value="">Tous</option>
                            @foreach($serviceCategories as $value => $label)
                                <option value="{{ $value }}" @selected(request('service_category') == $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Créé du</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Créé au</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="px-4 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] text-sm font-semibold">Filtrer</button>
                    <button type="button" @click="more = !more" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg hover:bg-slate-50 text-sm font-medium">
                        <span x-text="more ? 'Moins de filtres' : 'Plus de filtres'"></span>
                    </button>
                    @if(count(request()->except(['page', 'per_page'])) > 0)
                        <a href="{{ route('products.index') }}" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Réinitialiser</a>
                    @endif
                </div>
            </form>
        </div>

        <x-table-bulk-bar export-type="products" item-label="article(s)" />

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <x-table-checkbox-header export-type="products" />
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Source</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Type</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Image</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Réf. / Code-barres</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nom / Catégorie</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Prix / Coût / TVA</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Disponible</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Réservé</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Min. / Alerte</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Dépôt / Emp.</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Fournisseur</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Statut</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($products as $product)
                            @php
                                $kind = $product->item_kind ?? 'stocked';
                                $stockStatus = $product->stockStatus();
                                $available = $product->availableStock();
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <x-table-checkbox-cell export-type="products" :id="$product->id" />

                                {{-- Source --}}
                                <td class="px-3 py-3 whitespace-nowrap">
                                    @if($product->isShopifyProduct())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Shopify</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">Manuel</span>
                                    @endif
                                </td>

                                {{-- Type --}}
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $kindColors[$kind] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ $product->item_kind_label }}
                                    </span>
                                </td>

                                {{-- Image --}}
                                <td class="px-3 py-3 whitespace-nowrap">
                                    @if($product->image_url)
                                        <img src="{{ $product->image_url }}" alt="" class="h-10 w-10 rounded-lg object-cover border border-slate-200">
                                    @else
                                        <div class="h-10 w-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        </div>
                                    @endif
                                </td>

                                {{-- Ref / barcode --}}
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-900">{{ $product->ref }}</div>
                                    @if($product->barcode)
                                        <div class="text-xs text-slate-500 font-mono mt-0.5">{{ $product->barcode }}</div>
                                    @elseif($product->isService())
                                        <div class="text-xs text-slate-400 mt-0.5">—</div>
                                    @endif
                                </td>

                                {{-- Name / category --}}
                                <td class="px-3 py-3 min-w-[12rem]">
                                    <div class="font-medium text-slate-900">{{ $product->name }}</div>
                                    @if($product->isService())
                                        <div class="text-xs text-violet-600 mt-0.5">{{ $product->service_category ?: 'Service' }}</div>
                                        @php
                                            $serviceMeta = collect([
                                                $product->estimated_duration,
                                                $product->billing_unit
                                                    ? (\App\Models\Product::BILLING_UNITS[$product->billing_unit] ?? $product->billing_unit)
                                                    : null,
                                                $product->technician_required ? 'Tech. requis' : null,
                                            ])->filter()->implode(' · ');
                                        @endphp
                                        @if($serviceMeta !== '')
                                            <div class="text-xs text-slate-500 mt-0.5">{{ $serviceMeta }}</div>
                                        @endif
                                    @else
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            {{ $product->product_type_category ?: '—' }}
                                            @if($product->product_category)
                                                / {{ $product->product_category }}
                                            @endif
                                        </div>
                                    @endif
                                    @if($product->variants_count > 1)
                                        <span class="mt-1 inline-flex text-[10px] font-medium px-1.5 py-0.5 rounded bg-blue-50 text-blue-700">{{ $product->variants_count }} variantes</span>
                                    @endif
                                </td>

                                {{-- Prices --}}
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="font-semibold text-slate-900">{{ number_format($product->sale_price ?? 0, 2) }} DHS</div>
                                    <div class="text-xs text-slate-500">Coût: {{ $product->cost_price_ht !== null ? number_format($product->cost_price_ht, 2).' DHS' : '—' }}</div>
                                    <div class="text-xs text-slate-500">TVA: {{ $product->vat_category ?: '—' }}</div>
                                </td>

                                {{-- Available --}}
                                <td class="px-3 py-3 whitespace-nowrap">
                                    @if(! $product->tracksStock())
                                        <span class="text-slate-400" title="{{ $product->stock_status_label }}">—</span>
                                        <div class="text-[10px] text-slate-400 max-w-[6rem] leading-tight">{{ $product->isService() ? 'Sans gestion' : 'Sans contrôle' }}</div>
                                    @else
                                        @if($stockStatus === 'out_of_stock')
                                            <span class="font-bold text-red-600">{{ $available }}</span>
                                            <div><span class="inline-flex mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-100 text-red-700">Rupture</span></div>
                                        @elseif($stockStatus === 'low_stock')
                                            <span class="font-bold text-orange-600">{{ $available }}</span>
                                            <div><span class="inline-flex mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-orange-100 text-orange-700">Stock faible</span></div>
                                        @else
                                            <span class="font-semibold text-green-700">{{ $available }}</span>
                                            <div><span class="inline-flex mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-700">En stock</span></div>
                                        @endif
                                        <div class="text-[10px] text-slate-400 mt-0.5">Physique: {{ $product->stock_quantity }}</div>
                                    @endif
                                </td>

                                {{-- Reserved --}}
                                <td class="px-3 py-3 whitespace-nowrap text-slate-600">
                                    {{ $product->tracksStock() ? (int) ($product->stock_reserved ?? 0) : '—' }}
                                </td>

                                {{-- Min / Alert --}}
                                <td class="px-3 py-3 whitespace-nowrap text-slate-600">
                                    @if($product->tracksStock())
                                        <div>Min: {{ $product->minimum_safety_stock ?? '—' }}</div>
                                        <div class="text-xs text-orange-600">Alerte: {{ $product->minimum_alert_stock ?? '—' }}</div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Depot / location --}}
                                <td class="px-3 py-3 whitespace-nowrap">
                                    @if($product->tracksStock())
                                        <div class="text-slate-800">{{ $product->depot ?: '—' }}</div>
                                        <div class="text-xs text-slate-500">{{ $product->location ?: '—' }}</div>
                                    @elseif($product->isNonStocked())
                                        <span class="text-xs text-amber-700">Sur demande</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Supplier --}}
                                <td class="px-3 py-3 whitespace-nowrap text-slate-700">
                                    @if($product->isService())
                                        <span class="text-slate-400">—</span>
                                    @else
                                        {{ $product->primarySupplier?->name ?? '—' }}
                                    @endif
                                </td>

                                {{-- Status --}}
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->isActive() ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $product->isActive() ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-3 py-3 whitespace-nowrap text-right">
                                    <div class="relative inline-block text-left" x-data="{ open: false }" @keydown.escape.window="open = false">
                                        <button type="button" @click="open = !open"
                                                class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                        </button>
                                        <div x-show="open" x-cloak @click.outside="open = false"
                                             class="absolute right-0 z-20 mt-1 w-56 origin-top-right rounded-xl border border-slate-200 bg-white shadow-lg py-1 text-left">
                                            <a href="{{ route('products.show', $product) }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Voir la fiche</a>
                                            <a href="{{ route('products.edit', $product) }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Modifier</a>
                                            <form action="{{ route('products.duplicate', $product) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-full text-left px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Dupliquer</button>
                                            </form>
                                            <form action="{{ route('products.toggle-status', $product) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-full text-left px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                    {{ $product->isActive() ? 'Désactiver' : 'Activer' }}
                                                </button>
                                            </form>
                                            <form action="{{ route('products.archive', $product) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-full text-left px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Archiver</button>
                                            </form>
                                            @if($product->tracksStock())
                                                <div class="border-t border-slate-100 my-1"></div>
                                                <a href="{{ route('stock.magasin.edit', $product) }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Ajuster le stock</a>
                                                <a href="{{ route('stock.magasin.index', ['search' => $product->ref]) }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Voir les mouvements</a>
                                            @endif
                                            @if($product->isShopifyProduct() && $product->shopify_url)
                                                <div class="border-t border-slate-100 my-1"></div>
                                                <a href="{{ $product->shopify_url }}" target="_blank" class="block px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50">Voir sur Shopify</a>
                                                <button type="button"
                                                        onclick="openDuplicateModal({{ $product->id }}, '{{ addslashes($product->name) }}', '{{ $product->ref }}')"
                                                        class="w-full text-left px-3 py-2 text-sm text-violet-700 hover:bg-violet-50">
                                                    Dupliquer en manuel
                                                </button>
                                            @endif
                                            <div class="border-t border-slate-100 my-1"></div>
                                            <form action="{{ route('products.destroy', $product) }}" method="POST"
                                                  onsubmit="return confirm('Supprimer cet article ? Cette action est irréversible.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50">Supprimer</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="px-6 py-16 text-center">
                                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    <p class="mt-4 text-slate-500">Aucun article trouvé</p>
                                    <a href="{{ route('products.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">
                                        Ajouter un produit / service
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-table-pagination :paginator="$products" item-label="articles" :default-per-page="20" />
        </div>
    </div>
</main>

{{-- Modal duplication Shopify → Manuel --}}
<div id="duplicateModal" class="fixed inset-0 bg-slate-900/40 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-xl bg-white">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-slate-900">Dupliquer en produit manuel</h3>
            <button type="button" onclick="closeDuplicateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="duplicateForm" action="" method="POST">
            @csrf
            <p class="text-sm text-slate-600 mb-1">Copie manuelle de :</p>
            <p class="font-medium text-slate-900" id="duplicateProductName"></p>
            <p class="text-sm text-slate-500 mb-4" id="duplicateProductRef"></p>
            <label for="initial_stock" class="block text-sm font-medium text-slate-700 mb-1">Stock initial *</label>
            <input type="number" name="initial_stock" id="initial_stock" min="0" value="0" required
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#fdb819] mb-4">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeDuplicateModal()" class="px-4 py-2 text-slate-700 bg-slate-100 rounded-lg">Annuler</button>
                <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg">Dupliquer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDuplicateModal(productId, productName, productRef) {
    document.getElementById('duplicateModal').classList.remove('hidden');
    document.getElementById('duplicateProductName').textContent = productName;
    document.getElementById('duplicateProductRef').textContent = 'Réf: ' + productRef;
    document.getElementById('duplicateForm').action = '/products/' + productId + '/duplicate-to-manual';
    document.getElementById('initial_stock').value = 0;
}
function closeDuplicateModal() {
    document.getElementById('duplicateModal').classList.add('hidden');
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDuplicateModal();
});
document.getElementById('duplicateModal').addEventListener('click', function (e) {
    if (e.target === this) closeDuplicateModal();
});
</script>
@endsection
