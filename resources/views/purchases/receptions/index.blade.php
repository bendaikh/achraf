@extends('layouts.with-sidebar')

@section('title', 'Liste des bons de réception')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Liste des bons de réception</h2>
                    <p class="text-sm text-gray-600 mt-1">Gérer tous vos bons de réception</p>
                </div>
                <a href="{{ route('receptions.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                    + Créer un bon de réception
                </a>
            </div>
        </header>

        <div class="p-8">
            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            @endif

            <x-bulk-import-panel
                label="Bons de réception"
                :template-route="route('receptions.import.template')"
                :import-route="route('receptions.import')"
            />

            <x-table-filters
                :action="route('receptions.index')"
                search-placeholder="N° réception, référence, fournisseur..."
                grid-cols="md:grid-cols-6"
            >
                <x-table-filter-select
                    name="status"
                    label="Statut"
                    :options="['brouillon' => 'Brouillon', 'validé' => 'Validé', 'annulé' => 'Annulé']"
                />
            </x-table-filters>

            <x-table-list-toolbar table-id="receptions" />



            <x-table-bulk-bar export-type="receptions" item-label="réception(s)" />

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table data-lm-table="receptions" class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <x-table-checkbox-header export-type="receptions" />
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-numero column-numero" data-lm-col="numero">
                                    Numéro
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-fournisseur column-fournisseur" data-lm-col="fournisseur">
                                    Fournisseur
                                </th>
                                <x-table-sort-header
                                    column="reception_date"
                                    colKey="date"
                                    label="Date de réception"
                                    :default="true"
                                    default-direction="desc"
                                />
                                <x-table-sort-header
                                    column="delivery_date"
                                    colKey="livraison"
                                    label="Date de livraison"
                                    default-direction="desc"
                                />
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-statut column-statut" data-lm-col="statut">
                                    Statut
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-conversion column-conversion" data-lm-col="conversion">
                                    Conversion
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-total column-total" data-lm-col="total">
                                    Total
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-document column-document" data-lm-col="document">
                                    Document importé
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-actions column-actions" data-lm-col="actions">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($receptions as $invoice)
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <x-table-checkbox-cell export-type="receptions" :id="$invoice->id" />
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-numero column-numero" data-lm-col="numero">
                                        <x-table-show-link :href="route('receptions.show', $invoice)" :label="$invoice->reception_number" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-fournisseur column-fournisseur" data-lm-col="fournisseur">
                                        <div class="text-sm text-gray-900">{{ $invoice->supplier->name ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-date column-date" data-lm-col="date">
                                        <div class="text-sm text-gray-900">{{ $invoice->reception_date->format('d/m/Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-livraison column-livraison" data-lm-col="livraison">
                                        <div class="text-sm text-gray-900">{{ $invoice->delivery_date ? $invoice->delivery_date->format('d/m/Y') : '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-statut column-statut" data-lm-col="statut">
                                        <div class="text-sm text-gray-900">{{ $invoice->status }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-conversion column-conversion" data-lm-col="conversion">
                                        <x-reception-conversion-status
                                            :converted="$invoice->isConverted()"
                                            :converted-to-delivery-note="$invoice->isConvertedToDeliveryNote()"
                                        />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-total column-total" data-lm-col="total">
                                        <div class="text-sm font-semibold text-gray-900">{{ number_format($invoice->total, 2) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-document column-document" data-lm-col="document">
                                        <x-managed-document-actions type="receptions" :id="$invoice->id" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium lm-col lm-col-actions column-actions" data-lm-col="actions">
                                        <div class="flex items-center space-x-3">
                                            <a href="{{ route('receptions.show', $invoice) }}" class="text-blue-600 hover:text-blue-900" title="Voir">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </a>
                                            <x-libromart-pdf-actions
                                                :print-route="route('receptions.print', $invoice)"
                                                :pdf-route="route('receptions.pdf', $invoice)"
                                            />
                                            <a href="{{ route('receptions.edit', $invoice) }}" class="text-yellow-600 hover:text-yellow-900" title="Modifier">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                            <form action="{{ route('receptions.destroy', $invoice) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce bon de réception?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Supprimer">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <p class="mt-2 text-sm text-gray-500">Aucun bon de réception trouvé</p>
                                            <a href="{{ route('receptions.create') }}" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                                                Créer votre premier bon de réception
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <x-table-pagination :paginator="$receptions" :bordered="false" item-label="réceptions" />
        </div>

        <div id="receptionConvertModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
            <div class="absolute inset-0 bg-gray-900/50" onclick="closeReceptionConvertModal()"></div>
            <div class="relative mx-auto my-10 w-full max-w-2xl bg-white rounded-xl shadow-xl border border-gray-200 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Convertir les Bons de Réception</h3>
                        <p class="text-sm text-gray-500 mt-1">Choisissez le type de document à créer, puis le mode de regroupement.</p>
                    </div>
                    <button type="button" onclick="closeReceptionConvertModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <div id="receptionConvertError" class="hidden mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3"></div>

                <div class="mt-6 space-y-5">
                    <section class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                        <div class="flex items-start gap-3 mb-3">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white shrink-0">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </span>
                            <div>
                                <h4 class="font-semibold text-indigo-950">Convertir en facture fournisseur</h4>
                                <p class="text-sm text-indigo-800/80 mt-0.5">Créez une ou plusieurs factures fournisseur à partir des bons de réception sélectionnés.</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <button type="button" onclick="convertSelectedReceptions('separate')" class="w-full text-left p-4 bg-white border border-indigo-200 rounded-lg hover:border-indigo-400 hover:bg-indigo-50 transition">
                                <span class="block font-medium text-gray-900">Convertir chaque Bon de Réception en une facture distincte</span>
                                <span class="block text-sm text-gray-500 mt-1">Une facture fournisseur sera créée pour chaque BR sélectionné.</span>
                            </button>
                            <button type="button" onclick="convertSelectedReceptions('combined')" class="w-full text-left p-4 bg-white border border-indigo-200 rounded-lg hover:border-indigo-400 hover:bg-indigo-50 transition">
                                <span class="block font-medium text-gray-900">Convertir tous les Bons de Réception sélectionnés en une seule facture</span>
                                <span class="block text-sm text-gray-500 mt-1">Les lignes seront fusionnées dans une seule facture, avec la référence BR/BC sur chaque ligne.</span>
                            </button>
                        </div>
                    </section>

                    <section class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                        <div class="flex items-start gap-3 mb-3">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white shrink-0">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8m-8 4h5m-9 8h14a2 2 0 002-2V7a2 2 0 00-2-2h-3.5l-1-2h-3l-1 2H6a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </span>
                            <div>
                                <h4 class="font-semibold text-emerald-950">Convertir en bon de livraison</h4>
                                <p class="text-sm text-emerald-800/80 mt-0.5">Créez un ou plusieurs bons de livraison à partir des bons de réception sélectionnés.</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <button type="button" onclick="convertSelectedReceptionsToDeliveryNotes('separate')" class="w-full text-left p-4 bg-white border border-emerald-200 rounded-lg hover:border-emerald-400 hover:bg-emerald-50 transition">
                                <span class="block font-medium text-gray-900">Convertir chaque Bon de Réception en un bon de livraison distinct</span>
                                <span class="block text-sm text-gray-500 mt-1">Un bon de livraison sera créé pour chaque BR sélectionné.</span>
                            </button>
                            <button type="button" onclick="convertSelectedReceptionsToDeliveryNotes('combined')" class="w-full text-left p-4 bg-white border border-emerald-200 rounded-lg hover:border-emerald-400 hover:bg-emerald-50 transition">
                                <span class="block font-medium text-gray-900">Convertir tous les Bons de Réception sélectionnés en un seul bon de livraison</span>
                                <span class="block text-sm text-gray-500 mt-1">Les lignes seront fusionnées dans un seul BL, avec la référence BR sur chaque ligne.</span>
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

@push('scripts')
<script>
function openReceptionConvertModal() {
    const ids = window.getSelectedTableIds ? window.getSelectedTableIds('receptions') : [];
    if (ids.length === 0) {
        alert('Veuillez sélectionner au moins un bon de réception.');
        return;
    }

    document.getElementById('receptionConvertError').classList.add('hidden');
    document.getElementById('receptionConvertModal').classList.remove('hidden');
}

function closeReceptionConvertModal() {
    document.getElementById('receptionConvertModal').classList.add('hidden');
}

function showReceptionConvertError(message) {
    const error = document.getElementById('receptionConvertError');
    error.textContent = message;
    error.classList.remove('hidden');
}

function convertSelectedReceptions(mode) {
    const ids = window.getSelectedTableIds ? window.getSelectedTableIds('receptions') : [];
    const csrf = document.querySelector('meta[name="csrf-token"]');

    fetch(@json(route('receptions.bulk-convert')), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
        },
        body: JSON.stringify({ ids, mode })
    })
        .then(response => response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || 'Erreur lors de la conversion.');
            }
            return data;
        }))
        .then(data => {
            window.location.href = data.redirect_url || @json(route('supplier-invoices.index'));
        })
        .catch(error => {
            showReceptionConvertError(error.message || 'Erreur lors de la conversion.');
        });
}

function convertSelectedReceptionsToDeliveryNotes(mode) {
    const ids = window.getSelectedTableIds ? window.getSelectedTableIds('receptions') : [];
    const csrf = document.querySelector('meta[name="csrf-token"]');

    fetch(@json(route('receptions.bulk-convert-delivery-notes')), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
        },
        body: JSON.stringify({ ids, mode })
    })
        .then(response => response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || 'Erreur lors de la conversion.');
            }
            return data;
        }))
        .then(data => {
            window.location.href = data.redirect_url || @json(route('supplier-delivery-notes.index'));
        })
        .catch(error => {
            showReceptionConvertError(error.message || 'Erreur lors de la conversion.');
        });
}
</script>
@endpush
@endsection
