@extends('layouts.with-sidebar')

@section('title', 'Encaissements - Gestion financière clients')
@section('sidebar_page_title', 'Encaissements')

@section('main')
<main class="flex-1 w-full min-w-0">
    @include('sales.finance._header', [
        'title' => 'Encaissements',
        'subtitle' => 'Règlements clients reçus (toutes factures) · filtres par période, mode et origine',
    ])

    <div class="p-4 sm:p-8">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Règlements</h3>
                <p class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $totals['count'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Montant réglé (brut)</h3>
                <p class="text-2xl sm:text-3xl font-bold text-green-600">{{ number_format($totals['gross'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Frais retenus</h3>
                <p class="text-2xl sm:text-3xl font-bold text-amber-600">{{ number_format($totals['fees'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Net reçu</h3>
                <p class="text-2xl sm:text-3xl font-bold text-[#0a5d8a]">{{ number_format($totals['net'], 2) }} DH</p>
            </div>
        </div>

        @if($byMethod->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Répartition par mode de paiement</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($byMethod as $row)
                        <a href="{{ request()->fullUrlWithQuery(['payment_method' => $row->method === 'Non renseigné' ? null : $row->method, 'page' => null]) }}"
                           class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-[#fdb819]">
                            <span class="font-medium text-gray-800">{{ $row->method }}</span>
                            <span class="text-gray-500">{{ $row->cnt }}×</span>
                            <span class="font-semibold text-green-700">{{ number_format((float) $row->total, 2) }} DH</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-table-filters
            :action="route('sales-finance.receipts')"
            search-placeholder="N° facture, client, référence, tracking..."
            grid-cols="md:grid-cols-3 lg:grid-cols-6"
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
                <label for="payment_source" class="block text-sm font-medium text-gray-700 mb-1">Origine</label>
                <select name="payment_source" id="payment_source" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Toutes</option>
                    <option value="manual" @selected(request('payment_source') === 'manual')>Manuel</option>
                    <option value="bulk" @selected(request('payment_source') === 'bulk')>Groupé</option>
                    <option value="import" @selected(request('payment_source') === 'import')>Import fichier</option>
                </select>
            </div>
            <div>
                <label for="realization" class="block text-sm font-medium text-gray-700 mb-1">Réalisation</label>
                <select name="realization" id="realization" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Tous</option>
                    <option value="realized" @selected(request('realization') === 'realized')>Réalisé</option>
                    <option value="scheduled" @selected(request('realization') === 'scheduled')>Programmé (date future)</option>
                </select>
            </div>
        </x-table-filters>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Référence</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Frais</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origine</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saisi par</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($payments as $payment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $payment->payment_date?->format('d/m/Y') }}
                                    @if($payment->isScheduled())
                                        <span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-800">Programmé</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($payment->invoice)
                                        <a href="{{ route('invoices.payments.index', $payment->invoice) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ $payment->invoice->invoice_number }}</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $payment->invoice?->client?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $payment->payment_method ?: '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $payment->payment_reference ?: ($payment->tracking_number ?: '—') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-green-700">{{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-amber-700">{{ $payment->delivery_fees !== null ? number_format((float) $payment->delivery_fees, 2) : '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">{{ number_format((float) ($payment->net_received ?? $payment->amount), 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $payment->sourceLabel() }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $payment->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center text-sm text-gray-500">Aucun encaissement pour ces critères.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-table-pagination :paginator="$payments" :bordered="false" item-label="encaissements" :default-per-page="25" />
    </div>
</main>
@endsection
