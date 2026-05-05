@extends('layouts.with-sidebar')

@section('title', 'Détails du produit')

@section('sidebar_page_title', 'Produit')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
            <div class="p-4 sm:p-6 lg:p-8">
                <div class="mb-6">
                    <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                        <a href="{{ route('products.index') }}" class="hover:text-blue-600">Produits</a>
                        <span>/</span>
                        <span class="text-gray-900">Détails du produit</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <h1 class="text-3xl font-bold text-gray-900">{{ $product->name }}</h1>
                        <div class="flex space-x-3">
                            @if($product->isShopifyProduct())
                            <button type="button" 
                                    onclick="openDuplicateModal()" 
                                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition duration-150 flex items-center space-x-2">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                <span>Dupliquer en Manuel</span>
                            </button>
                            @endif
                            <a href="{{ route('products.edit', $product) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150 flex items-center space-x-2">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                <span>Modifier</span>
                            </a>
                            <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-150 flex items-center space-x-2">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    <span>Supprimer</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Image du produit</h2>
                            @if($product->image)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full rounded-lg object-cover">
                            @else
                                <div class="w-full h-64 rounded-lg bg-gray-200 flex items-center justify-center">
                                    <svg class="h-24 w-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                </div>
                            @endif

                            <div class="mt-6 space-y-4">
                                <div>
                                    <span class="text-sm text-gray-500">Statut</span>
                                    <p class="mt-1">
                                        <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full {{ $product->status === 'Activer' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $product->status ?? 'N/A' }}
                                        </span>
                                    </p>
                                </div>

                                <div>
                                    <span class="text-sm text-gray-500">Référence</span>
                                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ $product->ref }}</p>
                                </div>

                                @if($product->barcode)
                                    <div>
                                        <span class="text-sm text-gray-500">Code-Barres</span>
                                        <p class="mt-1 text-gray-900">{{ $product->barcode }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations générales</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <span class="text-sm text-gray-500">Nom du produit</span>
                                    <p class="mt-1 text-gray-900 font-medium">{{ $product->name }}</p>
                                </div>

                                @if($product->product_category)
                                    <div>
                                        <span class="text-sm text-gray-500">Catégorie produit</span>
                                        <p class="mt-1 text-gray-900">{{ $product->product_category }}</p>
                                    </div>
                                @endif

                                @if($product->element_type)
                                    <div>
                                        <span class="text-sm text-gray-500">Type d'élément</span>
                                        <p class="mt-1 text-gray-900">{{ $product->element_type }}</p>
                                    </div>
                                @endif

                                @if($product->tag)
                                    <div>
                                        <span class="text-sm text-gray-500">Tag</span>
                                        <p class="mt-1">
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">{{ $product->tag }}</span>
                                        </p>
                                    </div>
                                @endif
                            </div>

                            @if($product->description)
                                <div class="mt-6">
                                    <span class="text-sm text-gray-500">Description</span>
                                    <p class="mt-1 text-gray-900">{{ $product->description }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Tarification</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                @if($product->cost_price_ht !== null)
                                    <div>
                                        <span class="text-sm text-gray-500">Prix de revient HT (DHS)</span>
                                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($product->cost_price_ht, 2) }} DHS</p>
                                    </div>
                                @endif

                                @if($product->cost_price_ttc !== null)
                                    <div>
                                        <span class="text-sm text-gray-500">Prix de revient TTC (DHS)</span>
                                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($product->cost_price_ttc, 2) }} DHS</p>
                                    </div>
                                @endif

                                @if($product->last_purchase_price !== null)
                                    <div>
                                        <span class="text-sm text-gray-500">Prix dernier achat (DHS)</span>
                                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($product->last_purchase_price, 2) }} DHS</p>
                                    </div>
                                @endif

                                @if($product->sale_price !== null)
                                    <div>
                                        <span class="text-sm text-gray-500">Prix de vente (DHS)</span>
                                        <p class="mt-1 text-xl font-semibold text-green-600">{{ number_format($product->sale_price, 2) }} DHS</p>
                                    </div>
                                @endif

                                @if($product->vat_category)
                                    <div>
                                        <span class="text-sm text-gray-500">Catégorie TVA</span>
                                        <p class="mt-1 text-gray-900">{{ $product->vat_category }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Gestion des stocks</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <span class="text-sm text-gray-500">Quantité en stock</span>
                                    <p class="mt-1 text-lg font-semibold {{ $product->isOutOfStock() ? 'text-red-600' : ($product->isStockLow() ? 'text-orange-600' : 'text-gray-900') }}">{{ $product->stock_quantity }}</p>
                                </div>
                                @if($product->minimum_safety_stock !== null)
                                    <div>
                                        <span class="text-sm text-gray-500">Stock minimum de sécurité</span>
                                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $product->minimum_safety_stock }}</p>
                                    </div>
                                @endif

                                @if($product->minimum_alert_stock !== null)
                                    <div>
                                        <span class="text-sm text-gray-500">Stock minimum d'alerte</span>
                                        <p class="mt-1 text-lg font-semibold text-orange-600">{{ $product->minimum_alert_stock }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations système</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <span class="text-sm text-gray-500">Date de création</span>
                                    <p class="mt-1 text-gray-900">{{ $product->created_at->format('d/m/Y H:i') }}</p>
                                </div>

                                <div>
                                    <span class="text-sm text-gray-500">Dernière modification</span>
                                    <p class="mt-1 text-gray-900">{{ $product->updated_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        </div>

                        @if($product->isShopifyProduct() && $product->variants->count() > 0)
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                                Variantes Shopify
                                <span class="ml-2 text-sm font-normal text-gray-500">({{ $product->variants->count() }} variante{{ $product->variants->count() > 1 ? 's' : '' }})</span>
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variante</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code-barres</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($product->variants as $variant)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm font-medium text-gray-900">{{ $variant->full_title }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm text-gray-600">{{ $variant->sku ?? '-' }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm text-gray-900">{{ number_format($variant->price ?? 0, 2) }} DHS</span>
                                                @if($variant->compare_at_price && $variant->compare_at_price > $variant->price)
                                                    <span class="ml-1 text-xs text-gray-400 line-through">{{ number_format($variant->compare_at_price, 2) }} DHS</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if($variant->inventory_quantity <= 0)
                                                    <span class="text-sm font-semibold text-red-600">{{ $variant->inventory_quantity }}</span>
                                                @elseif($variant->inventory_quantity <= 5)
                                                    <span class="text-sm font-semibold text-orange-600">{{ $variant->inventory_quantity }}</span>
                                                @else
                                                    <span class="text-sm text-gray-900">{{ $variant->inventory_quantity }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm text-gray-600">{{ $variant->barcode ?? '-' }}</span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </main>

@if($product->isShopifyProduct())
<!-- Modal de duplication Shopify vers Manuel -->
<div id="duplicateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Dupliquer en produit manuel</h3>
                <button type="button" onclick="closeDuplicateModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form action="{{ route('products.duplicate-to-manual', $product) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">Vous allez créer une copie manuelle du produit Shopify :</p>
                    <p class="font-medium text-gray-900">{{ $product->name }}</p>
                    <p class="text-sm text-gray-500">Réf: {{ $product->ref }}</p>
                </div>

                <div class="mb-4">
                    <label for="initial_stock" class="block text-sm font-medium text-gray-700 mb-1">Stock initial *</label>
                    <input type="number" 
                           name="initial_stock" 
                           id="initial_stock" 
                           min="0" 
                           value="0" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="mt-1 text-xs text-gray-500">Définissez la quantité de stock initiale pour le produit manuel.</p>
                </div>

                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <p class="ml-2 text-sm text-yellow-700">
                            Le produit manuel aura la référence : <strong>{{ $product->ref }}-MANUAL</strong>
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDuplicateModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                        Dupliquer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openDuplicateModal() {
    document.getElementById('duplicateModal').classList.remove('hidden');
    document.getElementById('initial_stock').value = 0;
    document.getElementById('initial_stock').focus();
}

function closeDuplicateModal() {
    document.getElementById('duplicateModal').classList.add('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDuplicateModal();
    }
});

document.getElementById('duplicateModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDuplicateModal();
    }
});
</script>
@endif
@endsection
