<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du produit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <aside class="w-64 bg-white shadow-lg flex flex-col relative">
            @include('layouts.sidebar')
        </aside>

        <main class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-6">
                    <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                        <a href="{{ route('products.index') }}" class="hover:text-blue-600">Produits</a>
                        <span>/</span>
                        <span class="text-gray-900">Détails du produit</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <h1 class="text-3xl font-bold text-gray-900">{{ $product->name }}</h1>
                        <div class="flex space-x-3">
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
                                <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-full rounded-lg object-cover">
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
                                        <span class="text-sm text-gray-500">Prix de revient HT</span>
                                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($product->cost_price_ht, 2) }} €</p>
                                    </div>
                                @endif

                                @if($product->cost_price_ttc !== null)
                                    <div>
                                        <span class="text-sm text-gray-500">Prix de revient TTC</span>
                                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($product->cost_price_ttc, 2) }} €</p>
                                    </div>
                                @endif

                                @if($product->last_purchase_price !== null)
                                    <div>
                                        <span class="text-sm text-gray-500">Prix dernier achat</span>
                                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($product->last_purchase_price, 2) }} €</p>
                                    </div>
                                @endif

                                @if($product->sale_price !== null)
                                    <div>
                                        <span class="text-sm text-gray-500">Prix de vente</span>
                                        <p class="mt-1 text-xl font-semibold text-green-600">{{ number_format($product->sale_price, 2) }} €</p>
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
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
