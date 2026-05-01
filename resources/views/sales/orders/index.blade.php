@extends('layouts.with-sidebar')

@section('title', 'Les Commandes')

@section('sidebar_page_title', 'Les Commandes')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Les Commandes</h1>
                <p class="text-sm text-gray-600 mt-0.5">Gérez toutes vos commandes (POS, Shopify, etc.)</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    Sync automatique activée
                </span>
            </div>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Commandes</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalOrders) }}</p>
                    </div>
                    <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Shopify</p>
                        <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($totalShopifyOrders) }}</p>
                    </div>
                    <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Point de Vente</p>
                        <p class="text-2xl font-bold text-purple-600 mt-1">{{ number_format($totalPosOrders) }}</p>
                    </div>
                    <div class="h-12 w-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Chiffre d'affaires</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalRevenue, 2) }} DH</p>
                    </div>
                    <div class="h-12 w-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Column Visibility Toggle -->
        <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-700">Colonnes visibles</h3>
                <div class="relative">
                    <button type="button" id="columnToggleBtn" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                        </svg>
                        Afficher/Masquer colonnes
                    </button>
                    
                    <!-- Dropdown menu -->
                    <div id="columnToggleMenu" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="p-3 space-y-2">
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" class="column-toggle rounded text-blue-600 focus:ring-blue-500" data-column="numero" checked>
                                <span class="text-sm text-gray-700">N° Commande</span>
                            </label>
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" class="column-toggle rounded text-blue-600 focus:ring-blue-500" data-column="source" checked>
                                <span class="text-sm text-gray-700">Source</span>
                            </label>
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" class="column-toggle rounded text-blue-600 focus:ring-blue-500" data-column="client" checked>
                                <span class="text-sm text-gray-700">Client</span>
                            </label>
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" class="column-toggle rounded text-blue-600 focus:ring-blue-500" data-column="date" checked>
                                <span class="text-sm text-gray-700">Date</span>
                            </label>
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" class="column-toggle rounded text-blue-600 focus:ring-blue-500" data-column="total" checked>
                                <span class="text-sm text-gray-700">Total</span>
                            </label>
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" class="column-toggle rounded text-blue-600 focus:ring-blue-500" data-column="paiement" checked>
                                <span class="text-sm text-gray-700">Paiement</span>
                            </label>
                            <label class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" class="column-toggle rounded text-blue-600 focus:ring-blue-500" data-column="livraison" checked>
                                <span class="text-sm text-gray-700">Livraison</span>
                            </label>
                            <div class="pt-2 border-t border-gray-200">
                                <button type="button" id="resetColumns" class="w-full px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded transition-colors">
                                    Réinitialiser
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('orders.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Rechercher</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" 
                        placeholder="N° commande, client..."
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                    <select name="source" id="source" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Toutes</option>
                        <option value="shopify" {{ request('source') === 'shopify' ? 'selected' : '' }}>Shopify</option>
                        <option value="pos" {{ request('source') === 'pos' ? 'selected' : '' }}>Point de Vente</option>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="status" id="status" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Tous</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Complété</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Annulé</option>
                    </select>
                </div>

                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" 
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Filtrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider column-numero">N° Commande</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider column-source">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider column-client">Client</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider column-date">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider column-total">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider column-paiement">Paiement</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider column-livraison">Livraison</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($orders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap column-numero">
                                <div class="text-sm font-medium text-gray-900">{{ $order->ticket_number }}</div>
                                @if($order->external_id)
                                <div class="text-xs text-gray-500">ID: {{ $order->external_id }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap column-source">
                                @if($order->source === 'shopify')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    Shopify
                                </span>
                                @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    POS
                                </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap column-client">
                                <div class="text-sm text-gray-900">{{ $order->client?->name ?? 'Client anonyme' }}</div>
                                @if($order->client?->email)
                                <div class="text-xs text-gray-500">{{ $order->client->email }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 column-date">
                                {{ $order->sold_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap column-total">
                                <div class="text-sm font-medium text-gray-900">{{ number_format($order->total, 2) }} {{ $order->currency ?? 'DH' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap column-paiement">
                                @if($order->source === 'shopify' && $order->payment_status)
                                    @if($order->payment_status === 'paid')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        Payé
                                    </span>
                                    @elseif($order->payment_status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                        </svg>
                                        En attente
                                    </span>
                                    @elseif($order->payment_status === 'refunded' || $order->payment_status === 'partially_refunded')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                                        </svg>
                                        Remboursé
                                    </span>
                                    @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ ucfirst($order->payment_status) }}
                                    </span>
                                    @endif
                                @else
                                <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap column-livraison">
                                @if($order->source === 'shopify' && $order->fulfillment_status)
                                    @php
                                        $fulfillmentLabels = [
                                            'fulfilled' => 'Traité',
                                            'unfulfilled' => 'Non traité',
                                            'partial' => 'Partiellement traité',
                                        ];
                                        $fulfillmentLabel = $fulfillmentLabels[$order->fulfillment_status] ?? ucfirst($order->fulfillment_status);
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($order->fulfillment_status === 'fulfilled')
                                            bg-green-100 text-green-800
                                        @elseif($order->fulfillment_status === 'partial')
                                            bg-yellow-100 text-yellow-800
                                        @elseif($order->fulfillment_status === 'unfulfilled')
                                            bg-gray-100 text-gray-800
                                        @else
                                            bg-gray-100 text-gray-800
                                        @endif">
                                        {{ $fulfillmentLabel }}
                                    </span>
                                @else
                                <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Voir détails -->
                                    <a href="{{ route('orders.show', $order) }}" 
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors"
                                       title="Voir détails">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                    
                                    <!-- Imprimer -->
                                    <a href="{{ route('orders.show', $order) }}" 
                                       onclick="event.preventDefault(); window.open('{{ route('orders.show', $order) }}', '_blank'); setTimeout(() => window.print(), 500);"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors"
                                       title="Imprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                        </svg>
                                    </a>
                                    
                                    @if($order->source === 'shopify' && $order->external_id)
                                    <!-- Voir sur Shopify -->
                                    <a href="https://admin.shopify.com/store/bi0iar-p0/orders/{{ $order->external_id }}" 
                                       target="_blank"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-green-600 hover:bg-green-50 transition-colors"
                                       title="Voir sur Shopify">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                                <p class="mt-4 text-sm text-gray-500">Aucune commande trouvée</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($orders->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $orders->links() }}
            </div>
            @endif
        </div>
    </div>

    <footer class="mt-auto border-t border-gray-200 bg-white py-6 px-4 text-center text-sm text-gray-500">
        <p>© {{ date('Y') }}. Tous droits réservés.</p>
        <p class="mt-1">Synchronisation automatique Shopify activée (toutes les heures)</p>
    </footer>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const columnToggleBtn = document.getElementById('columnToggleBtn');
    const columnToggleMenu = document.getElementById('columnToggleMenu');
    const columnToggles = document.querySelectorAll('.column-toggle');
    const resetColumnsBtn = document.getElementById('resetColumns');
    
    // Toggle dropdown menu
    columnToggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        columnToggleMenu.classList.toggle('hidden');
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!columnToggleMenu.contains(e.target) && !columnToggleBtn.contains(e.target)) {
            columnToggleMenu.classList.add('hidden');
        }
    });
    
    // Load saved column preferences
    function loadColumnPreferences() {
        const savedPrefs = localStorage.getItem('ordersColumnPrefs');
        if (savedPrefs) {
            const prefs = JSON.parse(savedPrefs);
            columnToggles.forEach(checkbox => {
                const columnName = checkbox.dataset.column;
                if (prefs.hasOwnProperty(columnName)) {
                    checkbox.checked = prefs[columnName];
                    toggleColumn(columnName, prefs[columnName]);
                }
            });
        }
    }
    
    // Save column preferences
    function saveColumnPreferences() {
        const prefs = {};
        columnToggles.forEach(checkbox => {
            prefs[checkbox.dataset.column] = checkbox.checked;
        });
        localStorage.setItem('ordersColumnPrefs', JSON.stringify(prefs));
    }
    
    // Toggle column visibility
    function toggleColumn(columnName, isVisible) {
        const columns = document.querySelectorAll('.column-' + columnName);
        columns.forEach(col => {
            if (isVisible) {
                col.style.display = '';
            } else {
                col.style.display = 'none';
            }
        });
    }
    
    // Handle checkbox changes
    columnToggles.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const columnName = this.dataset.column;
            const isVisible = this.checked;
            toggleColumn(columnName, isVisible);
            saveColumnPreferences();
        });
    });
    
    // Reset all columns to visible
    resetColumnsBtn.addEventListener('click', function() {
        columnToggles.forEach(checkbox => {
            checkbox.checked = true;
            toggleColumn(checkbox.dataset.column, true);
        });
        localStorage.removeItem('ordersColumnPrefs');
    });
    
    // Initialize on page load
    loadColumnPreferences();
});
</script>
@endsection
