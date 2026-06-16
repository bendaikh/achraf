@extends('layouts.with-sidebar')

@section('title', 'Gestion Financière')

@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Gestion Financière</h2>
                <p class="text-sm text-gray-600 mt-1">Vue centralisée des revenus, dépenses, paiements et soldes</p>
            </div>
            <form method="GET" action="{{ route('financial.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date de début</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date de fin</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium hover:bg-[#084a6e]">Filtrer</button>
            </form>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-8">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Chiffre d'affaires</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($overview['revenue'], 2) }} DH</p>
                <p class="text-xs text-gray-400 mt-2">POS : {{ number_format($overview['revenue_pos'], 2) }} · Factures : {{ number_format($overview['revenue_invoices'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Dépenses</p>
                <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($overview['expenses'], 2) }} DH</p>
                <p class="text-xs text-gray-400 mt-2">Avec facture : {{ number_format($overview['expenses_with_invoice'], 2) }} · Sans : {{ number_format($overview['expenses_without_invoice'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Résultat net</p>
                <p class="text-2xl font-bold mt-1 {{ $overview['net_result'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ number_format($overview['net_result'], 2) }} DH
                </p>
                <p class="text-xs text-gray-400 mt-2">CA − dépenses (période)</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Flux de trésorerie net</p>
                <p class="text-2xl font-bold mt-1 {{ $overview['net_cash_flow'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ number_format($overview['net_cash_flow'], 2) }} DH
                </p>
                <p class="text-xs text-gray-400 mt-2">Encaissements + POS − sorties</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Encaissements clients</p>
                <p class="text-xl font-bold text-green-600 mt-1">{{ number_format($overview['client_payments'], 2) }} DH</p>
                <a href="{{ route('sales.payments.index') }}" class="text-xs text-blue-600 hover:text-blue-800 mt-2 inline-block">Gestion paiements ventes →</a>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Paiements fournisseurs</p>
                <p class="text-xl font-bold text-indigo-600 mt-1">{{ number_format($overview['supplier_payments'], 2) }} DH</p>
                <a href="{{ route('purchases.payments.index') }}" class="text-xs text-blue-600 hover:text-blue-800 mt-2 inline-block">Gestion paiements achats →</a>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Créances clients</p>
                <p class="text-xl font-bold text-amber-600 mt-1">{{ number_format($overview['client_receivables'], 2) }} DH</p>
                <p class="text-xs text-gray-400 mt-2">{{ $outstandingClients['count'] }} facture(s) en attente</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Dettes fournisseurs</p>
                <p class="text-xl font-bold text-amber-600 mt-1">{{ number_format($overview['supplier_payables'], 2) }} DH</p>
                <p class="text-xs text-gray-400 mt-2">{{ $outstandingSuppliers['count'] }} facture(s) en attente</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Achats fournisseurs (période)</p>
                <p class="text-lg font-semibold text-gray-900 mt-1">{{ number_format($overview['supplier_purchases'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Revenus POS</p>
                <p class="text-lg font-semibold text-gray-900 mt-1">{{ number_format($overview['revenue_pos'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Revenus factures</p>
                <p class="text-lg font-semibold text-gray-900 mt-1">{{ number_format($overview['revenue_invoices'], 2) }} DH</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenus vs dépenses</h3>
                <div class="h-72">
                    <canvas id="revenueExpensesChart"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Flux de trésorerie (entrées / sorties)</h3>
                <div class="h-72">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Mouvements financiers récents</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Paiements clients, fournisseurs et dépenses sur la période</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tiers</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentTransactions as $transaction)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $transaction['date_formatted'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $transaction['label'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($transaction['url'])
                                        <a href="{{ $transaction['url'] }}" class="text-blue-600 hover:text-blue-800">{{ $transaction['reference'] }}</a>
                                    @else
                                        {{ $transaction['reference'] }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $transaction['party'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold {{ $transaction['direction'] === 'in' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $transaction['direction'] === 'in' ? '+' : '−' }}{{ number_format($transaction['amount'], 2) }} DH
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">Aucun mouvement sur la période sélectionnée</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-amber-100 bg-amber-50 flex flex-wrap justify-between items-center gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Créances clients</h3>
                        <p class="text-sm text-amber-800 mt-0.5">
                            {{ $outstandingClients['count'] }} facture(s) · {{ number_format($outstandingClients['total'], 2) }} DH
                        </p>
                    </div>
                    <a href="{{ route('sales.payments.index', ['payment_status' => 'unpaid']) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">Voir tout →</a>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($outstandingClients['items'] as $item)
                        <a href="{{ $item['url'] }}" class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $item['number'] }} — {{ $item['party'] }}</p>
                                <p class="text-xs text-gray-500">{{ $item['date'] }}@if($item['due_date']) · Échéance {{ $item['due_date'] }}@endif</p>
                            </div>
                            <span class="text-sm font-semibold text-amber-700">{{ number_format($item['remaining'], 2) }} DH</span>
                        </a>
                    @empty
                        <p class="px-6 py-8 text-sm text-gray-500 text-center">Aucune créance en attente</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl border border-indigo-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-indigo-100 bg-indigo-50 flex flex-wrap justify-between items-center gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Dettes fournisseurs</h3>
                        <p class="text-sm text-indigo-800 mt-0.5">
                            {{ $outstandingSuppliers['count'] }} facture(s) · {{ number_format($outstandingSuppliers['total'], 2) }} DH
                        </p>
                    </div>
                    <a href="{{ route('purchases.payments.index', ['payment_status' => 'unpaid']) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">Voir tout →</a>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($outstandingSuppliers['items'] as $item)
                        <a href="{{ $item['url'] }}" class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $item['number'] }} — {{ $item['party'] }}</p>
                                <p class="text-xs text-gray-500">{{ $item['date'] }}@if($item['due_date']) · Échéance {{ $item['due_date'] }}@endif</p>
                            </div>
                            <span class="text-sm font-semibold text-indigo-700">{{ number_format($item['remaining'], 2) }} DH</span>
                        </a>
                    @empty
                        <p class="px-6 py-8 text-sm text-gray-500 text-center">Aucune dette en attente</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Accès rapides</h3>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('sales.payments.index') }}" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 text-sm font-medium transition">Gestion Paiement (Ventes)</a>
                <a href="{{ route('purchases.payments.index') }}" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 text-sm font-medium transition">Gestion Paiement (Achats)</a>
                <a href="{{ route('invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium transition">Factures clients</a>
                <a href="{{ route('supplier-invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium transition">Factures fournisseurs</a>
                <a href="{{ route('expenses-with-invoice.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium transition">Dépenses avec facture</a>
                <a href="{{ route('expenses-without-invoice.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium transition">Dépenses sans facture</a>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const brandGold = '#fdb819';

    new Chart(document.getElementById('revenueExpensesChart'), {
        type: 'bar',
        data: {
            labels: @json($chart['labels']),
            datasets: [
                {
                    label: 'Revenus',
                    data: @json($chart['revenue']),
                    backgroundColor: brandGold,
                    borderRadius: 4,
                },
                {
                    label: 'Dépenses',
                    data: @json($chart['expenses']),
                    backgroundColor: '#ef4444',
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('fr-FR') + ' DH' } }
            }
        }
    });

    new Chart(document.getElementById('cashFlowChart'), {
        type: 'line',
        data: {
            labels: @json($chart['labels']),
            datasets: [
                {
                    label: 'Entrées',
                    data: @json($chart['cashIn']),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Sorties',
                    data: @json($chart['cashOut']),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true,
                    tension: 0.3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('fr-FR') + ' DH' } }
            }
        }
    });
});
</script>
@endpush
