@extends('layouts.with-sidebar')

@section('title', 'Rapprochements / trésorerie - Gestion financière clients')
@section('sidebar_page_title', 'Rapprochements / trésorerie')

@php
    $accountLabels = ['caisse' => 'Caisse', 'banque' => 'Banque', 'other' => 'Autre'];
    $stateBadges = [
        'pointe' => ['Pointé', 'bg-green-100 text-green-800'],
        'a_pointer' => ['À pointer', 'bg-amber-100 text-amber-800'],
        'non_synchronise' => ['Sans mouvement', 'bg-red-100 text-red-800'],
        'programme' => ['Programmé', 'bg-gray-100 text-gray-700'],
    ];
@endphp

@section('main')
<main class="flex-1 w-full min-w-0">
    @include('sales.finance._header', [
        'title' => 'Rapprochements / trésorerie',
        'subtitle' => 'Encaissements clients par mode et par jour · état de pointage du mouvement de trésorerie lié',
    ])

    <div class="p-4 sm:p-8">
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg"><p class="text-sm text-green-700">{{ session('success') }}</p></div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg"><p class="text-sm text-red-700">{{ session('error') }}</p></div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="text-xs font-medium text-gray-500 mb-1">Net encaissé</h3>
                <p class="text-xl sm:text-2xl font-bold text-[#0a5d8a]">{{ number_format($totals['net'], 2) }} DH</p>
                <p class="text-xs text-gray-400">{{ $totals['count'] }} règlement(s)</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="text-xs font-medium text-gray-500 mb-1">Caisse (espèces)</h3>
                <p class="text-xl sm:text-2xl font-bold text-violet-700">{{ number_format($totals['caisse'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="text-xs font-medium text-gray-500 mb-1">Banque</h3>
                <p class="text-xl sm:text-2xl font-bold text-blue-700">{{ number_format($totals['banque'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="text-xs font-medium text-gray-500 mb-1">Pointé</h3>
                <p class="text-xl sm:text-2xl font-bold text-green-600">{{ number_format($totals['pointed'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 col-span-2 lg:col-span-1">
                <h3 class="text-xs font-medium text-gray-500 mb-1">Reste à pointer</h3>
                <p class="text-xl sm:text-2xl font-bold text-amber-600">{{ number_format($totals['to_point'], 2) }} DH</p>
                @if($totals['scheduled'] > 0)
                    <p class="text-xs text-gray-400">+ {{ number_format($totals['scheduled'], 2) }} DH programmés</p>
                @endif
            </div>
        </div>

        <x-table-filters
            :action="route('sales-finance.reconciliation')"
            search-placeholder="N° facture, client, référence..."
            grid-cols="md:grid-cols-3 lg:grid-cols-5"
        >
            <div>
                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement</label>
                <select name="payment_method" id="payment_method" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Tous</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ $method }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="state" class="block text-sm font-medium text-gray-700 mb-1">État</label>
                <select name="state" id="state" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Tous</option>
                    @foreach($stateBadges as $key => [$label, $class])
                        <option value="{{ $key }}" @selected(request('state') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-table-filters>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <h3 class="px-4 py-3 text-sm font-semibold text-gray-700 border-b border-gray-200">Par mode de paiement</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Compte</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Nb</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Pointé</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">À pointer</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($byMethod as $row)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $row['method'] }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $accountLabels[$row['account']] ?? $row['account'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ $row['count'] }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ number_format($row['net'], 2) }}</td>
                                    <td class="px-4 py-2 text-right text-green-700">{{ number_format($row['pointed'], 2) }}</td>
                                    <td class="px-4 py-2 text-right text-amber-700">{{ number_format($row['to_point'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Aucun encaissement sur la période.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <h3 class="px-4 py-3 text-sm font-semibold text-gray-700 border-b border-gray-200">Par jour</h3>
                <div class="overflow-x-auto max-h-96">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Nb</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Caisse</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Banque</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total net</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Pointé</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($byDay as $row)
                                <tr>
                                    <td class="px-4 py-2 text-gray-900">{{ $row['day'] !== '—' ? \Illuminate\Support\Carbon::parse($row['day'])->format('d/m/Y') : '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ $row['count'] }}</td>
                                    <td class="px-4 py-2 text-right text-violet-700">{{ number_format($row['caisse'], 2) }}</td>
                                    <td class="px-4 py-2 text-right text-blue-700">{{ number_format($row['banque'], 2) }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ number_format($row['net'], 2) }}</td>
                                    <td class="px-4 py-2 text-right text-green-700">{{ number_format($row['pointed'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('financial.mouvements.point-bulk') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 border-b border-gray-200">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Détail des encaissements</h3>
                    <p class="text-xs text-gray-500">Le pointage utilise le journal des mouvements existant (Gestion Financière → Mouvements).</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('financial.mouvements.reconcile', request()->only(['date_from', 'date_to'])) }}" class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">Rapprochement bancaire</a>
                    <a href="{{ route('financial.tresorerie', request()->only(['date_from', 'date_to'])) }}" class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">Trésorerie globale</a>
                    <button type="submit" class="px-3 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] text-sm font-medium">Pointer la sélection</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 w-8"></th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Compte</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mouvement</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">État</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($detail as $row)
                            @php [$stateLabel, $stateClass] = $stateBadges[$row['state']]; @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">
                                    @if($row['state'] === 'a_pointer' && $row['movement'])
                                        <input type="checkbox" name="ids[]" value="{{ $row['movement']->id }}" class="rounded border-gray-300 text-[#0a5d8a] focus:ring-[#fdb819]">
                                    @endif
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $row['payment']->payment_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    @if($row['payment']->invoice)
                                        <a href="{{ route('invoices.payments.index', $row['payment']->invoice) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ $row['payment']->invoice->invoice_number }}</a>
                                    @else — @endif
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $row['payment']->invoice?->client?->name ?? '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $row['payment']->payment_method ?: '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $accountLabels[$row['account']] ?? $row['account'] }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-right font-semibold">{{ number_format($row['net'], 2) }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $row['movement']?->reference ?? '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $stateClass }}">{{ $stateLabel }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-6 py-12 text-center text-gray-500">Aucun encaissement sur la période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <x-table-pagination :paginator="$detail" :bordered="false" item-label="encaissements" :default-per-page="25" />
    </div>
</main>
@endsection
