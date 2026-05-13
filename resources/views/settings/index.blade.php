@extends('layouts.with-sidebar')

@section('title', 'Paramètres')

@section('sidebar_page_title', 'Paramètres')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100" x-data="{ activeTab: '{{ request('tab', 'facture') }}' }">
    <div class="flex h-full">
        <!-- Settings Sidebar -->
        <div class="w-64 bg-white border-r border-gray-200 flex-shrink-0">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Paramètres</h2>
                <p class="text-sm text-gray-500">Configuration des documents</p>
            </div>
            <nav class="p-2 space-y-1">
                <button @click="activeTab = 'facture'" :class="activeTab === 'facture' ? 'bg-[#0a5d8a] text-white' : 'text-gray-700 hover:bg-gray-100'" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition duration-150 text-left">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="font-medium">Facture</span>
                </button>
                <button @click="activeTab = 'devis'" :class="activeTab === 'devis' ? 'bg-[#0a5d8a] text-white' : 'text-gray-700 hover:bg-gray-100'" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition duration-150 text-left">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="font-medium">Devis</span>
                </button>
                <button @click="activeTab = 'avoir'" :class="activeTab === 'avoir' ? 'bg-[#0a5d8a] text-white' : 'text-gray-700 hover:bg-gray-100'" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition duration-150 text-left">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                    </svg>
                    <span class="font-medium">Avoir</span>
                </button>
                <button @click="activeTab = 'bc_fournisseur'" :class="activeTab === 'bc_fournisseur' ? 'bg-[#0a5d8a] text-white' : 'text-gray-700 hover:bg-gray-100'" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition duration-150 text-left">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    <span class="font-medium">Bon de Commande Fournisseur</span>
                </button>
                <button @click="activeTab = 'bc_client'" :class="activeTab === 'bc_client' ? 'bg-[#0a5d8a] text-white' : 'text-gray-700 hover:bg-gray-100'" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition duration-150 text-left">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <span class="font-medium">Bon de Commande Client</span>
                </button>
                <button @click="activeTab = 'bon_livraison'" :class="activeTab === 'bon_livraison' ? 'bg-[#0a5d8a] text-white' : 'text-gray-700 hover:bg-gray-100'" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition duration-150 text-left">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                    </svg>
                    <span class="font-medium">Bon de Livraison</span>
                </button>
                <button @click="activeTab = 'bon_reception'" :class="activeTab === 'bon_reception' ? 'bg-[#0a5d8a] text-white' : 'text-gray-700 hover:bg-gray-100'" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition duration-150 text-left">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <span class="font-medium">Bon de Réception</span>
                </button>
                <button @click="activeTab = 'produit'" :class="activeTab === 'produit' ? 'bg-[#0a5d8a] text-white' : 'text-gray-700 hover:bg-gray-100'" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition duration-150 text-left">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span class="font-medium">Produit</span>
                </button>
            </nav>
        </div>

        <!-- Settings Content -->
        <div class="flex-1 p-8 overflow-y-auto">
            @if (session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Facture Settings -->
            <div x-show="activeTab === 'facture'" x-cloak>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_type" value="facture">
                    
                    <div class="space-y-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm font-medium text-blue-800">Aperçu du prochain numéro</p>
                            <p class="text-lg font-bold text-blue-900 mt-1">{{ $previews['facture'] ?? 'FA-2026/000001' }}</p>
                        </div>

                        <div>
                            <label for="facture_next_number" class="block text-sm font-medium text-gray-700 mb-1">Prochain numéro de facture</label>
                            <input type="text" name="facture_next_number" id="facture_next_number" value="{{ $settings['facture_next_number'] ?? '1' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="facture_format" class="block text-sm font-medium text-gray-700 mb-1">
                                Format de numérotation de facture
                                <span class="inline-block ml-1 text-gray-400 cursor-help" title="Utilisez {NUMBER} pour le numéro, {YEAR} pour l'année">ⓘ</span>
                            </label>
                            <input type="text" name="facture_format" id="facture_format" value="{{ $settings['facture_format'] ?? '{NUMBER}' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="facture_apply_to_old" id="facture_apply_to_old" value="1" {{ ($settings['facture_apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                            <label for="facture_apply_to_old" class="ml-2 text-sm text-gray-700">Appliquer le nouveau format aux anciens documents</label>
                        </div>

                        <div>
                            <label for="facture_year" class="block text-sm font-medium text-gray-700 mb-1">Année de facturation (YYYY/000001)</label>
                            <input type="text" name="facture_year" id="facture_year" value="{{ $settings['facture_year'] ?? date('Y') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="facture_code_length" class="block text-sm font-medium text-gray-700 mb-1">Nombre de caractères que doit contenir le code généré, complété des zéros si nécessaire</label>
                            <input type="number" name="facture_code_length" id="facture_code_length" value="{{ $settings['facture_code_length'] ?? '6' }}" min="1" max="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="facture_reset_period" class="block text-sm font-medium text-gray-700 mb-1">Réinitialiser numérotation de facture</label>
                            <select name="facture_reset_period" id="facture_reset_period" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                                <option value="never" {{ ($settings['facture_reset_period'] ?? 'yearly') === 'never' ? 'selected' : '' }}>Jamais</option>
                                <option value="yearly" {{ ($settings['facture_reset_period'] ?? 'yearly') === 'yearly' ? 'selected' : '' }}>Chaque Année</option>
                                <option value="monthly" {{ ($settings['facture_reset_period'] ?? 'yearly') === 'monthly' ? 'selected' : '' }}>Chaque Mois</option>
                            </select>
                        </div>

                        <div>
                            <label for="facture_conditions" class="block text-sm font-medium text-gray-700 mb-1">Conditions et modalités par défaut</label>
                            <textarea name="facture_conditions" id="facture_conditions" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['facture_conditions'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label for="facture_remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarques client par défaut</label>
                            <textarea name="facture_remarks" id="facture_remarks" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['facture_remarks'] ?? '' }}</textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium">
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Devis Settings -->
            <div x-show="activeTab === 'devis'" x-cloak>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_type" value="devis">
                    
                    <div class="space-y-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm font-medium text-blue-800">Aperçu du prochain numéro</p>
                            <p class="text-lg font-bold text-blue-900 mt-1">{{ $previews['devis'] ?? 'DV-2026/000001' }}</p>
                        </div>

                        <div>
                            <label for="devis_next_number" class="block text-sm font-medium text-gray-700 mb-1">Prochain numéro de devis</label>
                            <input type="text" name="devis_next_number" id="devis_next_number" value="{{ $settings['devis_next_number'] ?? '1' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="devis_format" class="block text-sm font-medium text-gray-700 mb-1">
                                Format de numérotation de devis
                                <span class="inline-block ml-1 text-gray-400 cursor-help" title="Utilisez {NUMBER} pour le numéro, {YEAR} pour l'année">ⓘ</span>
                            </label>
                            <input type="text" name="devis_format" id="devis_format" value="{{ $settings['devis_format'] ?? '{NUMBER}' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="devis_apply_to_old" id="devis_apply_to_old" value="1" {{ ($settings['devis_apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                            <label for="devis_apply_to_old" class="ml-2 text-sm text-gray-700">Appliquer le nouveau format aux anciens documents</label>
                        </div>

                        <div>
                            <label for="devis_year" class="block text-sm font-medium text-gray-700 mb-1">Année de devis (YYYY/000001)</label>
                            <input type="text" name="devis_year" id="devis_year" value="{{ $settings['devis_year'] ?? date('Y') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="devis_code_length" class="block text-sm font-medium text-gray-700 mb-1">Nombre de caractères que doit contenir le code généré, complété des zéros si nécessaire</label>
                            <input type="number" name="devis_code_length" id="devis_code_length" value="{{ $settings['devis_code_length'] ?? '6' }}" min="1" max="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="devis_reset_period" class="block text-sm font-medium text-gray-700 mb-1">Réinitialiser numérotation de devis</label>
                            <select name="devis_reset_period" id="devis_reset_period" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                                <option value="never" {{ ($settings['devis_reset_period'] ?? 'yearly') === 'never' ? 'selected' : '' }}>Jamais</option>
                                <option value="yearly" {{ ($settings['devis_reset_period'] ?? 'yearly') === 'yearly' ? 'selected' : '' }}>Chaque Année</option>
                                <option value="monthly" {{ ($settings['devis_reset_period'] ?? 'yearly') === 'monthly' ? 'selected' : '' }}>Chaque Mois</option>
                            </select>
                        </div>

                        <div>
                            <label for="devis_conditions" class="block text-sm font-medium text-gray-700 mb-1">Conditions et modalités par défaut</label>
                            <textarea name="devis_conditions" id="devis_conditions" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['devis_conditions'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label for="devis_remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarques client par défaut</label>
                            <textarea name="devis_remarks" id="devis_remarks" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['devis_remarks'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label for="devis_validity_days" class="block text-sm font-medium text-gray-700 mb-1">Durée de validité du devis (en jours)</label>
                            <input type="number" name="devis_validity_days" id="devis_validity_days" value="{{ $settings['devis_validity_days'] ?? '30' }}" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium">
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Avoir Settings -->
            <div x-show="activeTab === 'avoir'" x-cloak>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_type" value="avoir">
                    
                    <div class="space-y-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm font-medium text-blue-800">Aperçu du prochain numéro</p>
                            <p class="text-lg font-bold text-blue-900 mt-1">{{ $previews['avoir'] ?? 'AV-2026/000001' }}</p>
                        </div>

                        <div>
                            <label for="avoir_next_number" class="block text-sm font-medium text-gray-700 mb-1">Prochain numéro d'avoir</label>
                            <input type="text" name="avoir_next_number" id="avoir_next_number" value="{{ $settings['avoir_next_number'] ?? '1' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="avoir_format" class="block text-sm font-medium text-gray-700 mb-1">
                                Format de numérotation d'avoir
                                <span class="inline-block ml-1 text-gray-400 cursor-help" title="Utilisez {NUMBER} pour le numéro, {YEAR} pour l'année">ⓘ</span>
                            </label>
                            <input type="text" name="avoir_format" id="avoir_format" value="{{ $settings['avoir_format'] ?? '{NUMBER}' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="avoir_apply_to_old" id="avoir_apply_to_old" value="1" {{ ($settings['avoir_apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                            <label for="avoir_apply_to_old" class="ml-2 text-sm text-gray-700">Appliquer le nouveau format aux anciens documents</label>
                        </div>

                        <div>
                            <label for="avoir_year" class="block text-sm font-medium text-gray-700 mb-1">Année d'avoir (YYYY/000001)</label>
                            <input type="text" name="avoir_year" id="avoir_year" value="{{ $settings['avoir_year'] ?? date('Y') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="avoir_code_length" class="block text-sm font-medium text-gray-700 mb-1">Nombre de caractères que doit contenir le code généré, complété des zéros si nécessaire</label>
                            <input type="number" name="avoir_code_length" id="avoir_code_length" value="{{ $settings['avoir_code_length'] ?? '6' }}" min="1" max="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="avoir_reset_period" class="block text-sm font-medium text-gray-700 mb-1">Réinitialiser numérotation d'avoir</label>
                            <select name="avoir_reset_period" id="avoir_reset_period" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                                <option value="never" {{ ($settings['avoir_reset_period'] ?? 'yearly') === 'never' ? 'selected' : '' }}>Jamais</option>
                                <option value="yearly" {{ ($settings['avoir_reset_period'] ?? 'yearly') === 'yearly' ? 'selected' : '' }}>Chaque Année</option>
                                <option value="monthly" {{ ($settings['avoir_reset_period'] ?? 'yearly') === 'monthly' ? 'selected' : '' }}>Chaque Mois</option>
                            </select>
                        </div>

                        <div>
                            <label for="avoir_conditions" class="block text-sm font-medium text-gray-700 mb-1">Conditions et modalités par défaut</label>
                            <textarea name="avoir_conditions" id="avoir_conditions" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['avoir_conditions'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label for="avoir_remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarques client par défaut</label>
                            <textarea name="avoir_remarks" id="avoir_remarks" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['avoir_remarks'] ?? '' }}</textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium">
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- BC Fournisseur Settings -->
            <div x-show="activeTab === 'bc_fournisseur'" x-cloak>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_type" value="bc_fournisseur">
                    
                    <div class="space-y-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm font-medium text-blue-800">Aperçu du prochain numéro</p>
                            <p class="text-lg font-bold text-blue-900 mt-1">{{ $previews['bc_fournisseur'] ?? 'BCF-2026/000001' }}</p>
                        </div>

                        <div>
                            <label for="bc_fournisseur_next_number" class="block text-sm font-medium text-gray-700 mb-1">Prochain numéro de BC fournisseur</label>
                            <input type="text" name="bc_fournisseur_next_number" id="bc_fournisseur_next_number" value="{{ $settings['bc_fournisseur_next_number'] ?? '1' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bc_fournisseur_format" class="block text-sm font-medium text-gray-700 mb-1">
                                Format de numérotation de BC fournisseur
                                <span class="inline-block ml-1 text-gray-400 cursor-help" title="Utilisez {NUMBER} pour le numéro, {YEAR} pour l'année">ⓘ</span>
                            </label>
                            <input type="text" name="bc_fournisseur_format" id="bc_fournisseur_format" value="{{ $settings['bc_fournisseur_format'] ?? '{NUMBER}' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="bc_fournisseur_apply_to_old" id="bc_fournisseur_apply_to_old" value="1" {{ ($settings['bc_fournisseur_apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                            <label for="bc_fournisseur_apply_to_old" class="ml-2 text-sm text-gray-700">Appliquer le nouveau format aux anciens documents</label>
                        </div>

                        <div>
                            <label for="bc_fournisseur_year" class="block text-sm font-medium text-gray-700 mb-1">Année (YYYY/000001)</label>
                            <input type="text" name="bc_fournisseur_year" id="bc_fournisseur_year" value="{{ $settings['bc_fournisseur_year'] ?? date('Y') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bc_fournisseur_code_length" class="block text-sm font-medium text-gray-700 mb-1">Nombre de caractères que doit contenir le code généré, complété des zéros si nécessaire</label>
                            <input type="number" name="bc_fournisseur_code_length" id="bc_fournisseur_code_length" value="{{ $settings['bc_fournisseur_code_length'] ?? '6' }}" min="1" max="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bc_fournisseur_reset_period" class="block text-sm font-medium text-gray-700 mb-1">Réinitialiser numérotation</label>
                            <select name="bc_fournisseur_reset_period" id="bc_fournisseur_reset_period" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                                <option value="never" {{ ($settings['bc_fournisseur_reset_period'] ?? 'yearly') === 'never' ? 'selected' : '' }}>Jamais</option>
                                <option value="yearly" {{ ($settings['bc_fournisseur_reset_period'] ?? 'yearly') === 'yearly' ? 'selected' : '' }}>Chaque Année</option>
                                <option value="monthly" {{ ($settings['bc_fournisseur_reset_period'] ?? 'yearly') === 'monthly' ? 'selected' : '' }}>Chaque Mois</option>
                            </select>
                        </div>

                        <div>
                            <label for="bc_fournisseur_conditions" class="block text-sm font-medium text-gray-700 mb-1">Conditions et modalités par défaut</label>
                            <textarea name="bc_fournisseur_conditions" id="bc_fournisseur_conditions" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['bc_fournisseur_conditions'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label for="bc_fournisseur_remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarques fournisseur par défaut</label>
                            <textarea name="bc_fournisseur_remarks" id="bc_fournisseur_remarks" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['bc_fournisseur_remarks'] ?? '' }}</textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium">
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- BC Client Settings -->
            <div x-show="activeTab === 'bc_client'" x-cloak>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_type" value="bc_client">
                    
                    <div class="space-y-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm font-medium text-blue-800">Aperçu du prochain numéro</p>
                            <p class="text-lg font-bold text-blue-900 mt-1">{{ $previews['bc_client'] ?? 'BC-2026/000001' }}</p>
                        </div>

                        <div>
                            <label for="bc_client_next_number" class="block text-sm font-medium text-gray-700 mb-1">Prochain numéro de BC client</label>
                            <input type="text" name="bc_client_next_number" id="bc_client_next_number" value="{{ $settings['bc_client_next_number'] ?? '1' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bc_client_format" class="block text-sm font-medium text-gray-700 mb-1">
                                Format de numérotation de BC client
                                <span class="inline-block ml-1 text-gray-400 cursor-help" title="Utilisez {NUMBER} pour le numéro, {YEAR} pour l'année">ⓘ</span>
                            </label>
                            <input type="text" name="bc_client_format" id="bc_client_format" value="{{ $settings['bc_client_format'] ?? '{NUMBER}' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="bc_client_apply_to_old" id="bc_client_apply_to_old" value="1" {{ ($settings['bc_client_apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                            <label for="bc_client_apply_to_old" class="ml-2 text-sm text-gray-700">Appliquer le nouveau format aux anciens documents</label>
                        </div>

                        <div>
                            <label for="bc_client_year" class="block text-sm font-medium text-gray-700 mb-1">Année (YYYY/000001)</label>
                            <input type="text" name="bc_client_year" id="bc_client_year" value="{{ $settings['bc_client_year'] ?? date('Y') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bc_client_code_length" class="block text-sm font-medium text-gray-700 mb-1">Nombre de caractères que doit contenir le code généré, complété des zéros si nécessaire</label>
                            <input type="number" name="bc_client_code_length" id="bc_client_code_length" value="{{ $settings['bc_client_code_length'] ?? '6' }}" min="1" max="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bc_client_reset_period" class="block text-sm font-medium text-gray-700 mb-1">Réinitialiser numérotation</label>
                            <select name="bc_client_reset_period" id="bc_client_reset_period" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                                <option value="never" {{ ($settings['bc_client_reset_period'] ?? 'yearly') === 'never' ? 'selected' : '' }}>Jamais</option>
                                <option value="yearly" {{ ($settings['bc_client_reset_period'] ?? 'yearly') === 'yearly' ? 'selected' : '' }}>Chaque Année</option>
                                <option value="monthly" {{ ($settings['bc_client_reset_period'] ?? 'yearly') === 'monthly' ? 'selected' : '' }}>Chaque Mois</option>
                            </select>
                        </div>

                        <div>
                            <label for="bc_client_conditions" class="block text-sm font-medium text-gray-700 mb-1">Conditions et modalités par défaut</label>
                            <textarea name="bc_client_conditions" id="bc_client_conditions" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['bc_client_conditions'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label for="bc_client_remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarques client par défaut</label>
                            <textarea name="bc_client_remarks" id="bc_client_remarks" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['bc_client_remarks'] ?? '' }}</textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium">
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bon de Livraison Settings -->
            <div x-show="activeTab === 'bon_livraison'" x-cloak>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_type" value="bon_livraison">
                    
                    <div class="space-y-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm font-medium text-blue-800">Aperçu du prochain numéro</p>
                            <p class="text-lg font-bold text-blue-900 mt-1">{{ $previews['bon_livraison'] ?? 'BL-2026/000001' }}</p>
                        </div>

                        <div>
                            <label for="bon_livraison_next_number" class="block text-sm font-medium text-gray-700 mb-1">Prochain numéro de bon de livraison</label>
                            <input type="text" name="bon_livraison_next_number" id="bon_livraison_next_number" value="{{ $settings['bon_livraison_next_number'] ?? '1' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bon_livraison_format" class="block text-sm font-medium text-gray-700 mb-1">
                                Format de numérotation de bon de livraison
                                <span class="inline-block ml-1 text-gray-400 cursor-help" title="Utilisez {NUMBER} pour le numéro, {YEAR} pour l'année">ⓘ</span>
                            </label>
                            <input type="text" name="bon_livraison_format" id="bon_livraison_format" value="{{ $settings['bon_livraison_format'] ?? '{NUMBER}' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="bon_livraison_apply_to_old" id="bon_livraison_apply_to_old" value="1" {{ ($settings['bon_livraison_apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                            <label for="bon_livraison_apply_to_old" class="ml-2 text-sm text-gray-700">Appliquer le nouveau format aux anciens documents</label>
                        </div>

                        <div>
                            <label for="bon_livraison_year" class="block text-sm font-medium text-gray-700 mb-1">Année (YYYY/000001)</label>
                            <input type="text" name="bon_livraison_year" id="bon_livraison_year" value="{{ $settings['bon_livraison_year'] ?? date('Y') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bon_livraison_code_length" class="block text-sm font-medium text-gray-700 mb-1">Nombre de caractères que doit contenir le code généré, complété des zéros si nécessaire</label>
                            <input type="number" name="bon_livraison_code_length" id="bon_livraison_code_length" value="{{ $settings['bon_livraison_code_length'] ?? '6' }}" min="1" max="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bon_livraison_reset_period" class="block text-sm font-medium text-gray-700 mb-1">Réinitialiser numérotation</label>
                            <select name="bon_livraison_reset_period" id="bon_livraison_reset_period" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                                <option value="never" {{ ($settings['bon_livraison_reset_period'] ?? 'yearly') === 'never' ? 'selected' : '' }}>Jamais</option>
                                <option value="yearly" {{ ($settings['bon_livraison_reset_period'] ?? 'yearly') === 'yearly' ? 'selected' : '' }}>Chaque Année</option>
                                <option value="monthly" {{ ($settings['bon_livraison_reset_period'] ?? 'yearly') === 'monthly' ? 'selected' : '' }}>Chaque Mois</option>
                            </select>
                        </div>

                        <div>
                            <label for="bon_livraison_conditions" class="block text-sm font-medium text-gray-700 mb-1">Conditions et modalités par défaut</label>
                            <textarea name="bon_livraison_conditions" id="bon_livraison_conditions" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['bon_livraison_conditions'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label for="bon_livraison_remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarques client par défaut</label>
                            <textarea name="bon_livraison_remarks" id="bon_livraison_remarks" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['bon_livraison_remarks'] ?? '' }}</textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium">
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bon de Réception Settings -->
            <div x-show="activeTab === 'bon_reception'" x-cloak>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_type" value="bon_reception">
                    
                    <div class="space-y-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <p class="text-sm font-medium text-blue-800">Aperçu du prochain numéro</p>
                            <p class="text-lg font-bold text-blue-900 mt-1">{{ $previews['bon_reception'] ?? 'BR-2026/000001' }}</p>
                        </div>

                        <div>
                            <label for="bon_reception_next_number" class="block text-sm font-medium text-gray-700 mb-1">Prochain numéro de bon de réception</label>
                            <input type="text" name="bon_reception_next_number" id="bon_reception_next_number" value="{{ $settings['bon_reception_next_number'] ?? '1' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bon_reception_format" class="block text-sm font-medium text-gray-700 mb-1">
                                Format de numérotation de bon de réception
                                <span class="inline-block ml-1 text-gray-400 cursor-help" title="Utilisez {NUMBER} pour le numéro, {YEAR} pour l'année">ⓘ</span>
                            </label>
                            <input type="text" name="bon_reception_format" id="bon_reception_format" value="{{ $settings['bon_reception_format'] ?? '{NUMBER}' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="bon_reception_apply_to_old" id="bon_reception_apply_to_old" value="1" {{ ($settings['bon_reception_apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                            <label for="bon_reception_apply_to_old" class="ml-2 text-sm text-gray-700">Appliquer le nouveau format aux anciens documents</label>
                        </div>

                        <div>
                            <label for="bon_reception_year" class="block text-sm font-medium text-gray-700 mb-1">Année (YYYY/000001)</label>
                            <input type="text" name="bon_reception_year" id="bon_reception_year" value="{{ $settings['bon_reception_year'] ?? date('Y') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bon_reception_code_length" class="block text-sm font-medium text-gray-700 mb-1">Nombre de caractères que doit contenir le code généré, complété des zéros si nécessaire</label>
                            <input type="number" name="bon_reception_code_length" id="bon_reception_code_length" value="{{ $settings['bon_reception_code_length'] ?? '6' }}" min="1" max="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>

                        <div>
                            <label for="bon_reception_reset_period" class="block text-sm font-medium text-gray-700 mb-1">Réinitialiser numérotation</label>
                            <select name="bon_reception_reset_period" id="bon_reception_reset_period" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                                <option value="never" {{ ($settings['bon_reception_reset_period'] ?? 'yearly') === 'never' ? 'selected' : '' }}>Jamais</option>
                                <option value="yearly" {{ ($settings['bon_reception_reset_period'] ?? 'yearly') === 'yearly' ? 'selected' : '' }}>Chaque Année</option>
                                <option value="monthly" {{ ($settings['bon_reception_reset_period'] ?? 'yearly') === 'monthly' ? 'selected' : '' }}>Chaque Mois</option>
                            </select>
                        </div>

                        <div>
                            <label for="bon_reception_conditions" class="block text-sm font-medium text-gray-700 mb-1">Conditions et modalités par défaut</label>
                            <textarea name="bon_reception_conditions" id="bon_reception_conditions" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['bon_reception_conditions'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label for="bon_reception_remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarques fournisseur par défaut</label>
                            <textarea name="bon_reception_remarks" id="bon_reception_remarks" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['bon_reception_remarks'] ?? '' }}</textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium">
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Produit Settings -->
            <div x-show="activeTab === 'produit'" x-cloak>
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_type" value="produit">
                    
                    <div class="space-y-6">
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

                        <div class="flex items-center">
                            <input type="checkbox" name="produit_apply_to_old" id="produit_apply_to_old" value="1" {{ ($settings['produit_apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                            <label for="produit_apply_to_old" class="ml-2 text-sm text-gray-700">Appliquer le nouveau format aux anciens produits</label>
                        </div>

                        <div>
                            <label for="produit_code_length" class="block text-sm font-medium text-gray-700 mb-1">Nombre de caractères que doit contenir le code généré, complété des zéros si nécessaire</label>
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

                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex">
                                <svg class="h-5 w-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Configuration Prix Shopify</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <label class="flex items-center space-x-3 cursor-pointer">
                                            <input type="radio" name="shopify_price_type" value="ttc" class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300" {{ ($settings['shopify_price_type'] ?? 'ttc') === 'ttc' ? 'checked' : '' }}>
                                            <span>Prix TTC (Toutes Taxes Comprises)</span>
                                        </label>
                                        <label class="flex items-center space-x-3 cursor-pointer mt-2">
                                            <input type="radio" name="shopify_price_type" value="ht" class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300" {{ ($settings['shopify_price_type'] ?? 'ttc') === 'ht' ? 'checked' : '' }}>
                                            <span>Prix HT (Hors Taxes)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium">
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection
