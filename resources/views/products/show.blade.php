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
                                    @if($product->hasVariants())
                                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $product->ref ?: '—' }}</p>
                                        <p class="mt-1 text-xs text-blue-700 bg-blue-50 rounded px-2 py-1 inline-block">Produit à variantes — le stock est géré par variante (voir tableau ci-dessous)</p>
                                    @else
                                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $product->ref }}</p>
                                    @endif
                                </div>

                                @if($product->barcode && ! $product->hasVariants())
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

                                @if($product->item_kind)
                                    <div>
                                        <span class="text-sm text-gray-500">Type d'article</span>
                                        <p class="mt-1">
                                            @php
                                                $kindColors = [
                                                    'stocked' => 'bg-blue-100 text-blue-800',
                                                    'non_stocked' => 'bg-amber-100 text-amber-800',
                                                    'service' => 'bg-violet-100 text-violet-800',
                                                ];
                                            @endphp
                                            <span class="px-2 py-1 rounded-full text-sm font-medium {{ $kindColors[$product->item_kind] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ $product->item_kind_label }}
                                            </span>
                                        </p>
                                    </div>
                                @elseif($product->element_type)
                                    <div>
                                        <span class="text-sm text-gray-500">Type d'élément</span>
                                        <p class="mt-1 text-gray-900">{{ $product->element_type }}</p>
                                    </div>
                                @endif

                                @if($product->isService() && $product->service_category)
                                    <div>
                                        <span class="text-sm text-gray-500">Catégorie de service</span>
                                        <p class="mt-1 text-gray-900">{{ $product->service_category }}</p>
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
                                        <span class="text-sm text-gray-500">Prix dernier achat TTC (DHS)</span>
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

                        @if($product->tracksStock())
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Gestion des stocks</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <span class="text-sm text-gray-500">Type</span>
                                    <p class="mt-1 text-gray-900 font-medium">Produit stocké</p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">État du stock</span>
                                    <p class="mt-1">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                            {{ $product->isOutOfStock() ? 'bg-red-100 text-red-800' : ($product->isStockLow() ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800') }}">
                                            {{ $product->stock_status_label }}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Quantité disponible</span>
                                    <p class="mt-1 text-lg font-semibold {{ $product->isOutOfStock() ? 'text-red-600' : ($product->isStockLow() ? 'text-orange-600' : 'text-gray-900') }}">{{ $product->available_stock }}</p>
                                    <p class="text-xs text-gray-500">Physique: {{ $product->stock_quantity }} · Réservé: {{ (int) ($product->stock_reserved ?? 0) }}</p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Seuil d'alerte</span>
                                    <p class="mt-1 text-lg font-semibold text-orange-600">{{ $product->alertThreshold() }}</p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Dépôt</span>
                                    <p class="mt-1 text-gray-900">{{ $product->warehouse?->displayLabel() ?: ($product->depot ?: '—') }}</p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Emplacement</span>
                                    <p class="mt-1 text-gray-900">{{ $product->warehouseLocation?->displayLabel() ?: ($product->location ?: '—') }}</p>
                                </div>
                                @if($product->primarySupplier)
                                    <div>
                                        <span class="text-sm text-gray-500">Fournisseur principal</span>
                                        <p class="mt-1 text-gray-900">{{ $product->primarySupplier->name }}</p>
                                    </div>
                                @endif
                            </div>

                            @php
                                $stockMovementService = app(\App\Services\StockMovementService::class);
                                $stockLocations = $stockMovementService->locationBreakdown($product);
                                $physicalTotal = $stockMovementService->physicalTotal($product);
                            @endphp
                            <div class="mt-6">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                    <h3 class="text-sm font-semibold text-gray-800">STOCK</h3>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('stock.movements.index', ['product_id' => $product->id, 'search' => $product->ref]) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50">
                                            Voir les mouvements
                                        </a>
                                        <a href="{{ route('stock.magasin.edit', $product) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-xs font-semibold hover:bg-amber-100">
                                            Ajuster le stock
                                        </a>
                                        <button type="button" onclick="openProductDeclareStock()" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">
                                            + Ajouter / Déclarer du stock physique
                                        </button>
                                        <a href="{{ route('stock.transfer.create', ['product_id' => $product->id]) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800">
                                            Dispatcher / Transférer
                                        </a>
                                    </div>
                                </div>
                                <ul id="productStockList" class="space-y-2">
                                    @foreach($stockLocations as $loc)
                                        <li class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ $loc['is_online'] ? 'bg-emerald-50 text-emerald-900' : 'bg-slate-50 text-slate-800' }}">
                                            <span>{{ $loc['is_online'] ? '🟢' : '🔵' }} {{ $loc['name'] }}</span>
                                            <strong>{{ $loc['quantity'] }}</strong>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="mt-3 text-sm font-semibold text-slate-800">
                                    Total des stocks physiques : <span id="productPhysicalTotal">{{ $physicalTotal }}</span>
                                    <span class="text-xs font-normal text-slate-500">(hors Shopify)</span>
                                </p>
                            </div>
                        </div>
                        @elseif($product->isService())
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations service</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                @if($product->estimated_duration)
                                    <div>
                                        <span class="text-sm text-gray-500">Durée estimée</span>
                                        <p class="mt-1 text-gray-900">{{ $product->estimated_duration }}</p>
                                    </div>
                                @endif
                                @if($product->billing_unit)
                                    <div>
                                        <span class="text-sm text-gray-500">Unité de facturation</span>
                                        <p class="mt-1 text-gray-900">{{ \App\Models\Product::BILLING_UNITS[$product->billing_unit] ?? $product->billing_unit }}</p>
                                    </div>
                                @endif
                                <div>
                                    <span class="text-sm text-gray-500">Technicien requis</span>
                                    <p class="mt-1 text-gray-900">{{ $product->technician_required ? 'Oui' : 'Non' }}</p>
                                </div>
                            </div>
                            <p class="mt-4 text-sm text-violet-700 bg-violet-50 rounded-lg px-3 py-2">Ce service n'est pas géré en stock et ne bloque jamais la vente.</p>
                        </div>
                        @else
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Stock</h2>
                            <p class="text-sm text-amber-800 bg-amber-50 rounded-lg px-3 py-2">Produit non stocké : aucun stock n'est géré, la vente n'est jamais bloquée.</p>
                            @if($product->primarySupplier)
                                <div class="mt-4">
                                    <span class="text-sm text-gray-500">Fournisseur principal</span>
                                    <p class="mt-1 text-gray-900">{{ $product->primarySupplier->name }}</p>
                                </div>
                            @endif
                        </div>
                        @endif

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

                        @if($product->variants->isNotEmpty())
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                                Variantes du produit
                                <span class="ml-2 text-sm font-normal text-gray-500">({{ $product->variants->count() }} variante{{ $product->variants->count() > 1 ? 's' : '' }})</span>
                            </h2>
                            @if($product->hasVariants())
                                <p class="mb-4 text-sm text-gray-600">Chaque variante est un article stockable indépendant. Le stock total du produit parent est un récapitulatif.</p>
                            @endif
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variante</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code-barres</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock total</th>
                                            @if($product->hasVariants() && !empty($variantStockBreakdown))
                                                @foreach(($variantStockBreakdown[0]['locations'] ?? []) as $location)
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $location['name'] }}</th>
                                                @endforeach
                                            @else
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @php
                                            $breakdownByVariant = collect($variantStockBreakdown ?? [])->keyBy('variant_id');
                                        @endphp
                                        @foreach($product->variants as $variant)
                                        @php
                                            $row = $breakdownByVariant->get($variant->id);
                                            $totalStock = $row['total_stock'] ?? $variant->totalStock();
                                        @endphp
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm font-medium text-gray-900">{{ $variant->full_title }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm text-gray-600 font-mono">{{ $variant->sku ?? '-' }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm text-gray-600">{{ $variant->barcode ?? '-' }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm text-gray-900">{{ number_format($variant->price ?? 0, 2) }} DHS</span>
                                                @if($variant->compare_at_price && $variant->compare_at_price > $variant->price)
                                                    <span class="ml-1 text-xs text-gray-400 line-through">{{ number_format($variant->compare_at_price, 2) }} DHS</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if($totalStock <= 0)
                                                    <span class="text-sm font-semibold text-red-600">{{ $totalStock }}</span>
                                                @elseif($totalStock <= 5)
                                                    <span class="text-sm font-semibold text-orange-600">{{ $totalStock }}</span>
                                                @else
                                                    <span class="text-sm text-gray-900">{{ $totalStock }}</span>
                                                @endif
                                            </td>
                                            @if($product->hasVariants() && $row)
                                                @foreach($row['locations'] as $location)
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $location['quantity'] }}</td>
                                                @endforeach
                                            @else
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    @php $qty = $variant->inventory_quantity; @endphp
                                                    @if($qty <= 0)
                                                        <span class="text-sm font-semibold text-red-600">{{ $qty }}</span>
                                                    @elseif($qty <= 5)
                                                        <span class="text-sm font-semibold text-orange-600">{{ $qty }}</span>
                                                    @else
                                                        <span class="text-sm text-gray-900">{{ $qty }}</span>
                                                    @endif
                                                </td>
                                            @endif
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

@if($product->tracksStock())
{{-- Modal déclaration stock physique (fiche produit) --}}
<div id="declareStockModal" class="fixed inset-0 bg-slate-900/40 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-16 mx-auto mb-10 w-[min(32rem,calc(100%-1.5rem))] border border-slate-200 shadow-xl rounded-xl bg-white p-5">
        <div class="flex justify-between items-start gap-3 mb-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Ajouter / Déclarer du stock physique</h3>
                <p class="text-sm text-slate-500">{{ $product->name }} · {{ $product->ref }}</p>
            </div>
            <button type="button" onclick="closeProductDeclareStock()" class="text-slate-400 hover:text-slate-600 text-xl leading-none">×</button>
        </div>
        <div id="declareStockError" class="hidden mb-3 rounded-lg bg-red-50 text-red-800 text-sm px-3 py-2"></div>
        <form id="declareStockForm" action="{{ route('products.declare-stock', $product) }}" method="POST" class="space-y-3">
            @csrf
            @if($product->hasVariants())
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Variante</label>
                <select name="product_variant_id" required class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($product->variants as $variant)
                        <option value="{{ $variant->id }}">{{ $variant->name ?: $variant->sku }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Dépôt de destination</label>
                <select name="warehouse_id" id="declareStockWarehouse" required class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach(\App\Models\Warehouse::query()->active()->physical()->orderByDesc('is_fulfillment_default')->orderBy('name')->get() as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Emplacement <span class="font-normal normal-case">(optionnel)</span></label>
                <select name="warehouse_location_id" id="declareStockLocation" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">— Aucun emplacement —</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Type d’opération</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                        <input type="radio" name="mode" value="add" checked class="mt-1" onchange="syncProductDeclareMode()">
                        <span><span class="font-semibold text-slate-800">Ajouter</span><br><span class="text-xs text-slate-500">Entrée de stock (+)</span></span>
                    </label>
                    <label class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                        <input type="radio" name="mode" value="set" class="mt-1" onchange="syncProductDeclareMode()">
                        <span><span class="font-semibold text-slate-800">Ajuster</span><br><span class="text-xs text-slate-500">Définir la quantité réelle (0 autorisé)</span></span>
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1" id="productDeclareQtyLabel">Quantité physique à ajouter / déclarer</label>
                <input type="number" name="quantity" id="productDeclareQty" min="1" value="1" required class="w-full rounded-lg border-slate-300 text-sm">
                <p id="productDeclareQtyHelp" class="mt-1 text-xs text-slate-500">Minimum 1 pour un ajout.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Motif / origine</label>
                <select name="reason" id="productDeclareReason" required class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach(\App\Models\StockMovement::PHYSICAL_STOCK_REASONS as $value => $label)
                        <option value="{{ $value }}" data-mode="add">{{ $label }}</option>
                    @endforeach
                    @foreach(\App\Models\StockMovement::STOCK_ADJUSTMENT_REASONS as $value => $label)
                        <option value="{{ $value }}" data-mode="set" hidden disabled>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Date</label>
                <input type="date" name="moved_at" id="declareStockDate" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Note <span class="font-normal normal-case">(optionnelle)</span></label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Commentaire libre…"></textarea>
            </div>
            <button type="submit" id="declareStockSubmit" class="w-full px-3 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700">Confirmer l'ajout</button>
        </form>
    </div>
</div>

<script>
var productDeclareLocationsUrl = @json(route('warehouses.locations.json'));
var productLocationStocksUrl = @json(route('products.location-stocks', $product));

function loadProductDeclareLocations(warehouseId) {
    var select = document.getElementById('declareStockLocation');
    if (!select) return;
    select.innerHTML = '<option value="">— Aucun emplacement —</option>';
    if (!warehouseId) return;
    fetch(productDeclareLocationsUrl + '?warehouse_id=' + encodeURIComponent(warehouseId), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            rows.forEach(function (loc) {
                var opt = document.createElement('option');
                opt.value = loc.id;
                opt.textContent = loc.label || loc.code;
                select.appendChild(opt);
            });
        });
}

function renderProductStockList(locations) {
    var list = document.getElementById('productStockList');
    if (!list) return;
    list.innerHTML = (locations || []).map(function (loc) {
        var cls = loc.is_online ? 'bg-emerald-50 text-emerald-900' : 'bg-slate-50 text-slate-800';
        var dot = loc.is_online ? '🟢' : '🔵';
        return '<li class="flex items-center justify-between rounded-lg px-3 py-2 text-sm ' + cls + '">' +
            '<span>' + dot + ' ' + (loc.name || '') + '</span><strong>' + loc.quantity + '</strong></li>';
    }).join('');
}

function syncProductDeclareMode() {
    var modeInput = document.querySelector('#declareStockForm input[name="mode"]:checked');
    var mode = modeInput ? modeInput.value : 'add';
    var qty = document.getElementById('productDeclareQty');
    var help = document.getElementById('productDeclareQtyHelp');
    var label = document.getElementById('productDeclareQtyLabel');
    var submitBtn = document.getElementById('declareStockSubmit');
    var reasonSelect = document.getElementById('productDeclareReason');

    if (reasonSelect) {
        Array.prototype.forEach.call(reasonSelect.options, function (opt) {
            var optMode = opt.getAttribute('data-mode') || 'add';
            var active = optMode === mode;
            opt.hidden = !active;
            opt.disabled = !active;
        });
        var firstActive = Array.prototype.find.call(reasonSelect.options, function (opt) { return !opt.disabled; });
        if (firstActive) reasonSelect.value = firstActive.value;
    }

    if (mode === 'set') {
        if (qty) {
            qty.min = '0';
            if (qty.value === '' || parseInt(qty.value, 10) < 0) qty.value = '0';
        }
        if (label) label.textContent = 'Nouvelle quantité physique';
        if (help) {
            help.textContent = 'Valeur valide : 0 ou plus. Le stock Shopify / En ligne n’est pas modifié.';
            help.className = 'mt-1 text-xs text-emerald-700 font-medium';
        }
        if (submitBtn) submitBtn.textContent = 'Confirmer l’ajustement';
    } else {
        if (qty) {
            qty.min = '1';
            if (!qty.value || parseInt(qty.value, 10) < 1) qty.value = '1';
        }
        if (label) label.textContent = 'Quantité physique à ajouter / déclarer';
        if (help) {
            help.textContent = 'Minimum 1 pour un ajout.';
            help.className = 'mt-1 text-xs text-slate-500';
        }
        if (submitBtn) submitBtn.textContent = "Confirmer l'ajout";
    }
}

function openProductDeclareStock() {
    var modal = document.getElementById('declareStockModal');
    if (!modal) return;
    var modeAdd = document.querySelector('#declareStockForm input[name="mode"][value="add"]');
    if (modeAdd) modeAdd.checked = true;
    syncProductDeclareMode();
    var wh = document.getElementById('declareStockWarehouse');
    if (wh) loadProductDeclareLocations(wh.value);
    modal.classList.remove('hidden');
}

function closeProductDeclareStock() {
    var modal = document.getElementById('declareStockModal');
    if (modal) modal.classList.add('hidden');
    var err = document.getElementById('declareStockError');
    if (err) err.classList.add('hidden');
}

document.getElementById('declareStockWarehouse')?.addEventListener('change', function () {
    loadProductDeclareLocations(this.value);
});

document.getElementById('declareStockForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    var err = document.getElementById('declareStockError');
    var submitBtn = document.getElementById('declareStockSubmit');
    if (err) err.classList.add('hidden');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enregistrement…';
    }

    fetch(form.action, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: new FormData(form)
    })
        .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
        .then(function (result) {
            if (!result.ok || !result.json.success) {
                throw new Error(result.json.message || 'Erreur lors de la déclaration.');
            }
            renderProductStockList(result.json.locations);
            var totalEl = document.getElementById('productPhysicalTotal');
            if (totalEl) totalEl.textContent = result.json.physical_total;
            closeProductDeclareStock();
            form.reset();
            document.getElementById('declareStockDate').value = @json(now()->format('Y-m-d'));
        })
        .catch(function (error) {
            if (err) {
                err.textContent = error.message || 'Erreur lors de la déclaration.';
                err.classList.remove('hidden');
            }
        })
        .finally(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                syncProductDeclareMode();
            }
        });
});

document.getElementById('declareStockModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeProductDeclareStock();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeProductDeclareStock();
});
</script>
@endif

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
