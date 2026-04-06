@extends('layouts.with-sidebar')

@section('title', 'Modifier le produit')

@section('sidebar_page_title', 'Modifier produit')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
            <div class="p-4 sm:p-6 lg:p-8">
                <div class="mb-6">
                    <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                        <a href="{{ route('products.index') }}" class="hover:text-blue-600">Produits</a>
                        <span>/</span>
                        <span class="text-gray-900">Modifier le produit</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Modifier le produit</h1>
                </div>

                @if($errors->any())
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-red-700 font-medium">Il y a des erreurs dans le formulaire:</p>
                                <ul class="list-disc list-inside text-red-600 mt-2">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6">
                    @csrf
                    @method('PUT')

                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Image Produit</label>
                        <div x-data="{ imagePreview: '{{ $product->image ? asset('storage/' . $product->image) : null }}' }" class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <template x-if="imagePreview">
                                    <img :src="imagePreview" class="h-32 w-32 rounded-lg object-cover border-2 border-gray-200">
                                </template>
                                <template x-if="!imagePreview">
                                    <div class="h-32 w-32 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center bg-gray-50">
                                        <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                </template>
                            </div>
                            <label class="cursor-pointer px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150">
                                <span class="text-blue-600">Choisissez Fichier</span>
                                <input 
                                    type="file" 
                                    name="image" 
                                    accept="image/*" 
                                    class="hidden"
                                    @change="
                                        const file = $event.target.files[0];
                                        if (file) {
                                            const reader = new FileReader();
                                            reader.onload = (e) => imagePreview = e.target.result;
                                            reader.readAsDataURL(file);
                                        }
                                    "
                                >
                            </label>
                        </div>
                        @error('image')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Nom du produit <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                id="name" 
                                value="{{ old('name', $product->name) }}" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror"
                            >
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="cost_price_ht" class="block text-sm font-medium text-gray-700 mb-1">Prix de revient HT</label>
                            <input 
                                type="number" 
                                name="cost_price_ht" 
                                id="cost_price_ht" 
                                value="{{ old('cost_price_ht', $product->cost_price_ht) }}" 
                                step="0.01"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('cost_price_ht') border-red-500 @enderror"
                            >
                            @error('cost_price_ht')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="minimum_safety_stock" class="block text-sm font-medium text-gray-700 mb-1">Stock minimum de sécurité</label>
                            <input 
                                type="number" 
                                name="minimum_safety_stock" 
                                id="minimum_safety_stock" 
                                value="{{ old('minimum_safety_stock', $product->minimum_safety_stock) }}" 
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('minimum_safety_stock') border-red-500 @enderror"
                            >
                            @error('minimum_safety_stock')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label for="ref" class="block text-sm font-medium text-gray-700 mb-1">
                                Référence # <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="ref" 
                                id="ref" 
                                value="{{ old('ref', $product->ref) }}" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('ref') border-red-500 @enderror"
                            >
                            @error('ref')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="cost_price_ttc" class="block text-sm font-medium text-gray-700 mb-1">Prix de Revient TTC</label>
                            <input 
                                type="number" 
                                name="cost_price_ttc" 
                                id="cost_price_ttc" 
                                value="{{ old('cost_price_ttc', $product->cost_price_ttc) }}" 
                                step="0.01"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('cost_price_ttc') border-red-500 @enderror"
                            >
                            @error('cost_price_ttc')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="last_purchase_price" class="block text-sm font-medium text-gray-700 mb-1">Prix dernier achat</label>
                            <input 
                                type="number" 
                                name="last_purchase_price" 
                                id="last_purchase_price" 
                                value="{{ old('last_purchase_price', $product->last_purchase_price) }}" 
                                step="0.01"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('last_purchase_price') border-red-500 @enderror"
                            >
                            @error('last_purchase_price')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <div>
                            <label for="minimum_alert_stock" class="block text-sm font-medium text-gray-700 mb-1">Stock minimum d'alerte</label>
                            <input 
                                type="number" 
                                name="minimum_alert_stock" 
                                id="minimum_alert_stock" 
                                value="{{ old('minimum_alert_stock', $product->minimum_alert_stock) }}" 
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('minimum_alert_stock') border-red-500 @enderror"
                            >
                            @error('minimum_alert_stock')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="stock_quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantité en stock</label>
                            <input 
                                type="number" 
                                name="stock_quantity" 
                                id="stock_quantity" 
                                value="{{ old('stock_quantity', $product->stock_quantity) }}" 
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('stock_quantity') border-red-500 @enderror"
                            >
                            @error('stock_quantity')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="barcode" class="block text-sm font-medium text-gray-700 mb-1">Code-Barres</label>
                            <input 
                                type="text" 
                                name="barcode" 
                                id="barcode" 
                                value="{{ old('barcode', $product->barcode) }}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('barcode') border-red-500 @enderror"
                            >
                            @error('barcode')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sale_price" class="block text-sm font-medium text-gray-700 mb-1">Prix de Vente</label>
                            <input 
                                type="number" 
                                name="sale_price" 
                                id="sale_price" 
                                value="{{ old('sale_price', $product->sale_price) }}" 
                                step="0.01"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('sale_price') border-red-500 @enderror"
                            >
                            @error('sale_price')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label for="vat_category" class="block text-sm font-medium text-gray-700 mb-1">Catégorie TVA</label>
                            <select 
                                name="vat_category" 
                                id="vat_category" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('vat_category') border-red-500 @enderror"
                            >
                                <option value="">Sélectionner...</option>
                                <option value="TVA (20%)" {{ old('vat_category', $product->vat_category) == 'TVA (20%)' ? 'selected' : '' }}>TVA (20%)</option>
                                <option value="TVA (10%)" {{ old('vat_category', $product->vat_category) == 'TVA (10%)' ? 'selected' : '' }}>TVA (10%)</option>
                                <option value="TVA (5.5%)" {{ old('vat_category', $product->vat_category) == 'TVA (5.5%)' ? 'selected' : '' }}>TVA (5.5%)</option>
                                <option value="TVA (2.1%)" {{ old('vat_category', $product->vat_category) == 'TVA (2.1%)' ? 'selected' : '' }}>TVA (2.1%)</option>
                            </select>
                            @error('vat_category')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="element_type" class="block text-sm font-medium text-gray-700 mb-1">Type d'élément</label>
                            <select 
                                name="element_type" 
                                id="element_type" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('element_type') border-red-500 @enderror"
                            >
                                <option value="">Sélectionner...</option>
                                <option value="Produit" {{ old('element_type', $product->element_type) == 'Produit' ? 'selected' : '' }}>Produit</option>
                                <option value="Service" {{ old('element_type', $product->element_type) == 'Service' ? 'selected' : '' }}>Service</option>
                            </select>
                            @error('element_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="tag" class="block text-sm font-medium text-gray-700 mb-1">Tag</label>
                            <input 
                                type="text" 
                                name="tag" 
                                id="tag" 
                                value="{{ old('tag', $product->tag) }}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('tag') border-red-500 @enderror"
                            >
                            @error('tag')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                            <select 
                                name="status" 
                                id="status" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('status') border-red-500 @enderror"
                            >
                                <option value="Activer" {{ old('status', $product->status) == 'Activer' ? 'selected' : '' }}>Activer</option>
                                <option value="Désactiver" {{ old('status', $product->status) == 'Désactiver' ? 'selected' : '' }}>Désactiver</option>
                            </select>
                            @error('status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="product_category" class="block text-sm font-medium text-gray-700 mb-1">Catégorie produit</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    name="product_category" 
                                    id="product_category" 
                                    value="{{ old('product_category', $product->product_category) }}" 
                                    placeholder="AUCUNE SÉLECTION"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('product_category') border-red-500 @enderror"
                                >
                            </div>
                            @error('product_category')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea 
                            name="description" 
                            id="description" 
                            rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('description') border-red-500 @enderror"
                        >{{ old('description', $product->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                        <a href="{{ route('products.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition duration-150">
                            Annuler
                        </a>
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition duration-150">
                            Mettre à jour
                        </button>
                    </div>
                </form>
            </div>
        </main>
@endsection
