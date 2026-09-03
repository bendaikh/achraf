@extends('layouts.with-sidebar')

@section('title', 'Règlement de paiement')

@section('main')
@php
    $isFullyPaid = $invoice->remaining_balance <= 0.009;
    $currency = $invoice->currency;
@endphp
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Règlement de paiement</h2>
                <p class="text-sm text-gray-600 mt-1">Facture {{ $invoice->invoice_number }} - {{ $invoice->client->name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('sales.payments.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Gestion Paiement
                </a>
                <a href="{{ route('invoices.show', $invoice) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Voir la facture
                </a>
            </div>
        </div>
    </header>

    <div class="p-8">
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        @if($isFullyPaid)
            <div class="mb-6 bg-green-100 border border-green-300 rounded-xl px-6 py-4 flex items-center gap-3">
                <svg class="h-6 w-6 text-green-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-green-800">Facture entièrement réglée</p>
                    <p class="text-sm text-green-700 mt-0.5">Statut : RÉGLÉE — aucun nouveau paiement n’est nécessaire.</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Montant facture</h3>
                <p class="text-3xl font-bold text-gray-900">{{ number_format($invoice->computed_total, 2) }} {{ $currency }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Total réglé</h3>
                <p class="text-3xl font-bold text-green-600">{{ number_format($invoice->total_paid, 2) }} {{ $currency }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Solde restant</h3>
                <p class="text-3xl font-bold {{ $isFullyPaid ? 'text-green-600' : 'text-red-600' }}">{{ number_format($invoice->remaining_balance, 2) }} {{ $currency }}</p>
            </div>
        </div>

        @if(! $isFullyPaid)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Ajouter un paiement</h3>
            <form action="{{ route('invoices.payments.store', $invoice) }}" method="POST" enctype="multipart/form-data" id="invoicePaymentForm">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date de paiement *</label>
                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Montant facture *</label>
                        <input type="number" step="0.01" name="amount" id="pay_amount" required value="{{ old('amount', number_format($invoice->remaining_balance, 2, '.', '')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="0.00" oninput="recalcPaymentNet()">
                        <p class="text-xs text-gray-500 mt-1">Solde restant : {{ number_format($invoice->remaining_balance, 2) }} {{ $currency }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Frais livraison / commission</label>
                        <input type="number" step="0.01" name="delivery_fees" id="pay_fees" value="{{ old('delivery_fees') }}" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="0.00" oninput="recalcPaymentNet()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Net encaissé</label>
                        <input type="number" step="0.01" name="net_received" id="pay_net" value="{{ old('net_received') }}" min="0" readonly class="w-full px-3 py-2 border border-green-300 bg-green-50 rounded-lg text-green-800 font-semibold" placeholder="Calculé automatiquement">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Méthode de paiement *</label>
                        <select name="payment_method" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Sélectionner</option>
                            <option value="Espèces">Espèces</option>
                            <option value="Chèque">Chèque</option>
                            <option value="Virement bancaire">Virement bancaire</option>
                            <option value="Carte bancaire">Carte bancaire</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Référence</label>
                        <input type="text" name="payment_reference" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="N° chèque, référence...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tracking</label>
                        <input type="text" name="tracking_number" value="{{ old('tracking_number', $invoice->posSale?->primaryTrackingNumber()) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="N° suivi transporteur">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Justificatif de paiement</label>
                        <input type="file" name="payment_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <input type="text" name="notes" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Notes optionnelles...">
                    </div>
                    <div class="md:col-span-3">
                        <label class="inline-flex items-center gap-2 text-sm text-amber-800">
                            <input type="checkbox" name="allow_overpayment" value="1" class="rounded border-gray-300">
                            Autoriser un montant supérieur au solde restant
                        </label>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                        Ajouter le paiement
                    </button>
                </div>
            </form>
        </div>
        @endif

        @include('sales.invoices.payments.partials.history-table', [
            'invoice' => $invoice,
            'payments' => $invoice->payments,
            'showActions' => true,
            'compact' => false,
        ])

        @if($invoice->activities->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mt-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Historique de rapprochement</h3>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($invoice->activities as $activity)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm text-gray-900">{{ $activity->description }}</p>
                                @if(is_array($activity->metadata) && ! empty($activity->metadata))
                                    <div class="mt-2 text-xs text-gray-500 space-y-1">
                                        @if(! empty($activity->metadata['order_number']))
                                            <div>Commande : {{ $activity->metadata['order_number'] }}</div>
                                        @endif
                                        @if(! empty($activity->metadata['import_file']))
                                            <div>Fichier : {{ $activity->metadata['import_file'] }}</div>
                                        @endif
                                        @if(! empty($activity->metadata['match_criteria']))
                                            <div>Critères : {{ collect($activity->metadata['match_criteria'])->map(fn ($c) => \App\Services\PaymentMatchingService::CRITERIA[$c]['label'] ?? $c)->implode(', ') }}</div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="text-right text-xs text-gray-500 shrink-0">
                                <div>{{ $activity->occurred_at?->format('d/m/Y H:i') }}</div>
                                <div>{{ $activity->actor?->name ?? 'Système' }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</main>
@if(! $isFullyPaid)
<script>
function recalcPaymentNet() {
    var amount = parseFloat(document.getElementById('pay_amount').value || '0');
    var feesInput = document.getElementById('pay_fees').value;
    var netEl = document.getElementById('pay_net');
    if (feesInput === '' || feesInput === null) {
        netEl.value = '';
        return;
    }
    var fees = parseFloat(feesInput || '0');
    netEl.value = Math.max(0, amount - fees).toFixed(2);
}
recalcPaymentNet();
</script>
@endif
@endsection
