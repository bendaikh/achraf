@extends('layouts.with-sidebar')

@section('title', 'Liste des dépenses avec facture')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Liste des dépenses avec facture</h2>
                    <p class="text-sm text-gray-600 mt-1">Gérer toutes vos dépenses avec facture</p>
                </div>
                <a href="{{ route('expenses-with-invoice.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                    + Nouvelle dépense avec facture
                </a>
            </div>
        </header>

        <div class="p-8">
            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif

            <x-table-filters
                :action="route('expenses-with-invoice.index')"
                search-placeholder="Désignation, N° facture, fournisseur..."
                grid-cols="md:grid-cols-5"
            />

            <div class="mb-5 flex flex-wrap gap-2">
                @foreach(['' => 'Toutes', 'yes' => 'Récurrentes', 'no' => 'Non récurrentes'] as $value => $label)
                    <a href="{{ route('expenses-with-invoice.index', array_merge(request()->except(['page', 'recurring']), $value ? ['recurring' => $value] : [])) }}"
                       class="px-3 py-1.5 rounded-lg text-sm font-medium {{ request('recurring', '') === $value ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <x-table-list-toolbar table-id="expenses-with-invoice" />



            <x-table-bulk-bar export-type="expenses-with-invoice" item-label="dépense(s)" />

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table data-lm-table="expenses-with-invoice" class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <x-table-checkbox-header export-type="expenses-with-invoice" />
                                <x-table-sort-header column="reference" label="N° facture" default-direction="asc" />
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-reference column-reference" data-lm-col="reference">Désignation</th>
                                <x-table-sort-header column="expense_date" label="Date" :default="true" default-direction="desc" />
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-fournisseur column-fournisseur" data-lm-col="fournisseur">Montant</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-date column-date" data-lm-col="date">Catégorie</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-total column-total" data-lm-col="total">Document</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-tva column-tva" data-lm-col="tva">Récurrence</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-statut column-statut" data-lm-col="statut">Statut</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-actions column-actions" data-lm-col="actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($expenses as $expense)
                                <tr class="hover:bg-gray-50">
                                    <x-table-checkbox-cell export-type="expenses-with-invoice" :id="$expense->id" />
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 lm-col lm-col-reference column-reference" data-lm-col="reference">{{ $expense->reference ?: '—' }}</td>
                                    <td class="px-6 py-4 lm-col lm-col-fournisseur column-fournisseur" data-lm-col="fournisseur">
                                        <x-table-show-link :href="route('expenses-with-invoice.show', $expense)" :label="$expense->designation" />
                                    </td>
                                    <td class="px-6 py-4 lm-col lm-col-date column-date" data-lm-col="date">{{ $expense->expense_date->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4 font-semibold lm-col lm-col-total column-total" data-lm-col="total">{{ number_format($expense->amount, 2) }} {{ $expense->currency }}</td>
                                    <td class="px-6 py-4 lm-col lm-col-tva column-tva" data-lm-col="tva">{{ $expense->expense_category ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-statut column-statut" data-lm-col="statut">
                                        <x-managed-document-actions type="expenses-with-invoice" :id="$expense->id" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-actions column-actions" data-lm-col="actions">
                                        @if($expense->is_recurring)
                                            <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800">↻ Récurrente</span>
                                            @if($expense->isRecurrenceTemplate() && $expense->next_due_date)
                                                <div class="mt-1 text-xs text-gray-500">Prochaine : {{ $expense->next_due_date->format('d/m/Y') }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($expense->isPendingPayment())
                                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">À payer</span>
                                            <form action="{{ route('expenses.mark-paid', $expense) }}" method="POST" class="mt-2">
                                                @csrf
                                                <button class="text-xs font-medium text-green-700 hover:text-green-900">Enregistrer le paiement</button>
                                            </form>
                                        @else
                                            <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800">Payée</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('expenses-with-invoice.show', $expense) }}" class="text-blue-600 hover:text-blue-900 transition duration-150" title="Voir">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>
                                            <a href="{{ route('expenses-with-invoice.edit', $expense) }}" class="text-green-600 hover:text-green-900 transition duration-150" title="Modifier">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <x-libromart-pdf-actions
                                                :print-route="route('expenses.print', $expense)"
                                                :pdf-route="route('expenses.pdf', $expense)"
                                            />
                                            <form action="{{ route('expenses-with-invoice.destroy', $expense) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette dépense?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 transition duration-150" title="Supprimer">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-12 text-center text-gray-500">Aucune dépense trouvée</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <x-table-pagination :paginator="$expenses" :bordered="false" item-label="dépenses" />
        </div>
    </main>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
