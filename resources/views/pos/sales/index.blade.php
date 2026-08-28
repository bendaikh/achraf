@extends('layouts.with-sidebar')

@section('title', 'Historique point de vente')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Historique des ventes (PDV)</h2>
                    <p class="text-sm text-gray-600 mt-1">Tickets caisse et modes de paiement</p>
                </div>
                <a href="{{ route('pos.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nouvelle vente
                </a>
            </div>
        </header>

        <div class="p-8">
            @if(session('success'))
                <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-lg text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <x-table-filters
                :action="route('pos.sales.index')"
                search-placeholder="N° ticket, client..."
                :date-to="true"
                grid-cols="md:grid-cols-6"
            >
                <x-table-filter-select
                    name="payment_method"
                    label="Mode de paiement"
                    :options="$paymentMethods"
                />
            </x-table-filters>

            <x-table-list-toolbar table-id="pos-sales" />



            <x-table-bulk-bar export-type="pos-sales" item-label="vente(s)" />

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table data-lm-table="pos-sales" class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <x-table-checkbox-header export-type="pos-sales" />
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-ticket column-ticket" data-lm-col="ticket">Ticket</th>
                                <x-table-sort-header column="sold_at" colKey="date" label="Date" :default="true" default-direction="desc" />
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-client column-client" data-lm-col="client">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-paiement column-paiement" data-lm-col="paiement">Paiement</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase lm-col lm-col-total column-total" data-lm-col="total">Total TTC</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase lm-col lm-col-statut column-statut" data-lm-col="statut">Caissier</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase lm-col lm-col-actions column-actions" data-lm-col="actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($sales as $sale)
                                <tr class="hover:bg-gray-50">
                                    <x-table-checkbox-cell export-type="pos-sales" :id="$sale->id" />
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900 lm-col lm-col-ticket column-ticket" data-lm-col="ticket">{{ $sale->ticket_number }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 lm-col lm-col-date column-date" data-lm-col="date">{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 lm-col lm-col-client column-client" data-lm-col="client">{{ $sale->client?->name ?? \App\Support\PosDefaultClient::NAME }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 lm-col lm-col-paiement column-paiement" data-lm-col="paiement">{{ $sale->paymentLabel() }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-emerald-700 text-right lm-col lm-col-total column-total" data-lm-col="total">{{ number_format($sale->total, 2) }} DH</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 lm-col lm-col-statut column-statut" data-lm-col="statut">{{ $sale->user?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 text-right lm-col lm-col-actions column-actions" data-lm-col="actions">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('pos.sales.show', $sale) }}" class="text-emerald-600 hover:text-emerald-800 text-sm font-medium">Ticket</a>
                                            <form action="{{ route('pos.sales.destroy', $sale) }}" method="POST" onsubmit="return confirm('Supprimer cette vente ?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800" title="Supprimer">
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
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">Aucune vente enregistrée.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-table-pagination :paginator="$sales" item-label="ventes" :default-per-page="20" />
            </div>
        </div>
    </main>
@endsection
