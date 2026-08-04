@extends('layouts.with-sidebar')

@section('title', 'Gestion Financière')

@section('sidebar_page_title', 'Gestion Financière')

@php
    $statusLabels = [
        'paid' => 'Payé',
        'partial' => 'Partiellement payé',
        'unpaid' => 'À payer',
    ];
    $statusClasses = [
        'paid' => 'bg-emerald-100 text-emerald-800',
        'partial' => 'bg-amber-100 text-amber-800',
        'unpaid' => 'bg-red-100 text-red-800',
    ];
@endphp

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Gestion financière</h2>
                    <p class="text-sm text-gray-600 mt-1">Vue centralisée des revenus, dépenses, paiements, TVA et trésorerie.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            onclick="document.getElementById('finance-overview-help')?.classList.toggle('hidden')"
                            class="inline-flex items-center justify-center px-4 py-2 border border-[#c9a227] rounded-lg text-sm font-medium text-[#8a6d1b] bg-white hover:bg-amber-50 whitespace-nowrap">
                        Voir les explications
                    </button>
                    <form method="GET" action="{{ route('financial.index') }}" class="inline-flex">
                        <input type="hidden" name="operation_type" value="{{ $operationType }}">
                        <input type="hidden" name="payment_status" value="{{ $paymentStatus }}">
                        <input type="hidden" name="q" value="{{ $search }}">
                        <select name="month" onchange="this.form.submit()"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-800">
                            @foreach($monthOptions as $opt)
                                <option value="{{ $opt['value'] }}" @selected($selectedMonth === $opt['value'])>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </form>
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium text-white bg-[#0a5d8a] hover:bg-[#084a6e] whitespace-nowrap">
                            + Nouvelle opération
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-2 w-56 rounded-lg border border-gray-200 bg-white shadow-lg z-20 py-1">
                            <a href="{{ route('invoices.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Facture client</a>
                            <a href="{{ route('supplier-invoices.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Facture fournisseur</a>
                            <a href="{{ route('expenses-with-invoice.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Dépense avec facture</a>
                            <a href="{{ route('expenses-without-invoice.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Dépense sans facture</a>
                            <a href="{{ route('sales.payments.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Paiement client</a>
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('financial.index') }}" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date de début</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date de fin</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Type d'opération</label>
                    <select name="operation_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="all" @selected($operationType === 'all')>Tous</option>
                        <option value="encaissement" @selected($operationType === 'encaissement')>Encaissements</option>
                        <option value="decaissement" @selected($operationType === 'decaissement')>Décaissements</option>
                        <option value="pos" @selected($operationType === 'pos')>Ventes POS</option>
                        <option value="sale" @selected($operationType === 'sale')>Paiements clients</option>
                        <option value="purchase" @selected($operationType === 'purchase')>Paiements fournisseurs</option>
                        <option value="expense" @selected($operationType === 'expense')>Dépenses</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Statut paiement</label>
                    <select name="payment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">Tous (créances / dettes)</option>
                        <option value="unpaid" @selected($paymentStatus === 'unpaid')>À payer</option>
                        <option value="partial" @selected($paymentStatus === 'partial')>Partiellement payé</option>
                        <option value="paid" @selected($paymentStatus === 'paid')>Payé</option>
                    </select>
                </div>
                <div class="sm:col-span-2 xl:col-span-1">
                    <label class="block text-xs text-gray-500 mb-1">Recherche</label>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Réf., tiers…" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium hover:bg-[#084a6e]">Filtrer</button>
                    <a href="{{ route('financial.index') }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-8">
        <div id="finance-overview-help" class="hidden rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            La vue d'ensemble agrège vos données réelles : chiffre d'affaires, achats, dépenses, TVA, trésorerie, créances et dettes. Utilisez les onglets pour entrer dans chaque module.
        </div>

        @if(isset($health))
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs text-gray-500 mb-1">Source des données</p>
                <p class="text-sm font-semibold text-gray-900">Ventes, achats, POS, paiements et dépenses</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs text-gray-500 mb-1">État de la période</p>
                <p class="text-sm font-semibold text-emerald-600">{{ $health['status_label'] }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs text-gray-500 mb-1">Anomalies détectées</p>
                <p class="text-sm font-semibold {{ $health['anomalies_count'] > 0 ? 'text-orange-600' : 'text-gray-900' }}">
                    {{ $health['anomalies_count'] > 0 ? $health['anomalies_count'].' élément'.($health['anomalies_count'] > 1 ? 's' : '') : 'Aucun' }}
                </p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <p class="text-xs text-gray-500 mb-1">Dernière mise à jour</p>
                <p class="text-sm font-semibold text-gray-900">{{ $health['last_updated_label'] }}</p>
            </div>
        </div>
        @endif

        {{-- KPIs activité --}}
        <section>
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Activité de la période</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Chiffre d'affaires</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($overview['revenue'], 2) }} DH</p>
                    <p class="text-xs text-gray-400 mt-2">Factures : {{ number_format($overview['revenue_invoices'], 2) }} · POS : {{ number_format($overview['revenue_pos'], 2) }}@if($overview['revenue_credit_notes'] > 0) · Avoirs : −{{ number_format($overview['revenue_credit_notes'], 2) }}@endif</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Achats</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($overview['purchases'], 2) }} DH</p>
                    <p class="text-xs text-gray-400 mt-2">Factures fourn. : {{ number_format($overview['supplier_purchases'], 2) }}@if($overview['supplier_credit_notes'] > 0) · Avoirs : −{{ number_format($overview['supplier_credit_notes'], 2) }}@endif</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Dépenses</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($overview['expenses'], 2) }} DH</p>
                    <p class="text-xs text-gray-400 mt-2">Avec facture : {{ number_format($overview['expenses_with_invoice'], 2) }} · Sans : {{ number_format($overview['expenses_without_invoice'], 2) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Résultat estimé</p>
                    <p class="text-2xl font-bold mt-1 {{ $overview['estimated_result'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                        {{ number_format($overview['estimated_result'], 2) }} DH
                    </p>
                    <p class="text-xs text-gray-400 mt-2">CA − achats − dépenses</p>
                </div>
            </div>
        </section>

        {{-- TVA --}}
        <section>
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">TVA (période)</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">TVA collectée (ventes)</p>
                    <p class="text-2xl font-bold text-sky-700 mt-1">{{ number_format($overview['vat_collected'], 2) }} DH</p>
                    <p class="text-xs text-gray-400 mt-2">
                        Factures {{ number_format($overview['vat_details']['collected_invoices'], 2) }}
                        · POS {{ number_format($overview['vat_details']['collected_pos'], 2) }}
                        @if($overview['vat_details']['collected_credit_notes'] > 0)
                            · Avoirs −{{ number_format($overview['vat_details']['collected_credit_notes'], 2) }}
                        @endif
                    </p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">TVA déductible (achats)</p>
                    <p class="text-2xl font-bold text-violet-700 mt-1">{{ number_format($overview['vat_deductible'], 2) }} DH</p>
                    <p class="text-xs text-gray-400 mt-2">
                        Achats {{ number_format($overview['vat_details']['deductible_purchases'], 2) }}
                        · Dépenses {{ number_format($overview['vat_details']['deductible_expenses'], 2) }}
                        @if($overview['vat_details']['deductible_credit_notes'] > 0)
                            · Avoirs −{{ number_format($overview['vat_details']['deductible_credit_notes'], 2) }}
                        @endif
                    </p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">TVA nette à payer</p>
                    <p class="text-2xl font-bold mt-1 {{ $overview['vat_net'] >= 0 ? 'text-orange-600' : 'text-emerald-600' }}">
                        {{ number_format($overview['vat_net'], 2) }} DH
                    </p>
                    <p class="text-xs text-gray-400 mt-2">Collectée − déductible</p>
                </div>
            </div>
        </section>

        {{-- Trésorerie --}}
        <section>
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Trésorerie</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Argent disponible</p>
                    <p class="text-2xl font-bold mt-1 {{ $overview['treasury_total'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                        {{ number_format($overview['treasury_total'], 2) }} DH
                    </p>
                    <p class="text-xs text-gray-400 mt-2">Solde cumulé (toutes périodes)</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Solde caisse</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($overview['treasury_caisse'], 2) }} DH</p>
                    <p class="text-xs text-gray-400 mt-2">Espèces</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Solde banque</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($overview['treasury_banque'], 2) }} DH</p>
                    <p class="text-xs text-gray-400 mt-2">Carte, virement, chèque</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Flux net (période)</p>
                    <p class="text-xl font-bold mt-1 {{ $overview['net_cash_flow'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                        {{ number_format($overview['net_cash_flow'], 2) }} DH
                    </p>
                    <p class="text-xs text-gray-400 mt-2">Encaissements − décaissements</p>
                </div>
            </div>
            @if($overview['treasury_other'] != 0)
                <p class="text-xs text-gray-500 mt-2">Autres modes / non classés : {{ number_format($overview['treasury_other'], 2) }} DH</p>
            @endif
        </section>

        {{-- Encaissements / décaissements / statuts --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Encaissements (période)</p>
                <p class="text-xl font-bold text-green-600 mt-1">{{ number_format($overview['client_payments'], 2) }} DH</p>
                <p class="text-xs text-gray-400 mt-2">POS {{ number_format($overview['cash_in_pos'], 2) }} · Paiements factures {{ number_format($overview['cash_in_invoices'], 2) }}</p>
                <a href="{{ route('sales.payments.index') }}" class="text-xs text-blue-600 hover:text-blue-800 mt-2 inline-block">Paiements ventes →</a>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500">Décaissements (période)</p>
                <p class="text-xl font-bold text-red-600 mt-1">{{ number_format($overview['decaissements'], 2) }} DH</p>
                <p class="text-xs text-gray-400 mt-2">Fournisseurs {{ number_format($overview['supplier_payments'], 2) }} · Dépenses {{ number_format($overview['expenses'], 2) }}</p>
                <a href="{{ route('purchases.payments.index') }}" class="text-xs text-blue-600 hover:text-blue-800 mt-2 inline-block">Paiements achats →</a>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500 mb-2">Statuts de paiement</p>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <span class="text-gray-600">Clients</span>
                        <span class="text-gray-900 text-right text-xs sm:text-sm">
                            {{ $overview['payment_statuses']['clients']['paid'] }} payé ·
                            {{ $overview['payment_statuses']['clients']['partial'] }} partiel ·
                            {{ $overview['payment_statuses']['clients']['unpaid'] }} à payer
                        </span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="text-gray-600">Fournisseurs</span>
                        <span class="text-gray-900 text-right text-xs sm:text-sm">
                            {{ $overview['payment_statuses']['suppliers']['paid'] }} payé ·
                            {{ $overview['payment_statuses']['suppliers']['partial'] }} partiel ·
                            {{ $overview['payment_statuses']['suppliers']['unpaid'] }} à payer
                        </span>
                    </div>
                    <div class="flex justify-between pt-1 border-t border-gray-100">
                        <span class="text-gray-500">Créances / Dettes</span>
                        <span class="font-semibold text-amber-700">
                            {{ number_format($overview['client_receivables'], 2) }} / {{ number_format($overview['supplier_payables'], 2) }} DH
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Déclaration --}}
        <section class="bg-white rounded-xl border border-gray-200 p-5 sm:p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Déclaration (synthèse fiscale)</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Informations nécessaires pour la période sélectionnée</p>
                </div>
                <a href="{{ route('financial.export', request()->only(['date_from', 'date_to'])) }}"
                   class="text-sm font-medium text-[#0a5d8a] hover:underline">Télécharger la synthèse →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2 text-gray-600">TVA collectée</td>
                            <td class="py-2 text-right font-medium">{{ number_format($overview['vat_collected'], 2) }} DH</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600">TVA déductible</td>
                            <td class="py-2 text-right font-medium">{{ number_format($overview['vat_deductible'], 2) }} DH</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-900 font-medium">TVA nette à payer</td>
                            <td class="py-2 text-right font-bold {{ $overview['vat_net'] >= 0 ? 'text-orange-600' : 'text-emerald-600' }}">
                                {{ number_format($overview['vat_net'], 2) }} DH
                            </td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600">Chiffre d'affaires TTC</td>
                            <td class="py-2 text-right font-medium">{{ number_format($overview['revenue'], 2) }} DH</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600">Achats TTC</td>
                            <td class="py-2 text-right font-medium">{{ number_format($overview['purchases'], 2) }} DH</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600">Dépenses</td>
                            <td class="py-2 text-right font-medium">{{ number_format($overview['expenses'], 2) }} DH</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Charts --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenus vs achats vs dépenses</h3>
                <div class="h-64 sm:h-72">
                    <canvas id="revenueExpensesChart"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Flux de trésorerie</h3>
                <div class="h-64 sm:h-72">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Mouvements --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Mouvements financiers</h3>
                <p class="text-sm text-gray-500 mt-0.5">Encaissements et décaissements filtrés sur la période</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px]">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tiers</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Mode</th>
                            <th class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentTransactions as $transaction)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-sm text-gray-900">{{ $transaction['date_formatted'] }}</td>
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-sm text-gray-700">{{ $transaction['label'] }}</td>
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-sm">
                                    @if($transaction['url'])
                                        <a href="{{ $transaction['url'] }}" class="text-blue-600 hover:text-blue-800">{{ $transaction['reference'] }}</a>
                                    @else
                                        {{ $transaction['reference'] }}
                                    @endif
                                </td>
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-sm text-gray-700">{{ $transaction['party'] }}</td>
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-sm text-gray-500 hidden md:table-cell">{{ $transaction['method'] ?? '—' }}</td>
                                <td class="px-4 sm:px-6 py-3 whitespace-nowrap text-sm text-right font-semibold {{ $transaction['direction'] === 'in' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $transaction['direction'] === 'in' ? '+' : '−' }}{{ number_format($transaction['amount'], 2) }} DH
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">Aucun mouvement sur la période / filtres sélectionnés</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Historiques --}}
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            @foreach([
                ['title' => 'Historique ventes', 'items' => $history['sales'], 'empty' => 'Aucune vente sur la période'],
                ['title' => 'Historique achats', 'items' => $history['purchases'], 'empty' => 'Aucun achat sur la période'],
                ['title' => 'Historique dépenses', 'items' => $history['expenses'], 'empty' => 'Aucune dépense sur la période'],
            ] as $block)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-4 sm:px-5 py-3 border-b border-gray-200">
                        <h3 class="text-base font-semibold text-gray-900">{{ $block['title'] }}</h3>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                        @forelse($block['items'] as $item)
                            <a href="{{ $item['url'] }}" class="flex items-start justify-between gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $item['reference'] }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $item['date'] }} · {{ $item['party'] }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ number_format($item['total'], 2) }} DH</p>
                                    <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-[10px] font-medium {{ $statusClasses[$item['status']] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $statusLabels[$item['status']] ?? $item['status'] }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <p class="px-5 py-8 text-sm text-gray-500 text-center">{{ $block['empty'] }}</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Créances / Dettes --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden">
                <div class="px-4 sm:px-6 py-4 border-b border-amber-100 bg-amber-50 flex flex-wrap justify-between items-center gap-3">
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
                        <a href="{{ $item['url'] }}" class="flex items-center justify-between gap-3 px-4 sm:px-6 py-3 hover:bg-gray-50 transition">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $item['number'] }} — {{ $item['party'] }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $item['date'] }}@if($item['due_date']) · Échéance {{ $item['due_date'] }}@endif
                                    · <span class="{{ $statusClasses[$item['status']] ?? '' }} px-1.5 py-0.5 rounded">{{ $statusLabels[$item['status']] ?? $item['status'] }}</span>
                                </p>
                            </div>
                            <span class="text-sm font-semibold text-amber-700 shrink-0">{{ number_format($item['remaining'], 2) }} DH</span>
                        </a>
                    @empty
                        <p class="px-6 py-8 text-sm text-gray-500 text-center">Aucune créance en attente</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl border border-indigo-200 shadow-sm overflow-hidden">
                <div class="px-4 sm:px-6 py-4 border-b border-indigo-100 bg-indigo-50 flex flex-wrap justify-between items-center gap-3">
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
                        <a href="{{ $item['url'] }}" class="flex items-center justify-between gap-3 px-4 sm:px-6 py-3 hover:bg-gray-50 transition">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $item['number'] }} — {{ $item['party'] }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $item['date'] }}@if($item['due_date']) · Échéance {{ $item['due_date'] }}@endif
                                    · <span class="{{ $statusClasses[$item['status']] ?? '' }} px-1.5 py-0.5 rounded">{{ $statusLabels[$item['status']] ?? $item['status'] }}</span>
                                </p>
                            </div>
                            <span class="text-sm font-semibold text-indigo-700 shrink-0">{{ number_format($item['remaining'], 2) }} DH</span>
                        </a>
                    @empty
                        <p class="px-6 py-8 text-sm text-gray-500 text-center">Aucune dette en attente</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    function canvasReady(canvas) {
        return !!(canvas
            && canvas.isConnected
            && typeof canvas.getContext === 'function'
            && canvas.getContext('2d'));
    }

    function destroyIfExists(canvas) {
        if (!window.Chart || !canvas || typeof window.Chart.getChart !== 'function') {
            return;
        }
        const existing = window.Chart.getChart(canvas);
        if (existing) {
            try { existing.destroy(); } catch (e) {}
        }
    }

    function initFinancialCharts() {
        if (!window.Chart) {
            return;
        }

        const brandGold = '#fdb819';
        const revenueCanvas = document.getElementById('revenueExpensesChart');
        const cashFlowCanvas = document.getElementById('cashFlowChart');

        if (!canvasReady(revenueCanvas) || !canvasReady(cashFlowCanvas)) {
            return;
        }

        destroyIfExists(revenueCanvas);
        destroyIfExists(cashFlowCanvas);

        try {
            new Chart(revenueCanvas, {
                type: 'bar',
                data: {
                    labels: @json($chart['labels']),
                    datasets: [
                        {
                            label: 'CA',
                            data: @json($chart['revenue']),
                            backgroundColor: brandGold,
                            borderRadius: 4,
                        },
                        {
                            label: 'Achats',
                            data: @json($chart['purchases']),
                            backgroundColor: '#6366f1',
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
                    animation: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('fr-FR') + ' DH' } }
                    }
                }
            });
        } catch (e) {
            console.warn('[Financial] revenue chart failed', e);
        }

        try {
            new Chart(cashFlowCanvas, {
                type: 'line',
                data: {
                    labels: @json($chart['labels']),
                    datasets: [
                        {
                            label: 'Encaissements',
                            data: @json($chart['cashIn']),
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            fill: true,
                            tension: 0.3,
                        },
                        {
                            label: 'Décaissements',
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
                    animation: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('fr-FR') + ' DH' } }
                    }
                }
            });
        } catch (e) {
            console.warn('[Financial] cash flow chart failed', e);
        }
    }

    if (window.SoftNav && typeof SoftNav.whenReady === 'function') {
        SoftNav.whenReady(initFinancialCharts);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFinancialCharts);
    } else {
        initFinancialCharts();
    }
})();
</script>
@endpush
