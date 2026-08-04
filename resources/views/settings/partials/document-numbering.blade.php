@php
    $key = $doc['key'];
    $prefix = $key . '_';
    $isOpenDefault = request('open', 'facture') === $key;
@endphp
<details
    id="settings-{{ $key }}"
    class="group border border-gray-200 rounded-xl overflow-hidden bg-white"
    @if($isOpenDefault) open @endif
>
    <summary
        class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-left hover:bg-gray-50 transition-colors [&::-webkit-details-marker]:hidden"
    >
        <div class="flex items-center gap-3 min-w-0">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-[#0a5d8a]/10 text-[#0a5d8a]">
                {!! $doc['icon'] !!}
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900">{{ $doc['label'] }}</p>
                <p class="text-xs text-gray-500 truncate">
                    Aperçu :
                    <span class="font-medium text-[#0a5d8a]">{{ $previews[$key] ?? $doc['preview_fallback'] }}</span>
                </p>
            </div>
        </div>
        <svg class="h-5 w-5 text-gray-400 flex-shrink-0 transition-transform duration-200 group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </summary>

    <div class="border-t border-gray-100">
        <form action="{{ route('settings.update') }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="settings_type" value="{{ $key }}">

            <div class="p-5 space-y-6">
                <div class="rounded-xl border border-blue-100 bg-gradient-to-r from-blue-50 to-sky-50 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-blue-700">Aperçu du prochain numéro</p>
                    <p class="mt-1 text-xl font-bold tracking-wide text-blue-900">{{ $previews[$key] ?? $doc['preview_fallback'] }}</p>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Configuration de la numérotation</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="{{ $prefix }}next_number" class="block text-sm font-medium text-gray-700 mb-1">{{ $doc['next_label'] }}</label>
                            <input type="text" name="{{ $prefix }}next_number" id="{{ $prefix }}next_number" value="{{ $settings[$prefix.'next_number'] ?? '1' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="{{ $prefix }}format" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $doc['format_label'] }}
                                <span class="inline-block ml-1 text-gray-400 cursor-help" title="Utilisez {NUMBER} pour le numéro, {YEAR} pour l'année">ⓘ</span>
                            </label>
                            <input type="text" name="{{ $prefix }}format" id="{{ $prefix }}format" value="{{ $settings[$prefix.'format'] ?? '{NUMBER}' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="{{ $prefix }}year" class="block text-sm font-medium text-gray-700 mb-1">{{ $doc['year_label'] }}</label>
                            <input type="text" name="{{ $prefix }}year" id="{{ $prefix }}year" value="{{ $settings[$prefix.'year'] ?? date('Y') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                        <div>
                            <label for="{{ $prefix }}code_length" class="block text-sm font-medium text-gray-700 mb-1">Longueur du code (zéros à gauche si besoin)</label>
                            <input type="number" name="{{ $prefix }}code_length" id="{{ $prefix }}code_length" value="{{ $settings[$prefix.'code_length'] ?? '6' }}" min="1" max="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Options</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="{{ $prefix }}reset_period" class="block text-sm font-medium text-gray-700 mb-1">{{ $doc['reset_label'] }}</label>
                            <select name="{{ $prefix }}reset_period" id="{{ $prefix }}reset_period" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                                <option value="never" {{ ($settings[$prefix.'reset_period'] ?? 'yearly') === 'never' ? 'selected' : '' }}>Jamais</option>
                                <option value="yearly" {{ ($settings[$prefix.'reset_period'] ?? 'yearly') === 'yearly' ? 'selected' : '' }}>Chaque Année</option>
                                <option value="monthly" {{ ($settings[$prefix.'reset_period'] ?? 'yearly') === 'monthly' ? 'selected' : '' }}>Chaque Mois</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 w-full cursor-pointer hover:border-[#0a5d8a]/40 transition-colors">
                                <input type="checkbox" name="{{ $prefix }}apply_to_old" id="{{ $prefix }}apply_to_old" value="1" {{ ($settings[$prefix.'apply_to_old'] ?? false) ? 'checked' : '' }} class="h-4 w-4 text-[#0a5d8a] focus:ring-[#0a5d8a] border-gray-300 rounded">
                                <span class="text-sm text-gray-700">Appliquer le nouveau format aux anciens documents</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Textes par défaut</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="{{ $prefix }}conditions" class="block text-sm font-medium text-gray-700 mb-1">Conditions et modalités par défaut</label>
                            <textarea name="{{ $prefix }}conditions" id="{{ $prefix }}conditions" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings[$prefix.'conditions'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label for="{{ $prefix }}remarks" class="block text-sm font-medium text-gray-700 mb-1">{{ $doc['remarks_label'] }}</label>
                            <textarea name="{{ $prefix }}remarks" id="{{ $prefix }}remarks" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings[$prefix.'remarks'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                @if (($doc['extra'] ?? null) === 'auto_invoice')
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
                        <div>
                            <label for="auto_invoice_start_order_number" class="block text-sm font-medium text-gray-900 mb-1">
                                Numéro de commande de départ pour la génération automatique de factures
                            </label>
                            <input type="number" name="auto_invoice_start_order_number" id="auto_invoice_start_order_number" min="0" value="{{ $settings['auto_invoice_start_order_number'] ?? '' }}" placeholder="Ex: 8800" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent bg-white">
                        </div>
                        <p class="text-sm text-amber-900">
                            Lorsqu'une commande est marquée <strong>Traité</strong> et <strong>Payé</strong>, une facture est créée automatiquement uniquement si son N° Commande est supérieur ou égal à cette valeur. Laissez vide pour désactiver.
                        </p>
                    </div>
                @endif

                @if (($doc['extra'] ?? null) === 'validity')
                    <div>
                        <label for="devis_validity_days" class="block text-sm font-medium text-gray-700 mb-1">Durée de validité du devis (en jours)</label>
                        <input type="number" name="devis_validity_days" id="devis_validity_days" value="{{ $settings['devis_validity_days'] ?? '30' }}" min="1" class="w-full max-w-xs px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">
                    </div>
                @endif
            </div>

            <div class="sticky bottom-0 flex justify-end gap-2 border-t border-gray-100 bg-white/95 backdrop-blur px-5 py-3">
                <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium shadow-sm">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</details>
