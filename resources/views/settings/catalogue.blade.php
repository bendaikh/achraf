@extends('layouts.with-sidebar')

@section('title', 'Catalogue — Paramètres')
@section('sidebar_page_title', 'Paramètres')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-6 lg:p-8 max-w-6xl mx-auto pb-16">
        @include('settings.partials.alerts')

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-start gap-3 px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-[#0a5d8a]/10 text-[#0a5d8a]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Catalogue</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Numérotation produits, types d'élément, catégories et prix Shopify</p>
                </div>
            </div>

            <div class="p-6 space-y-8">
                <div id="settings-produit">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="settings_type" value="produit">

                        <div class="space-y-6">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 mb-1">Numérotation produit</h3>
                                <p class="text-sm text-gray-500 mb-4">Configuration du code généré à la création d'un produit</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="produit_next_number" class="block text-sm font-medium text-gray-700 mb-1">Prochain numéro de produit</label>
                                        <input type="text" name="produit_next_number" id="produit_next_number" value="{{ $settings['produit_next_number'] ?? '1' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                                    </div>
                                    <div>
                                        <label for="produit_format" class="block text-sm font-medium text-gray-700 mb-1">
                                            Format de numérotation de produit
                                            <span class="inline-block ml-1 text-gray-400 cursor-help" title="Utilisez {NUMBER} pour le numéro, {YEAR} pour l'année">ⓘ</span>
                                        </label>
                                        <input type="text" name="produit_format" id="produit_format" value="{{ $settings['produit_format'] ?? 'PRD-{NUMBER}' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                                    </div>
                                    <div>
                                        <label for="produit_code_length" class="block text-sm font-medium text-gray-700 mb-1">Longueur du code (zéros à gauche si besoin)</label>
                                        <input type="number" name="produit_code_length" id="produit_code_length" value="{{ $settings['produit_code_length'] ?? '6' }}" min="1" max="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                                    </div>
                                    <div>
                                        <label for="produit_reset_period" class="block text-sm font-medium text-gray-700 mb-1">Réinitialiser numérotation</label>
                                        <select name="produit_reset_period" id="produit_reset_period" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                                            <option value="never" {{ ($settings['produit_reset_period'] ?? 'never') === 'never' ? 'selected' : '' }}>Jamais</option>
                                            <option value="yearly" {{ ($settings['produit_reset_period'] ?? 'never') === 'yearly' ? 'selected' : '' }}>Chaque Année</option>
                                            <option value="monthly" {{ ($settings['produit_reset_period'] ?? 'never') === 'monthly' ? 'selected' : '' }}>Chaque Mois</option>
                                        </select>
                                    </div>
                                </div>
                                <label class="mt-4 flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 cursor-pointer hover:border-[#0a5d8a]/40 transition-colors max-w-xl">
                                    <input type="checkbox" name="produit_apply_to_old" id="produit_apply_to_old" value="1" {{ ($settings['produit_apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                                    <span class="text-sm text-gray-700">Appliquer le nouveau format aux anciens produits</span>
                                </label>
                            </div>

                            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                                <div class="flex gap-3">
                                    <svg class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <h3 class="text-sm font-medium text-blue-800">Configuration Prix Shopify</h3>
                                        <div class="mt-3 space-y-2 text-sm text-blue-700">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="radio" name="shopify_price_type" value="ttc" class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300" {{ ($settings['shopify_price_type'] ?? 'ttc') === 'ttc' ? 'checked' : '' }}>
                                                <span>Prix TTC (Toutes Taxes Comprises)</span>
                                            </label>
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="radio" name="shopify_price_type" value="ht" class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300" {{ ($settings['shopify_price_type'] ?? 'ttc') === 'ht' ? 'checked' : '' }}>
                                                <span>Prix HT (Hors Taxes)</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="sticky bottom-0 flex justify-end border-t border-gray-100 bg-white/95 backdrop-blur -mx-6 px-6 py-3">
                                <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium shadow-sm">
                                    Enregistrer produit
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div id="settings-produit-types" class="border-t border-gray-100 pt-8">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="settings_type" value="produit_types">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Types d'élément (produits)</h3>
                        <p class="text-sm text-gray-500 mb-3">Une valeur par ligne. Ces types apparaissent dans le formulaire de création de produit.</p>
                        <label for="product_element_types" class="block text-sm font-medium text-gray-700 mb-1">Types d'élément</label>
                        <textarea name="product_element_types" id="product_element_types" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['product_element_types'] ?? "Produit\nService" }}</textarea>
                        <div class="sticky bottom-0 flex justify-end mt-4 pt-3">
                            <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium shadow-sm">
                                Enregistrer les types
                            </button>
                        </div>
                    </form>
                </div>

                <div id="settings-type-produit" class="border-t border-gray-100 pt-8">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="settings_type" value="product_type_categories">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Catégories de type produit</h3>
                        <p class="text-sm text-gray-500 mb-3">Une catégorie par ligne. Proposées lors de la création de produit.</p>
                        <textarea name="product_type_categories" rows="8" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['product_type_categories'] ?? '' }}</textarea>
                        <div class="sticky bottom-0 flex justify-end mt-4 pt-3">
                            <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium shadow-sm">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
