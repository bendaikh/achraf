@extends('layouts.app')

@section('content')
<main class="flex-1 overflow-y-auto p-8 bg-gray-100">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Paramètres</h1>
                <p class="text-gray-500 mt-1">Configurez les paramètres généraux de l'application</p>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('settings.update') }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Configuration TVA / Prix Shopify</h2>
                    <p class="text-sm text-gray-500 mt-1">Définissez comment les prix importés depuis Shopify doivent être interprétés</p>
                </div>
                
                <div class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Les prix des produits Shopify sont :</label>
                        
                        <div class="space-y-3">
                            <label class="flex items-center p-4 border rounded-lg cursor-pointer transition-colors {{ $shopifyPriceType === 'ttc' ? 'border-[#fdb819] bg-[#fdb819]/5' : 'border-gray-200 hover:border-gray-300' }}">
                                <input type="radio" name="shopify_price_type" value="ttc" class="h-4 w-4 text-[#fdb819] focus:ring-[#fdb819] border-gray-300" {{ $shopifyPriceType === 'ttc' ? 'checked' : '' }}>
                                <div class="ml-3">
                                    <span class="block text-sm font-medium text-gray-900">TTC (Toutes Taxes Comprises)</span>
                                    <span class="block text-sm text-gray-500">Les prix Shopify incluent déjà la TVA</span>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-4 border rounded-lg cursor-pointer transition-colors {{ $shopifyPriceType === 'ht' ? 'border-[#fdb819] bg-[#fdb819]/5' : 'border-gray-200 hover:border-gray-300' }}">
                                <input type="radio" name="shopify_price_type" value="ht" class="h-4 w-4 text-[#fdb819] focus:ring-[#fdb819] border-gray-300" {{ $shopifyPriceType === 'ht' ? 'checked' : '' }}>
                                <div class="ml-3">
                                    <span class="block text-sm font-medium text-gray-900">HT (Hors Taxes)</span>
                                    <span class="block text-sm text-gray-500">Les prix Shopify sont hors TVA, la TVA sera ajoutée selon le taux applicable</span>
                                </div>
                            </label>
                        </div>
                        
                        @error('shopify_price_type')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex">
                            <svg class="h-5 w-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Impact sur le système</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p>Ce paramètre affecte :</p>
                                    <ul class="list-disc list-inside mt-1 space-y-1">
                                        <li>L'import des produits depuis Shopify</li>
                                        <li>L'affichage des prix dans les documents de vente</li>
                                        <li>Le calcul automatique entre prix HT et TTC</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] transition duration-150 text-sm font-medium">
                    Enregistrer les paramètres
                </button>
            </div>
        </form>
    </div>
</main>
@endsection
