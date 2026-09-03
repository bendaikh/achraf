@extends('layouts.with-sidebar')

@section('title', 'Paiement groupé')

@section('main')
@php
    $first = $payments->first();
@endphp
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Détail du paiement groupé</h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $payments->count() }} facture(s)
                    @if($first?->payment_date) · {{ $first->payment_date->format('d/m/Y') }} @endif
                    @if($first?->payment_reference) · Réf. {{ $first->payment_reference }} @endif
                </p>
            </div>
            <a href="{{ route('sales.payments.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">Retour</a>
        </div>
    </header>

    <div class="p-8">
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Total payé (factures)</h3>
                <p class="text-3xl font-bold text-gray-900">{{ number_format($totals['gross'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Frais transporteur total</h3>
                <p class="text-3xl font-bold text-amber-700">{{ number_format($totals['fees'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Net encaissé</h3>
                <p class="text-3xl font-bold text-green-600">{{ number_format($totals['net'], 2) }} DH</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Répartition facture par facture</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant facture</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frais</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Net encaissé</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($payments as $payment)
                            @php
                                $inv = $payment->invoice;
                                $gross = $payment->resolvedGrossAmount();
                                $fees = $payment->resolvedDeliveryFees();
                                $net = $payment->resolvedNetReceived();
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm">
                                    @if($inv)
                                        <a href="{{ route('invoices.show', $inv) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $inv->invoice_number }}
                                        </a>
                                        <div class="text-xs text-gray-500">
                                            <a href="{{ route('invoices.payments.index', $inv) }}" class="hover:underline">Voir l’historique</a>
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $inv?->client?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold">{{ number_format($gross, 2) }} DH</td>
                                <td class="px-4 py-3 text-sm">{{ $fees !== null ? number_format($fees, 2).' DH' : '—' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-green-700">{{ $net !== null ? number_format($net, 2).' DH' : '—' }}</td>
                                <td class="px-4 py-3 text-sm font-mono">{{ $payment->resolvedTrackingNumber($inv) ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if($inv && $inv->remaining_balance <= 0.009)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Réglée</span>
                                    @elseif($inv)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Partielle</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 max-w-3xl text-sm text-gray-600">
            <p><span class="font-medium text-gray-900">Méthode :</span> {{ $first->payment_method ?? '—' }}</p>
            <p class="mt-1"><span class="font-medium text-gray-900">Référence :</span> {{ $first->payment_reference ?? '—' }}</p>
            <p class="mt-1"><span class="font-medium text-gray-900">Notes :</span> {{ $first->notes ?? '—' }}</p>
            <p class="mt-1"><span class="font-medium text-gray-900">Enregistré par :</span> {{ $first->user->name ?? '—' }}
                @if($first->created_at) le {{ $first->created_at->format('d/m/Y H:i') }} @endif
            </p>
            <p class="mt-3 text-xs text-gray-400">Lot #{{ $batchId }}</p>
        </div>
    </div>
</main>
@endsection
