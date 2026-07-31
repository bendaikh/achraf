{{-- Async dashboard panel — shell stays in layout; data loads via JSON. --}}
@php
    $dataUrl = $dataUrl ?? route('dashboard.data');
    $bootstrap = $bootstrap ?? null;
@endphp
<main
    class="flex-1 w-full min-w-0"
    x-data="dashboardPage(@js($dataUrl), @js($bootstrap))"
    x-init="init()"
    x-cloak
>
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Tableau de bord</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Vue d'ensemble de votre activité — <span x-text="todayLabel"></span>
                </p>
            </div>
            <form @submit.prevent="applyFilter()" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date de début</label>
                    <input type="date" x-model="dateFrom" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date de fin</label>
                    <input type="date" x-model="dateTo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <button
                    type="submit"
                    class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium hover:bg-[#084a6e] disabled:opacity-60"
                    :disabled="loading"
                >Filtrer</button>
            </form>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-8 relative">
        <div
            x-show="loading"
            x-transition.opacity
            class="absolute inset-0 z-10 bg-gray-50/70 flex items-start justify-center pt-24"
        >
            <div class="flex items-center gap-3 rounded-xl bg-white border border-gray-200 shadow-sm px-4 py-3 text-sm text-gray-600">
                <svg class="h-5 w-5 animate-spin text-[#0a5d8a]" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Chargement des données…
            </div>
        </div>

        <div x-show="error" x-cloak class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
            <p class="text-sm text-red-700" x-text="error"></p>
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

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Chiffre d'affaires (période)</p>
                <p class="text-2xl font-bold text-gray-900 mt-1" x-text="money(stats.revenue_month)"></p>
                <p class="text-xs text-gray-400 mt-2">Année : <span x-text="money(stats.revenue_year)"></span></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Dépenses (période)</p>
                <p class="text-2xl font-bold text-red-600 mt-1" x-text="money(stats.expenses_month)"></p>
                <p class="text-xs text-gray-400 mt-2">Année : <span x-text="money(stats.expenses_year)"></span></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Résultat net (période)</p>
                <p class="text-2xl font-bold mt-1" :class="(stats.profit_month ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600'" x-text="money(stats.profit_month)"></p>
                <p class="text-xs text-gray-400 mt-2">CA − dépenses du mois</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Paiements fournisseurs (mois)</p>
                <p class="text-2xl font-bold text-gray-900 mt-1" x-text="money(stats.supplier_payments_month)"></p>
                <p class="text-xs text-amber-600 mt-2">Dettes fournisseurs : <span x-text="money(stats.supplier_balance_due)"></span></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <template x-for="tile in countTiles" :key="tile.label">
                <div class="bg-white rounded-xl border border-gray-200 p-4 text-center shadow-sm">
                    <p class="text-xs text-gray-500 uppercase tracking-wide" x-text="tile.label"></p>
                    <p class="text-xl font-bold mt-1" :class="tile.color" x-text="number(tile.value)"></p>
                </div>
            </template>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Commandes ce mois</p>
                <p class="text-xl font-semibold text-gray-900" x-text="stats.orders_month ?? '—'"></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Commandes terminées</p>
                <p class="text-xl font-semibold text-gray-900" x-text="stats.orders_completed ?? '—'"></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Stock bas / rupture</p>
                <p class="text-xl font-semibold text-amber-600">
                    <span x-text="stats.low_stock_count ?? '—'"></span> / <span x-text="stats.out_of_stock_count ?? '—'"></span>
                </p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-sm text-gray-500">Canaux vente</p>
                <p class="text-sm font-medium text-gray-900 mt-1">
                    POS : <span x-text="stats.pos_orders ?? '—'"></span> · Shopify : <span x-text="stats.shopify_orders ?? '—'"></span>
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenus vs dépenses (6 derniers mois)</h3>
                <div class="h-72">
                    <canvas x-ref="revenueCanvas"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Modes de paiement (POS)</h3>
                <div class="h-72 flex items-center justify-center" x-ref="paymentWrap">
                    <canvas x-ref="paymentCanvas"></canvas>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-amber-100 bg-amber-50 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Les factures non payées</h3>
                    <p class="text-sm text-amber-800 mt-0.5">
                        <span x-text="unpaidInvoices.count ?? 0"></span> facture(s) ·
                        <span x-text="money(unpaidInvoices.total)"></span> en attente
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
                        <template x-if="!(unpaidInvoices.items || []).length">
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Aucune facture impayée</td></tr>
                        </template>
                        <template x-for="invoice in (unpaidInvoices.items || [])" :key="invoice.number">
                            <tr class="hover:bg-amber-50/50">
                                <td class="px-4 py-3"><a :href="invoice.url" class="text-blue-600 hover:underline font-medium" x-text="invoice.number"></a></td>
                                <td class="px-4 py-3 text-gray-700" x-text="invoice.client"></td>
                                <td class="px-4 py-3 text-right font-semibold text-amber-700" x-text="money(invoice.total)"></td>
                                <td class="px-4 py-3 text-gray-500" x-text="invoice.date"></td>
                                <td class="px-4 py-3 text-gray-500" x-text="invoice.due_date || '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

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
                            <template x-if="!recentOrders.length">
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Aucune commande</td></tr>
                            </template>
                            <template x-for="order in recentOrders" :key="order.ticket">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><a :href="order.url" class="text-blue-600 hover:underline" x-text="order.ticket"></a></td>
                                    <td class="px-4 py-3 text-gray-700" x-text="order.client"></td>
                                    <td class="px-4 py-3 text-right font-medium" x-text="money(order.total)"></td>
                                    <td class="px-4 py-3 text-gray-500" x-text="order.date"></td>
                                </tr>
                            </template>
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
                            <template x-if="!recentInvoices.length">
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Aucune facture</td></tr>
                            </template>
                            <template x-for="invoice in recentInvoices" :key="invoice.number">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><a :href="invoice.url" class="text-blue-600 hover:underline" x-text="invoice.number"></a></td>
                                    <td class="px-4 py-3 text-gray-700" x-text="invoice.client"></td>
                                    <td class="px-4 py-3 text-right font-medium" x-text="money(invoice.total)"></td>
                                    <td class="px-4 py-3 text-gray-500" x-text="invoice.date"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
