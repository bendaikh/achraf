<div x-show="activeTab === 'mon_entreprise'" x-cloak>
    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="settings_type" value="mon_entreprise">

        <div class="space-y-8">
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900">Logo de l'entreprise</h3>
                <p class="text-sm text-gray-500 mt-1">Description</p>

                <div class="mt-6 flex flex-col sm:flex-row sm:items-start gap-6">
                    <div class="flex-shrink-0">
                        @if(!empty($settings['company_logo']))
                            <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="Logo" class="h-24 w-24 rounded-full object-cover border border-gray-200">
                        @else
                            <div class="h-24 w-24 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-400 text-xs text-center px-2">
                                Aucun logo
                            </div>
                        @endif
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Télécharger le logo de l'entreprise:</label>
                        <div class="flex flex-wrap gap-2">
                            <label class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 cursor-pointer text-sm font-medium">
                                Télécharger
                                <input type="file" name="company_logo" accept="image/*" class="hidden">
                            </label>
                            @if(!empty($settings['company_logo']))
                                <button type="submit" name="remove_logo" value="1" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium" onclick="return confirm('Supprimer le logo ?')">
                                    Supprimer
                                </button>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-2">L'image doit être au moins de 200 x 200</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900">Cachet de l'entreprise</h3>
                <p class="text-sm text-gray-500 mt-1">Image du cachet affichée sur les factures et documents imprimables</p>

                <div class="mt-6 flex flex-col sm:flex-row sm:items-start gap-6">
                    <div class="flex-shrink-0">
                        @if(!empty($settings['company_cachet']))
                            <img src="{{ asset('storage/' . $settings['company_cachet']) }}" alt="Cachet" class="h-24 w-auto max-w-[140px] object-contain border border-gray-200 rounded-lg p-1 bg-white">
                        @else
                            <div class="h-24 w-32 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-400 text-xs text-center px-2">
                                Aucun cachet
                            </div>
                        @endif
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Télécharger le cachet :</label>
                        <div class="flex flex-wrap gap-2">
                            <label class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 cursor-pointer text-sm font-medium">
                                Télécharger
                                <input type="file" name="company_cachet" accept="image/*" class="hidden">
                            </label>
                            @if(!empty($settings['company_cachet']))
                                <button type="submit" name="remove_cachet" value="1" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium" onclick="return confirm('Supprimer le cachet ?')">
                                    Supprimer
                                </button>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-2">PNG ou JPG recommandé, fond transparent si possible</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900">Informations de l'entreprise</h3>
                <p class="text-sm text-gray-500 mt-1">Renseignez les informations relatives à votre entreprise</p>

                <div class="mt-6 space-y-4">
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">Nom de l'entreprise</label>
                        <input type="text" name="company_name" id="company_name" value="{{ old('company_name', $settings['company_name'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                    </div>
                    <div>
                        <label for="company_address" class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                        <input type="text" name="company_address" id="company_address" value="{{ old('company_address', $settings['company_address'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="company_country" class="block text-sm font-medium text-gray-700 mb-1">Pays</label>
                            <input type="text" name="company_country" id="company_country" value="{{ old('company_country', $settings['company_country'] ?? 'Maroc') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="company_city" class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                            <input type="text" name="company_city" id="company_city" value="{{ old('company_city', $settings['company_city'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="company_postal_code" class="block text-sm font-medium text-gray-700 mb-1">Code postal</label>
                            <input type="text" name="company_postal_code" id="company_postal_code" value="{{ old('company_postal_code', $settings['company_postal_code'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="company_phone" class="block text-sm font-medium text-gray-700 mb-1">Numéro de téléphone</label>
                            <input type="text" name="company_phone" id="company_phone" value="{{ old('company_phone', $settings['company_phone'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="company_ice" class="block text-sm font-medium text-gray-700 mb-1">ICE</label>
                            <input type="text" name="company_ice" id="company_ice" value="{{ old('company_ice', $settings['company_ice'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="company_patente" class="block text-sm font-medium text-gray-700 mb-1">Patente</label>
                            <input type="text" name="company_patente" id="company_patente" value="{{ old('company_patente', $settings['company_patente'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="company_rc" class="block text-sm font-medium text-gray-700 mb-1">Registre de Commerce</label>
                            <input type="text" name="company_rc" id="company_rc" value="{{ old('company_rc', $settings['company_rc'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="company_if" class="block text-sm font-medium text-gray-700 mb-1">Identifiant fiscal (IF)</label>
                            <input type="text" name="company_if" id="company_if" value="{{ old('company_if', $settings['company_if'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="company_cnss" class="block text-sm font-medium text-gray-700 mb-1">CNSS</label>
                            <input type="text" name="company_cnss" id="company_cnss" value="{{ old('company_cnss', $settings['company_cnss'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="company_email" class="block text-sm font-medium text-gray-700 mb-1">Adresse email</label>
                            <input type="email" name="company_email" id="company_email" value="{{ old('company_email', $settings['company_email'] ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 text-sm font-medium">
                    Enregistrer
                </button>
            </div>
        </div>
    </form>
</div>
