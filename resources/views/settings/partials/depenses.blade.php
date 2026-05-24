<div x-show="activeTab === 'depenses'" x-cloak>
    <form action="{{ route('settings.update') }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="settings_type" value="depenses">

        <div class="space-y-6">
            <p class="text-sm text-gray-600">Configurez les valeurs disponibles dans les formulaires de dépenses. Une valeur par ligne.</p>

            <div>
                <label for="expense_categories" class="block text-sm font-medium text-gray-700 mb-1">Catégories de dépense</label>
                <textarea name="expense_categories" id="expense_categories" rows="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent" placeholder="Ex: Fournitures&#10;Transport&#10;Loyer">{{ $settings['expense_categories'] ?? '' }}</textarea>
            </div>

            <div>
                <label for="expense_accounts" class="block text-sm font-medium text-gray-700 mb-1">Comptes</label>
                <textarea name="expense_accounts" id="expense_accounts" rows="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent" placeholder="Ex: Caisse&#10;Banque">{{ $settings['expense_accounts'] ?? '' }}</textarea>
            </div>

            <div>
                <label for="expense_payment_methods" class="block text-sm font-medium text-gray-700 mb-1">Modes de règlement</label>
                <textarea name="expense_payment_methods" id="expense_payment_methods" rows="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent" placeholder="Ex: Espèces&#10;Virement&#10;Chèque">{{ $settings['expense_payment_methods'] ?? '' }}</textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium">
                    Enregistrer
                </button>
            </div>
        </div>
    </form>
</div>
