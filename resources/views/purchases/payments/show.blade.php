@extends('layouts.with-sidebar')

@section('title', 'Règlement '.$payment->payment_number)

@section('main')
@php
    $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
    $snapshot = $snapshot ?? ['invoices' => [], 'credits' => [], 'advances' => []];
@endphp
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $payment->payment_number }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ $payment->supplier?->name }} · {{ $payment->statusLabel() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('purchases.payments.print', $payment) }}" target="_blank" class="px-4 py-2 bg-gray-100 rounded-lg text-sm">Imprimer le règlement</a>
                <a href="{{ route('purchases.payments.pdf', $payment) }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Télécharger PDF</a>
                @unless($payment->isCancelled())
                    <a href="{{ route('purchases.payments.edit', $payment) }}" class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm">Modifier</a>
                @endunless
                <a href="{{ route('purchases.payments.settle', $payment->supplier) }}" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg text-sm">Compte fournisseur</a>
            </div>
        </div>
    </header>

    <div class="p-8 space-y-6">
        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg"><p class="text-sm text-green-700">{{ session('success') }}</p></div>
        @endif

        <div class="grid md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">Fournisseur</p><p class="font-semibold">{{ $payment->supplier?->name }}</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">Date du règlement</p><p class="font-semibold">{{ $payment->payment_date?->format('d/m/Y') }}</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">Mode de paiement</p><p class="font-semibold">{{ $payment->payment_method }}</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">Montant décaissé</p><p class="font-semibold">{{ $fmt($payment->amount) }} DH</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">Référence</p><p class="font-semibold">{{ $payment->payment_reference ?: '—' }}</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">Statut</p><p class="font-semibold">{{ $payment->statusLabel() }}</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">Créé par</p><p class="font-semibold">{{ $payment->user?->name ?: '—' }}</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">Date/heure de création</p><p class="font-semibold">{{ $payment->created_at?->format('d/m/Y H:i') }}</p></div>
            <div class="bg-white rounded-xl border p-4 md:col-span-1"><p class="text-xs text-gray-500">Notes</p><p class="font-semibold">{{ $payment->notes ?: '—' }}</p></div>
        </div>

        <div class="bg-white rounded-xl border p-6">
            <h3 class="font-semibold mb-2">Justificatif importé</h3>
            <p class="text-xs text-gray-500 mb-3">Document distinct du PDF généré par Libromart.</p>
            <x-managed-document-actions
                type="supplier-payment-headers"
                :id="$payment->id"
                :show-add="! $payment->isCancelled()"
            />
        </div>

        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-6 py-4 border-b"><h3 class="font-semibold">Affectation du règlement</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Facture</th>
                            <th class="px-4 py-3 text-right">Montant facture</th>
                            <th class="px-4 py-3 text-right">Déjà payé avant</th>
                            <th class="px-4 py-3 text-right">Avoir imputé</th>
                            <th class="px-4 py-3 text-right">Avance imputée</th>
                            <th class="px-4 py-3 text-right">Montant de ce règlement</th>
                            <th class="px-4 py-3 text-right">Reste après</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse(($snapshot['invoices'] ?? []) as $line)
                            <tr>
                                <td class="px-4 py-3">{{ $line['invoice_number'] }}</td>
                                <td class="px-4 py-3 text-right">{{ $fmt($line['invoice_amount'] ?? 0) }}</td>
                                <td class="px-4 py-3 text-right">{{ isset($line['paid_before']) ? $fmt($line['paid_before']) : '—' }}</td>
                                <td class="px-4 py-3 text-right text-emerald-700">{{ $fmt($line['credit_applied'] ?? 0) }}</td>
                                <td class="px-4 py-3 text-right text-sky-700">{{ $fmt($line['advance_applied'] ?? 0) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $fmt($line['cash_applied'] ?? 0) }}</td>
                                <td class="px-4 py-3 text-right">{{ $fmt($line['remaining_after'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Aucune affectation sur facture (avance fournisseur)</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($payment->isCancelled() && $payment->cancellation_reason)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm">
                Annulé le {{ $payment->cancelled_at?->format('d/m/Y H:i') }}
                par {{ $payment->cancelledByUser?->name ?: '—' }}
                — Motif : {{ $payment->cancellation_reason }}
            </div>
        @endif

        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="px-6 py-4 border-b"><h3 class="font-semibold">Traçabilité</h3></div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Date/heure</th>
                        <th class="px-4 py-3 text-left">Utilisateur</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">Ancienne valeur</th>
                        <th class="px-4 py-3 text-left">Nouvelle valeur</th>
                        <th class="px-4 py-3 text-left">Motif</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($audits as $audit)
                        <tr>
                            <td class="px-4 py-3">{{ $audit->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $audit->user?->name ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $audit->action }}{{ $audit->field ? ' · '.$audit->field : '' }}</td>
                            <td class="px-4 py-3">{{ $audit->old_value ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $audit->new_value ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $audit->reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Aucune modification ultérieure</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @unless($payment->isCancelled())
            <form method="POST" action="{{ route('purchases.payments.cancel', $payment) }}" class="bg-red-50 border border-red-200 rounded-xl p-6 space-y-3" onsubmit="return confirm('Voulez-vous vraiment annuler ce règlement ? Son affectation aux factures sera annulée et les soldes seront automatiquement recalculés. Le règlement restera conservé dans l’historique pour la traçabilité.');">
                @csrf
                <h3 class="font-semibold text-red-800">Annuler / supprimer le règlement</h3>
                <label class="block text-sm font-medium">Motif de l’annulation *</label>
                <input type="text" name="cancellation_reason" required class="w-full rounded-lg border-gray-300">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm">Annuler le règlement</button>
            </form>
        @endunless
    </div>
</main>
@endsection
