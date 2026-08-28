@extends('layouts.with-sidebar')

@section('title', 'Remboursements clients')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Remboursements clients</h2>
                <p class="text-sm text-gray-600 mt-1">Opérations financières de remboursement (distinctes des avoirs)</p>
            </div>
            <a href="{{ route('sales.refunds.create') }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                + Nouveau remboursement
            </a>
        </div>
    </header>

    <div class="p-8">
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        <x-table-filters :action="route('sales.refunds.index')" search-placeholder="N° remboursement, client, facture..." grid-cols="md:grid-cols-5">
            <div>
                <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                <select name="source" id="source" class="w-full rounded-lg border-gray-300 shadow-sm">
                    <option value="">Toutes</option>
                    <option value="shopify" @selected(request('source') === 'shopify')>Shopify</option>
                    <option value="jumia" @selected(request('source') === 'jumia')>Jumia</option>
                    <option value="manual" @selected(request('source') === 'manual')>Manuel</option>
                </select>
            </div>
        </x-table-filters>

        <x-table-list-toolbar table-id="sales-refunds" />

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mt-4">
            <div class="overflow-x-auto">
                <table data-lm-table="sales-refunds" class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <x-lm-col key="numero" tag="th" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">N° remboursement</x-lm-col>
                            <x-lm-col key="date" tag="th" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Date</x-lm-col>
                            <x-lm-col key="client" tag="th" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Client</x-lm-col>
                            <x-lm-col key="facture" tag="th" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Facture</x-lm-col>
                            <x-lm-col key="source" tag="th" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Source</x-lm-col>
                            <x-lm-col key="montant" tag="th" class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase">Montant</x-lm-col>
                            <x-lm-col key="mode" tag="th" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Mode</x-lm-col>
                            <x-lm-col key="actions" tag="th" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Actions</x-lm-col>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($refunds as $refund)
                            <tr class="hover:bg-gray-50">
                                <x-lm-col key="numero" class="px-6 py-4 text-sm font-medium text-gray-900">{{ $refund->refund_number }}</x-lm-col>
                                <x-lm-col key="date" class="px-6 py-4 text-sm text-gray-900">{{ $refund->refund_date->format('d/m/Y') }}</x-lm-col>
                                <x-lm-col key="client" class="px-6 py-4 text-sm text-gray-900">{{ $refund->client->name }}</x-lm-col>
                                <x-lm-col key="facture" class="px-6 py-4 text-sm">
                                    @if($refund->invoice)
                                        <a href="{{ route('invoices.show', $refund->invoice) }}" class="text-blue-600 hover:text-blue-800">{{ $refund->invoice->invoice_number }}</a>
                                    @else
                                        —
                                    @endif
                                </x-lm-col>
                                <x-lm-col key="source" class="px-6 py-4 text-sm text-gray-900">{{ ucfirst($refund->source ?? 'manuel') }}</x-lm-col>
                                <x-lm-col key="montant" class="px-6 py-4 text-sm font-semibold text-red-600 text-right">{{ number_format($refund->amount, 2) }}</x-lm-col>
                                <x-lm-col key="mode" class="px-6 py-4 text-sm text-gray-900">{{ $refund->payment_method }}</x-lm-col>
                                <x-lm-col key="actions" class="px-6 py-4 text-sm">
                                    <a href="{{ route('sales.refunds.show', $refund) }}" class="text-blue-600 hover:text-blue-800">Voir</a>
                                </x-lm-col>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500">Aucun remboursement enregistré</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">
            <x-table-pagination :paginator="$refunds" :bordered="false" item-label="remboursements" />
        </div>
    </div>
</main>
@endsection
