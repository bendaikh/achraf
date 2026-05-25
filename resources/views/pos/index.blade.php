@extends('layouts.app')

@section('title', 'Point de vente')

@section('content')
<div
    class="bg-slate-900 flex text-slate-100"
    :class="posFullView ? 'fixed inset-0 z-50 min-h-screen' : 'min-h-screen relative'"
    x-data="posRegister(@js($productsMagasinForJs), @js($productsEnligneForJs), @js($pricesAreTtc), @js($comptoirClient->id))"
    @keydown.escape.window="onGlobalEscape()"
    x-cloak
>
    <div
        x-show="!posFullView && sidebarOpen"
        x-transition.opacity.duration.200ms
        @click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-black/50 lg:hidden"
        style="display: none;"
        x-cloak
    ></div>

    <aside
        x-show="!posFullView"
        x-transition.opacity.duration.200ms
        class="bg-white shadow-lg fixed inset-y-0 left-0 h-full overflow-y-auto z-40 border-r border-slate-200 transform transition-all duration-200 ease-out -translate-x-full lg:translate-x-0"
        :class="{
            'translate-x-0': sidebarOpen,
            'w-64 max-w-[min(16rem,88vw)]': !sidebarCollapsed || sidebarOpen,
            'lg:w-20': sidebarCollapsed && !sidebarOpen
        }"
        @click="if ($event.target.closest('a[href]')) sidebarOpen = false"
    >
        @include('layouts.sidebar')
    </aside>

    <main class="flex-1 flex flex-col min-h-screen w-full min-w-0 bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 transition-[margin] duration-200" :class="posFullView ? 'ml-0 w-full' : (sidebarCollapsed ? 'ml-0 lg:ml-20' : 'ml-0 lg:ml-64')">
        <div x-show="!posFullView" class="shrink-0 flex items-center gap-2 px-4 py-3 border-b border-white/10 bg-slate-900/95 backdrop-blur">
            <button type="button" @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg text-white/90 hover:bg-white/10 touch-manipulation" aria-label="Ouvrir le menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <button type="button" @click="sidebarCollapsed = !sidebarCollapsed; localStorage.setItem('sidebarCollapsed', sidebarCollapsed)" class="hidden lg:flex p-2 rounded-lg text-white/90 hover:bg-white/10 touch-manipulation transition-transform duration-200" aria-label="Toggle sidebar">
                <svg class="h-6 w-6 transition-transform duration-200" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>
            <span class="text-sm font-semibold text-white truncate">Point de vente</span>
        </div>

        <button type="button" x-show="posFullView" x-transition @click="posFullView = false" class="fixed top-3 right-3 z-[60] inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800/95 text-white text-sm font-semibold border border-white/15 shadow-lg hover:bg-slate-700 backdrop-blur">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
            Quitter l’aperçu
        </button>

        <header class="shrink-0 border-b border-white/10 bg-slate-900/80 backdrop-blur px-6 py-4 flex flex-wrap items-center justify-between gap-4" :class="posFullView ? 'pr-44' : ''">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Point de vente</h1>
                <p class="text-sm text-emerald-200/80 mt-0.5">
                    <span x-show="!posFullView">Caisse · encaissement · tickets</span>
                    <span x-show="posFullView" class="text-emerald-300/90">Aperçu plein écran — catalogue et panier sur toute la largeur</span>
                </p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" x-show="!posFullView" @click="posFullView = true" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600/90 text-white text-sm font-semibold hover:bg-emerald-500 border border-emerald-400/30 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    Aperçu plein écran
                </button>
                <a href="{{ route('pos.sales.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 text-white text-sm font-medium hover:bg-white/15 border border-white/10 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Historique
                </a>
            </div>
        </header>

        @if(session('error'))
            <div class="mx-6 mt-4 rounded-lg bg-red-500/20 border border-red-400/40 px-4 py-3 text-sm text-red-100">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('pos.checkout') }}" class="flex-1 flex flex-col lg:flex-row min-h-0" @submit="if (cart.length === 0) { $event.preventDefault(); alert('Panier vide.'); }">
            @csrf
            <input type="hidden" name="client_id" :value="clientId || ''">

            <section class="flex-1 flex flex-col min-h-0 p-4 lg:p-6 border-b lg:border-b-0 lg:border-r border-white/10">
                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    <div class="flex-1 relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" x-model="searchQuery" @input.debounce.300ms="runSearch()" placeholder="Rechercher un produit (nom, réf.)…" class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-800/80 border border-white/10 text-white placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition">
                    </div>
                    <div class="w-full sm:w-64 flex gap-2">
                        <input type="text" x-model="barcode" @keydown.enter.prevent="scanBarcode()" placeholder="Code-barres" class="flex-1 min-w-0 px-3 py-3 rounded-xl bg-slate-800/80 border border-white/10 text-white placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 outline-none text-sm">
                        <button type="button" @click="scanBarcode()" class="px-4 py-3 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-500 transition whitespace-nowrap flex-shrink-0">OK</button>
                    </div>
                </div>

                <div x-show="searchResults.length > 0 && searchQuery.trim()" x-transition class="mb-4 rounded-xl border border-emerald-500/30 bg-slate-800/60 divide-y divide-white/5 max-h-64 overflow-y-auto">
                    <template x-for="p in searchResults" :key="p.id">
                        <button type="button" @click="addProduct(p); searchQuery = ''; searchResults = []" class="w-full text-left px-4 py-3 hover:bg-emerald-600/20 flex items-center gap-3">
                            <div class="w-12 h-12 flex-shrink-0 rounded-lg overflow-hidden bg-slate-700/50 border border-white/5">
                                <template x-if="p.image_url">
                                    <img :src="p.image_url" :alt="p.name" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!p.image_url">
                                    <div class="w-full h-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                        </svg>
                                    </div>
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-white truncate" x-text="p.name"></p>
                                <p class="text-xs text-slate-400" x-text="p.ref"></p>
                            </div>
                            <span class="text-emerald-300 font-semibold whitespace-nowrap"><span x-text="formatMoney(p.sale_price)"></span> DH</span>
                        </button>
                    </template>
                </div>

                <div class="flex flex-col gap-3 mb-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                Catalogue
                            </h2>
                            <div class="flex rounded-lg overflow-hidden border border-white/10">
                                <button type="button" @click="stockType = 'magasin'; currentPage = 1" :class="stockType === 'magasin' ? 'bg-[#fdb819] text-white' : 'bg-slate-800/60 text-slate-400 hover:bg-slate-700'" class="px-3 py-1.5 text-xs font-semibold transition-colors flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    Stock Magasin
                                </button>
                                <button type="button" @click="stockType = 'enligne'; currentPage = 1" :class="stockType === 'enligne' ? 'bg-[#fdb819] text-white' : 'bg-slate-800/60 text-slate-400 hover:bg-slate-700'" class="px-3 py-1.5 text-xs font-semibold transition-colors flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                    Stock En Ligne
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <select x-model="perPage" @change="currentPage = 1" class="text-xs bg-slate-800/60 border border-white/10 text-slate-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-[#fdb819] outline-none">
                                <option value="24">24 / page</option>
                                <option value="48">48 / page</option>
                                <option value="96">96 / page</option>
                            </select>
                            <span class="text-xs font-medium text-slate-500 bg-slate-800/60 px-3 py-1.5 rounded-full border border-white/5">
                                <span x-text="currentCatalog.length"></span> produit<span x-show="currentCatalog.length > 1">s</span>
                            </span>
                        </div>
                    </div>
                    <!-- Pagination controls -->
                    <div x-show="totalPages > 1" class="flex items-center justify-center gap-2">
                        <button type="button" @click="currentPage = 1" :disabled="currentPage === 1" class="px-2 py-1 rounded bg-slate-800/60 text-slate-400 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-xs">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1 rounded bg-slate-800/60 text-slate-400 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-medium">
                            Précédent
                        </button>
                        <div class="flex items-center gap-1">
                            <template x-for="page in visiblePages" :key="page">
                                <button type="button" @click="if(page !== '...') currentPage = page" :class="page === currentPage ? 'bg-[#fdb819] text-white' : (page === '...' ? 'bg-transparent text-slate-500 cursor-default' : 'bg-slate-800/60 text-slate-400 hover:bg-slate-700')" class="min-w-[28px] px-2 py-1 rounded text-xs font-medium" x-text="page"></button>
                            </template>
                        </div>
                        <button type="button" @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="px-3 py-1 rounded bg-slate-800/60 text-slate-400 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-medium">
                            Suivant
                        </button>
                        <button type="button" @click="currentPage = totalPages" :disabled="currentPage === totalPages" class="px-2 py-1 rounded bg-slate-800/60 text-slate-400 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-xs">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
                <template x-if="currentCatalog.length > 0">
                    <div
                        class="flex-1 overflow-y-auto pb-4"
                    >
                        <div 
                            class="grid gap-3 auto-rows-max"
                            :class="posFullView ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4' : 'grid-cols-2 md:grid-cols-3 xl:grid-cols-4'"
                        >
                            <template x-for="p in paginatedCatalog" :key="p.id">
                                <button type="button" @click='addProduct(p)' class="group text-left rounded-xl border border-white/10 bg-slate-800/40 overflow-hidden hover:border-emerald-400/50 hover:bg-slate-800/80 hover:shadow-lg hover:shadow-emerald-500/10 transition-all duration-200 h-auto">
                                    <div class="w-full aspect-square bg-gradient-to-br from-slate-700/50 to-slate-800/50 overflow-hidden relative">
                                        <img x-show="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                        <div x-show="!p.image_url" class="w-full h-full flex items-center justify-center">
                                            <svg class="w-12 h-12 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        </div>
                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                        <div class="absolute top-1.5 right-1.5 px-2 py-0.5 rounded text-xs font-medium" :class="p.stock > 0 ? 'bg-emerald-500/90 text-white' : 'bg-red-500/90 text-white'">
                                            <span x-text="p.stock"></span> en stock
                                        </div>
                                    </div>
                                    <div class="px-2.5 py-2">
                                        <p class="font-semibold text-white text-sm line-clamp-2 leading-tight group-hover:text-emerald-200 transition-colors" x-text="p.name"></p>
                                        <p class="text-xs text-slate-500 font-mono truncate" x-text="p.ref"></p>
                                        <div class="flex items-baseline gap-1 mt-1">
                                            <span class="text-base font-bold text-emerald-400 group-hover:text-emerald-300" x-text="formatMoney(p.sale_price)"></span>
                                            <span class="text-xs font-medium text-slate-400">DH</span>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <!-- Bottom pagination controls -->
                <div x-show="totalPages > 1 && currentCatalog.length > 0" class="flex items-center justify-center gap-2 py-3 border-t border-white/10 mt-2">
                    <button type="button" @click="currentPage = 1" :disabled="currentPage === 1" class="px-2 py-1 rounded bg-slate-800/60 text-slate-400 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-xs">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1 rounded bg-slate-800/60 text-slate-400 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-medium">
                        Précédent
                    </button>
                    <span class="text-xs text-slate-400 px-2">
                        Page <span x-text="currentPage"></span> / <span x-text="totalPages"></span>
                    </span>
                    <button type="button" @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="px-3 py-1 rounded bg-slate-800/60 text-slate-400 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-medium">
                        Suivant
                    </button>
                    <button type="button" @click="currentPage = totalPages" :disabled="currentPage === totalPages" class="px-2 py-1 rounded bg-slate-800/60 text-slate-400 hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-xs">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <template x-if="currentCatalog.length === 0">
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center py-12">
                            <svg class="h-24 w-24 mx-auto text-slate-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <p class="text-slate-400 text-lg font-medium mb-2">Aucun produit disponible</p>
                            <p class="text-slate-600 text-sm" x-text="stockType === 'magasin' ? 'Ajoutez des produits depuis la gestion des produits' : 'Synchronisez des produits depuis Shopify'"></p>
                        </div>
                    </div>
                </template>
            </section>

            <aside class="w-full shrink-0 flex flex-col bg-slate-950/50 backdrop-blur border-t lg:border-t-0 lg:border-l border-white/10" :class="posFullView ? 'lg:w-[min(32rem,38vw)] xl:w-[min(36rem,34vw)]' : 'lg:w-[420px]'">
                <input type="hidden" name="stock_type" :value="stockType">
                <div class="p-4 border-b border-white/10">
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Client (optionnel)</label>
                    <select id="pos_client_id" x-ref="clientSelect" class="w-full rounded-lg bg-slate-800 border border-white/10 text-white text-sm py-2.5 px-3 focus:ring-2 focus:ring-[#fdb819] outline-none">
                        <option value="{{ $comptoirClient->id }}" selected>{{ $comptoirClient->name }}</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} {{ $c->email ? '('.$c->email.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-2 min-h-[200px]">
                    <template x-if="cart.length === 0">
                        <div class="text-center py-12">
                            <svg class="h-16 w-16 mx-auto text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <p class="text-slate-500 text-sm">Panier vide</p>
                            <p class="text-slate-600 text-xs mt-1">Ajoutez des produits pour commencer</p>
                        </div>
                    </template>
                    <template x-for="(line, idx) in cart" :key="line.key">
                        <div class="rounded-xl bg-gradient-to-br from-slate-800/90 to-slate-800/60 border border-white/10 p-3 space-y-3 hover:border-emerald-500/30 transition-colors">
                            <div class="flex gap-3">
                                <div class="w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden bg-slate-700/50 border border-white/5">
                                    <template x-if="line.image_url">
                                        <img :src="line.image_url" :alt="line.name" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!line.image_url">
                                        <div class="w-full h-full flex items-center justify-center">
                                            <svg class="w-7 h-7 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-white text-sm leading-tight mb-1" x-text="line.name"></p>
                                    <p class="text-xs text-slate-500 font-mono" x-text="line.ref"></p>
                                </div>
                                <button type="button" @click="removeLine(idx)" class="text-slate-500 hover:text-red-400 p-1.5 h-fit rounded-lg hover:bg-red-500/10 transition-colors" aria-label="Retirer">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="text-xs text-slate-500 mb-1 block">Quantité</label>
                                    <input type="number" min="1" class="w-full rounded-lg bg-slate-900/80 border border-white/10 text-white px-3 py-2 text-sm font-medium focus:ring-2 focus:ring-emerald-500 outline-none" x-model.number="line.quantity">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500 mb-1 block">Prix U.</label>
                                    <input type="number" step="0.01" min="0" class="w-full rounded-lg bg-slate-900/80 border border-white/10 text-white px-3 py-2 text-sm font-medium focus:ring-2 focus:ring-emerald-500 outline-none" x-model.number="line.unit_price">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500 mb-1 block">Remise</label>
                                    <input type="number" step="0.01" min="0" class="w-full rounded-lg bg-slate-900/80 border border-white/10 text-white px-3 py-2 text-sm font-medium focus:ring-2 focus:ring-emerald-500 outline-none" x-model.number="line.discount">
                                </div>
                            </div>
                            <div class="pt-2 border-t border-white/5 flex justify-between items-center">
                                <span class="text-xs text-slate-500">Total ligne</span>
                                <span class="text-emerald-400 text-base font-bold"><span x-text="formatMoney(lineTotal(line))"></span> DH</span>
                            </div>
                        </div>
                    </template>
                </div>

                <template x-for="(line, idx) in cart" :key="'h-'+line.key">
                    <div class="hidden">
                        <input type="hidden" :name="'items['+idx+'][product_id]'" :value="line.product_id">
                        <input type="hidden" :name="'items['+idx+'][quantity]'" :value="line.quantity">
                        <input type="hidden" :name="'items['+idx+'][unit_price]'" :value="line.unit_price">
                        <input type="hidden" :name="'items['+idx+'][tax_rate]'" :value="line.tax_rate">
                        <input type="hidden" :name="'items['+idx+'][discount]'" :value="line.discount">
                    </div>
                </template>

                <div class="p-4 border-t border-white/10 space-y-3 bg-slate-900/90">
                    <div class="flex justify-between text-sm text-slate-400">
                        <span>Total HT (lignes)</span>
                        <span x-text="formatMoney(subtotalHt())"></span>
                    </div>
                    <div class="flex justify-between text-sm text-slate-400">
                        <span>TVA</span>
                        <span x-text="formatMoney(taxTotal())"></span>
                    </div>
                    <div class="flex justify-between text-sm text-slate-400">
                        <span>Remise globale (DH)</span>
                        <input type="number" step="0.01" min="0" name="global_discount" x-model.number="globalDiscount" class="w-24 rounded bg-slate-800 border border-white/10 text-white text-right px-2 py-1 text-sm">
                    </div>
                    <div class="flex justify-between text-lg font-bold text-white pt-2 border-t border-white/10">
                        <span>Total TTC</span>
                        <span class="text-emerald-400"><span x-text="formatMoney(totalTtc())"></span> DH</span>
                    </div>
                    <button type="button" @click="openPayment()" :disabled="cart.length === 0" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-bold text-base shadow-lg shadow-emerald-900/40 hover:from-emerald-400 hover:to-teal-500 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        Paiement
                    </button>
                </div>
            </aside>

            <div x-show="paymentOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" style="display: none;">
                <div @click.away="paymentOpen = false" class="w-full max-w-md rounded-2xl bg-slate-900 border border-white/10 shadow-2xl p-6 space-y-5">
                    <h3 class="text-xl font-bold text-white">Encaissement</h3>
                    <p class="text-sm text-slate-400">Total à payer : <span class="text-emerald-400 font-bold text-lg" x-text="formatMoney(totalTtc()) + ' DH'"></span></p>

                    <div class="space-y-2">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Mode de paiement</span>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($paymentMethods as $value => $label)
                                <label class="flex items-center gap-2 rounded-lg border px-3 py-2 cursor-pointer transition" :class="paymentMethod === '{{ $value }}' ? 'border-emerald-500 bg-emerald-500/10' : 'border-white/10 bg-slate-800/50'">
                                    <input type="radio" name="payment_method" value="{{ $value }}" x-model="paymentMethod" class="text-emerald-500 focus:ring-emerald-500">
                                    <span class="text-sm text-white">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <template x-if="paymentMethod === 'cash'">
                        <div class="space-y-2">
                            <label class="text-xs font-semibold text-slate-500 uppercase">Montant reçu (DH)</label>
                            <input type="number" step="0.01" min="0" name="amount_received" x-model="amountReceived" class="w-full rounded-lg bg-slate-800 border border-white/10 text-white px-4 py-3 text-lg font-mono focus:ring-2 focus:ring-emerald-500 outline-none">
                            <p class="text-sm text-slate-400">Monnaie : <span class="text-emerald-300 font-semibold" x-text="formatMoney(changeDue()) + ' DH'"></span></p>
                        </div>
                    </template>
                    <template x-if="paymentMethod !== 'cash'">
                        <input type="hidden" name="amount_received" :value="totalTtc()">
                    </template>

                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Note (optionnel)</label>
                        <textarea name="notes" rows="2" x-model="notes" class="mt-1 w-full rounded-lg bg-slate-800 border border-white/10 text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none resize-none" placeholder="Réf. chèque, commentaire…"></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="paymentOpen = false" class="flex-1 py-3 rounded-xl border border-white/20 text-slate-300 font-medium hover:bg-white/5">Annuler</button>
                        <button type="submit" @click="if (paymentMethod === 'cash' && parseFloat(amountReceived) < totalTtc()) { $event.preventDefault(); alert('Montant insuffisant.'); }" class="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-500">Valider la vente</button>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<style>[x-cloak]{display:none!important}</style>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
.select2-container--default .select2-selection--single {
    background-color: rgb(30 41 59) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    border-radius: 0.5rem !important;
    height: auto !important;
    padding: 0.5rem 0.75rem !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: white !important;
    line-height: 1.5 !important;
    padding: 0 !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 100% !important;
    right: 8px !important;
}
.select2-dropdown {
    background-color: rgb(30 41 59) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.select2-search--dropdown .select2-search__field {
    background-color: rgb(15 23 42) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
    border-radius: 0.375rem !important;
}
.select2-results__option {
    color: white !important;
    padding: 0.5rem 0.75rem !important;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #fdb819 !important;
    color: white !important;
}
.select2-container--default .select2-results__option[aria-selected=true] {
    background-color: rgba(253, 184, 25, 0.3) !important;
}
</style>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function posRegister(catalogMagasin, catalogEnligne, pricesAreTtc, defaultClientId) {
    return {
        catalogMagasin: catalogMagasin,
        catalogEnligne: catalogEnligne,
        pricesAreTtc: pricesAreTtc,
        defaultClientId: defaultClientId,
        stockType: 'magasin',
        posFullView: false,
        sidebarOpen: false,
        sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
        cart: [],
        searchQuery: '',
        searchResults: [],
        barcode: '',
        clientId: String(defaultClientId || ''),
        paymentOpen: false,
        paymentMethod: 'cash',
        amountReceived: '',
        globalDiscount: 0,
        notes: '',
        currentPage: 1,
        perPage: 24,
        get currentCatalog() {
            return this.stockType === 'magasin' ? this.catalogMagasin : this.catalogEnligne;
        },
        get totalPages() {
            return Math.ceil(this.currentCatalog.length / this.perPage);
        },
        get paginatedCatalog() {
            const start = (this.currentPage - 1) * this.perPage;
            const end = start + this.perPage;
            return this.currentCatalog.slice(start, end);
        },
        get visiblePages() {
            const total = this.totalPages;
            const current = this.currentPage;
            const pages = [];
            
            if (total <= 7) {
                for (let i = 1; i <= total; i++) pages.push(i);
            } else {
                pages.push(1);
                if (current > 3) pages.push('...');
                
                const start = Math.max(2, current - 1);
                const end = Math.min(total - 1, current + 1);
                
                for (let i = start; i <= end; i++) pages.push(i);
                
                if (current < total - 2) pages.push('...');
                pages.push(total);
            }
            
            return pages;
        },
        init() {
            const self = this;
            $(document).ready(function() {
                const $clientSelect = $('#pos_client_id');
                $clientSelect.select2({
                    placeholder: 'Rechercher un client...',
                    allowClear: false,
                    width: '100%'
                }).val(self.defaultClientId).trigger('change');
                $clientSelect.on('change', function() {
                    self.clientId = $(this).val() || String(self.defaultClientId);
                });
                self.clientId = $clientSelect.val() || String(self.defaultClientId);
            });
        },
        addProduct(p) {
            const existing = this.cart.find(c => c.product_id === p.id);
            if (existing) {
                existing.quantity += 1;
                return;
            }
            this.cart.push({
                key: crypto.randomUUID(),
                product_id: p.id,
                name: p.name,
                ref: p.ref,
                unit_price: Number(p.sale_price) || 0,
                tax_rate: Number(p.tax_rate) || 20,
                quantity: 1,
                discount: 0,
                image_url: p.image_url || null,
            });
        },
        removeLine(i) { this.cart.splice(i, 1); },
        lineBase(line) {
            const q = Number(line.quantity) || 0;
            const u = Number(line.unit_price) || 0;
            const d = Number(line.discount) || 0;
            const tr = Number(line.tax_rate) || 0;
            
            if (this.pricesAreTtc) {
                // If prices are TTC, unit_price includes tax, we need to calculate HT
                const totalTtc = Math.max(0, q * u - d);
                const ht = totalTtc / (1 + tr / 100);
                return ht;
            } else {
                // If prices are HT, calculate normally
                return Math.max(0, q * u - d);
            }
        },
        lineTax(line) {
            const q = Number(line.quantity) || 0;
            const u = Number(line.unit_price) || 0;
            const d = Number(line.discount) || 0;
            const tr = Number(line.tax_rate) || 0;
            
            if (this.pricesAreTtc) {
                // If prices are TTC, calculate tax from the TTC amount
                const totalTtc = Math.max(0, q * u - d);
                const ht = totalTtc / (1 + tr / 100);
                return totalTtc - ht;
            } else {
                // If prices are HT, calculate tax normally
                return this.lineBase(line) * (tr / 100);
            }
        },
        lineTotal(line) {
            const q = Number(line.quantity) || 0;
            const u = Number(line.unit_price) || 0;
            const d = Number(line.discount) || 0;
            
            if (this.pricesAreTtc) {
                // If prices are TTC, the total is directly from unit_price
                return Math.max(0, q * u - d);
            } else {
                // If prices are HT, add tax
                return this.lineBase(line) + this.lineTax(line);
            }
        },
        subtotalHt() {
            return this.cart.reduce((s, l) => s + this.lineBase(l), 0);
        },
        taxTotal() {
            return this.cart.reduce((s, l) => s + this.lineTax(l), 0);
        },
        totalTtc() {
            const gd = Number(this.globalDiscount) || 0;
            return Math.max(0, this.subtotalHt() + this.taxTotal() - gd);
        },
        formatMoney(n) {
            return (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
        },
        changeDue() {
            const rec = parseFloat(this.amountReceived);
            if (isNaN(rec)) return 0;
            return Math.max(0, rec - this.totalTtc());
        },
        onGlobalEscape() {
            if (this.paymentOpen) {
                this.paymentOpen = false;
                return;
            }
            if (this.sidebarOpen) {
                this.sidebarOpen = false;
                return;
            }
            if (this.posFullView) {
                this.posFullView = false;
            }
        },
        openPayment() {
            this.paymentOpen = true;
            this.amountReceived = this.formatMoney(this.totalTtc());
        },
        async runSearch() {
            const q = this.searchQuery.trim();
            if (!q) { this.searchResults = []; return; }
            try {
                const r = await fetch('{{ route('pos.products.search') }}?q=' + encodeURIComponent(q) + '&stock_type=' + this.stockType, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                this.searchResults = data.products || [];
            } catch (e) { this.searchResults = []; }
        },
        async scanBarcode() {
            const b = this.barcode.trim();
            if (!b) return;
            try {
                const r = await fetch('{{ route('pos.products.search') }}?barcode=' + encodeURIComponent(b) + '&stock_type=' + this.stockType, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                if (data.products && data.products.length) {
                    this.addProduct(data.products[0]);
                }
            } catch (e) {}
            this.barcode = '';
        },
    };
}
</script>
@endsection
