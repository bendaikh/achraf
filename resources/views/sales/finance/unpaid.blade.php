@extends('layouts.with-sidebar')

@section('title', 'Impayés / reste à payer - Gestion financière clients')
@section('sidebar_page_title', 'Impayés / reste à payer')

@section('main')
<main class="flex-1 w-full min-w-0">
    @include('sales.finance._header', [
        'title' => 'Impayés / reste à payer',
        'subtitle' => 'Factures clients non soldées · solde = total TTC − paiements enregistrés (même règle que Gestion des paiements)',
    ])

    <div class="p-4 sm:p-8">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Factures non soldées</h3>
                <p class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $totals['count'] }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $totals['clients'] }} client(s)</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Total facturé</h3>
                <p class="text-2xl sm:text-3xl font-bold text-gray-900">{{ number_format($totals['total'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Déjà encaissé</h3>
                <p class="text-2xl sm:text-3xl font-bold text-green-600">{{ number_format($totals['paid'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Reste à payer</h3>
                <p class="text-2xl sm:text-3xl font-bold text-red-600">{{ number_format($totals['remaining'], 2) }} DH</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Balance âgée (retard / échéance)</h3>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    @foreach($buckets as $bucket)
                        @php $isCurrent = request('bucket') === $bucket['key']; @endphp
                        <a href="{{ request()->fullUrlWithQuery(['bucket' => $isCurrent ? null : $bucket['key'], 'page' => null]) }}"
                           class="rounded-lg border px-3 py-2 {{ $isCurrent ? 'border-[#fdb819] bg-amber-50' : 'border-gray-200 hover:border-[#fdb819]' }}">
                            <div class="text-xs font-medium text-gray-500">{{ $bucket['label'] }}</div>
                            <div class="text-sm font-bold {{ $bucket['key'] === 'not_due' ? 'text-gray-900' : 'text-red-600' }}">{{ number_format($bucket['amount'], 2) }} DH</div>
                            <div class="text-xs text-gray-400">{{ $bucket['count'] }} facture(s)</div>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Principaux clients débiteurs</h3>
                <ul class="divide-y divide-gray-100">
                    @forelse($topClients as $client)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="truncate text-gray-800">{{ $client['name'] }} <span class="text-gray-400">({{ $client['count'] }})</span></span>
                            <span class="font-semibold text-red-600 whitespace-nowrap">{{ number_format($client['remaining'], 2) }} DH</span>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500">Aucun impayé.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <x-table-filters
            :action="route('sales-finance.unpaid')"
            search-placeholder="N° facture, client, commande..."
            grid-cols="md:grid-cols-3 lg:grid-cols-5"
        >
            <div>
                <label for="overdue" class="block text-sm font-medium text-gray-700 mb-1">Échéance</label>
                <select name="overdue" id="overdue" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Toutes</option>
                    <option value="1" @selected(request('overdue') === '1')>En retard uniquement</option>
                </select>
            </div>
            @if(request('bucket'))
                <input type="hidden" name="bucket" value="{{ request('bucket') }}">
            @endif
        </x-table-filters>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Échéance</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total TTC</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Encaissé</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Reste à payer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Retard</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dernier paiement</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($invoices as $invoice)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ $invoice->invoice_number }}</a>
                                    @if($invoice->posSale)
                                        <div class="text-xs text-gray-400">{{ $invoice->posSale->ticket_number }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $invoice->client?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $invoice->invoice_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-semibold text-gray-900">{{ number_format($invoice->fin_total, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-green-700">{{ number_format($invoice->fin_paid, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-bold text-red-600">{{ number_format($invoice->fin_remaining, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($invoice->fin_days_late > 0)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $invoice->fin_days_late > 60 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">{{ $invoice->fin_days_late }} j</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Non échu</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $invoice->last_payment_date ? \Illuminate\Support\Carbon::parse($invoice->last_payment_date)->format('d/m/Y') : '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('invoices.payments.index', $invoice) }}" class="text-indigo-600 hover:text-indigo-900">Encaisser</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center text-sm text-gray-500">Aucune facture non soldée.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-table-pagination :paginator="$invoices" :bordered="false" item-label="factures" :default-per-page="25" />
    </div>
</main>
@endsection
