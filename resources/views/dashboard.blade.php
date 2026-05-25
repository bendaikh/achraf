@extends('layouts.with-sidebar')

@section('title', 'Tableau de bord')

@section('sidebar_page_title', 'Tableau de bord')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Tableau de bord</h2>
                <p class="text-sm text-gray-600 mt-1">Vue d'ensemble de votre activité — {{ now()->translatedFormat('d F Y') }}</p>
            </div>
            <p class="text-sm text-gray-500">Connecté : <span class="font-medium text-gray-800">{{ Auth::user()->name }}</span></p>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-8">
        @if (session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Chiffre d'affaires (mois)</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['revenue_month'], 2) }} DH</p>
                <p class="text-xs text-gray-400 mt-2">Année : {{ number_format($stats['revenue_year'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Dépenses (mois)</p>
                <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($stats['expenses_month'], 2) }} DH</p>
                <p class="text-xs text-gray-400 mt-2">Année : {{ number_format($stats['expenses_year'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Résultat net (mois)</p>
                <p class="text-2xl font-bold mt-1 {{ $stats['profit_month'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ number_format($stats['profit_month'], 2) }} DH
                </p>
                <p class="text-xs text-gray-400 mt-2">CA − dépenses du mois</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Paiements fournisseurs (mois)</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['supplier_payments_month'], 2) }} DH</p>
                <p class="text-xs text-amber-600 mt-2">Dettes fournisseurs : {{ number_format($stats['supplier_balance_due'], 2) }} DH</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach([
                ['Clients', $stats['clients_count'], 'text-blue-600'],
                ['Fournisseurs', $stats['suppliers_count'], 'text-indigo-600'],
                ['Produits', $stats['products_count'], 'text-emerald-600'],
                ['Commandes', $stats['orders_total'], 'text-amber-600'],
                ['Factures', $stats['invoices_count'], 'text-purple-600'],
                ['Devis en cours', $stats['quotes_pending'], 'text-gray-600'],
            ] as [$label, $value, $color])
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center shadow-sm">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $label }}</p>
                    <p class="text-xl font-bold {{ $color }} mt-1">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Commandes ce mois</p>
                <p class="text-xl font-semibold text-gray-900">{{ $stats['orders_month'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Commandes terminées</p>
                <p class="text-xl font-semibold text-gray-900">{{ $stats['orders_completed'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Stock bas / rupture</p>
                <p class="text-xl font-semibold text-amber-600">{{ $stats['low_stock_count'] }} / {{ $stats['out_of_stock_count'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Canaux vente</p>
                <p class="text-sm font-medium text-gray-900 mt-1">POS : {{ $stats['pos_orders'] }} · Shopify : {{ $stats['shopify_orders'] }}</p>
            </div>
        </div>

        {{-- Charts --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenus vs dépenses (6 derniers mois)</h3>
                <div class="h-72">
                    <canvas id="revenueExpensesChart"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Modes de paiement (POS)</h3>
                <div class="h-72 flex items-center justify-center">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Unpaid invoices --}}
        <div class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-amber-100 bg-amber-50 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Les factures non payées</h3>
                    <p class="text-sm text-amber-800 mt-0.5">
                        {{ $unpaidInvoices['count'] }} facture(s) · {{ number_format($unpaidInvoices['total'], 2) }} DH en attente
                    </p>
                </div>
                <a href="{{ route('invoices.index') }}" class="text-sm text-[#e5a617] hover:underline font-medium">Voir toutes les factures</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N°</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Échéance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($unpaidInvoices['items'] as $invoice)
                            <tr class="hover:bg-amber-50/50">
                                <td class="px-4 py-3"><a href="{{ $invoice['url'] }}" class="text-blue-600 hover:underline font-medium">{{ $invoice['number'] }}</a></td>
                                <td class="px-4 py-3 text-gray-700">{{ $invoice['client'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-amber-700">{{ number_format($invoice['total'], 2) }} DH</td>
                                <td class="px-4 py-3 text-gray-500">{{ $invoice['date'] }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $invoice['due_date'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Aucune facture impayée</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent activity --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Dernières commandes</h3>
                    <a href="{{ route('orders.index') }}" class="text-sm text-[#e5a617] hover:underline">Voir tout</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recentOrders as $order)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><a href="{{ $order['url'] }}" class="text-blue-600 hover:underline">{{ $order['ticket'] }}</a></td>
                                    <td class="px-4 py-3 text-gray-700">{{ $order['client'] }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ number_format($order['total'], 2) }} DH</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $order['date'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Aucune commande</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Dernières factures</h3>
                    <a href="{{ route('invoices.index') }}" class="text-sm text-[#e5a617] hover:underline">Voir tout</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N°</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recentInvoices as $invoice)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><a href="{{ $invoice['url'] }}" class="text-blue-600 hover:underline">{{ $invoice['number'] }}</a></td>
                                    <td class="px-4 py-3 text-gray-700">{{ $invoice['client'] }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ number_format($invoice['total'], 2) }} DH</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $invoice['date'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Aucune facture</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const brandGold = '#fdb819';
    const brandGoldDark = '#e5a617';

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

    const paymentLabels = @json($paymentChart['labels']);
    const paymentValues = @json($paymentChart['values']);

    if (paymentLabels.length > 0) {
        new Chart(document.getElementById('paymentMethodsChart'), {
            type: 'doughnut',
            data: {
                labels: paymentLabels,
                datasets: [{
                    data: paymentValues,
                    backgroundColor: [brandGold, brandGoldDark, '#0a5d8a', '#6b7280', '#10b981'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    } else {
        document.getElementById('paymentMethodsChart').parentElement.innerHTML =
            '<p class="text-sm text-gray-500">Aucune vente POS sur les 3 derniers mois</p>';
    }
});
</script>
@endpush
@endsection
