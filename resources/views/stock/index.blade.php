@extends('layouts.with-sidebar')

@section('title', 'Gestion stock')

@section('sidebar_page_title', 'Stock')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
            <div class="p-4 sm:p-6 lg:p-8">
                <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Gestion stock</h1>
                        <p class="text-gray-500 mt-1">Suivez et ajustez les quantités par produit</p>
                    </div>
                    <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition duration-150">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Retour aux produits
                    </a>
                </div>

                @if(session('success'))
                    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <p class="text-green-700">{{ session('success') }}</p>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow border border-gray-100 p-5">
                        <p class="text-sm font-medium text-gray-500">Produits sous seuil</p>
                        <p class="mt-1 text-2xl font-bold {{ $lowStockCount > 0 ? 'text-orange-600' : 'text-gray-900' }}">{{ $lowStockCount }}</p>
                    </div>
                    <div class="bg-white rounded-lg shadow border border-gray-100 p-5 flex items-center">
                        <form method="get" action="{{ route('stock.index') }}" class="flex flex-wrap items-end gap-3 w-full">
                            <div class="flex-1 min-w-[160px]">
                                <label for="q" class="block text-xs font-medium text-gray-500 mb-1">Recherche</label>
                                <input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="Référence ou nom…"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1 invisible">Filtre</label>
                                <div class="flex rounded-lg border border-gray-200 overflow-hidden">
                                    <a href="{{ route('stock.index', array_filter(['q' => request('q')])) }}"
                                        class="px-3 py-2 text-sm {{ request('filter') !== 'low' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">Tous</a>
                                    <a href="{{ route('stock.index', array_filter(['q' => request('q'), 'filter' => 'low'])) }}"
                                        class="px-3 py-2 text-sm border-l border-gray-200 {{ request('filter') === 'low' ? 'bg-orange-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">Sous seuil</a>
                                </div>
                            </div>
                            <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800">Filtrer</button>
                        </form>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Référence</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seuils</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">État</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($products as $product)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $product->ref }}</td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                            @if($product->product_category)
                                                <div class="text-xs text-gray-500">{{ $product->product_category }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($product->isOutOfStock())
                                                <span class="text-sm font-bold text-red-600">{{ $product->stock_quantity }}</span>
                                            @elseif($product->isStockLow())
                                                <span class="text-sm font-bold text-orange-600">{{ $product->stock_quantity }}</span>
                                            @else
                                                <span class="text-sm text-gray-900">{{ $product->stock_quantity }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                            @if($product->minimum_alert_stock !== null || $product->minimum_safety_stock !== null)
                                                <span>Alerte : {{ $product->minimum_alert_stock ?? '—' }}</span>
                                                <span class="mx-1 text-gray-300">|</span>
                                                <span>Sécurité : {{ $product->minimum_safety_stock ?? '—' }}</span>
                                            @else
                                                <span class="text-gray-400">Non défini</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($product->isOutOfStock())
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Rupture</span>
                                            @elseif($product->isStockLow())
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Sous seuil</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">OK</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <a href="{{ route('stock.edit', $product) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-gradient-to-r from-blue-600 to-purple-600 text-white text-xs font-medium hover:from-blue-700 hover:to-purple-700">
                                                Ajuster
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">Aucun produit ne correspond à votre recherche.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($products->hasPages())
                        <div class="px-6 py-4 border-t border-gray-200">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </main>
@endsection
