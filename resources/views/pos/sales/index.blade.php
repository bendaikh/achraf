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

            <x-table-bulk-bar export-type="pos-sales" item-label="vente(s)" />

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <x-table-checkbox-header export-type="pos-sales" />
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paiement</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total TTC</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Caissier</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($sales as $sale)
                                <tr class="hover:bg-gray-50">
                                    <x-table-checkbox-cell export-type="pos-sales" :id="$sale->id" />
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $sale->ticket_number }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $sale->client?->name ?? 'Comptoir' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $sale->paymentLabel() }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-emerald-700 text-right">{{ number_format($sale->total, 2) }} DH</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $sale->user?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('pos.sales.show', $sale) }}" class="text-emerald-600 hover:text-emerald-800 text-sm font-medium">Ticket</a>
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
                @if($sales->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">{{ $sales->links() }}</div>
                @endif
            </div>
        </div>
    </main>
@endsection
