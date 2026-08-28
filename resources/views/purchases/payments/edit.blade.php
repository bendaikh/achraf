@extends('layouts.with-sidebar')

@section('title', 'Modifier le règlement')

@section('main')
@php
    $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
@endphp
<main class="flex-1 w-full min-w-0" x-data="supplierSettle()">
    <header class="bg-white shadow-sm border-b sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Modifier {{ $payment->payment_number }}</h2>
                <p class="text-sm text-gray-600">Une modification de montant ou d’affectation recalcule automatiquement les soldes.</p>
            </div>
            <a href="{{ route('purchases.payments.show', $payment) }}" class="px-4 py-2 bg-gray-100 rounded-lg text-sm">Retour à la fiche</a>
        </div>
    </header>
    <div class="p-8 space-y-6">
        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <ul class="text-sm text-red-700 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('purchases.payments.update', $payment) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="bg-white rounded-xl border overflow-hidden">
                <div class="px-6 py-4 border-b"><h3 class="font-semibold">Factures concernées</h3></div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Sel.</th>
                            <th class="px-4 py-3 text-left">Facture</th>
                            <th class="px-4 py-3 text-right">Reste (hors ce règlement)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="inv in invoices" :key="inv.id">
                            <tr>
                                <td class="px-4 py-3"><input type="checkbox" :name="'invoice_ids[]'" :value="inv.id" x-model="inv.selected" class="rounded"></td>
                                <td class="px-4 py-3" x-text="inv.number"></td>
                                <td class="px-4 py-3 text-right" x-text="money(inv.remaining)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl border p-6 grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Date *</label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', $payment->payment_date?->format('Y-m-d')) }}" required class="w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Mode *</label>
                    <select name="payment_method" x-model="method" required class="w-full rounded-lg border-gray-300">
                        <option value="Virement bancaire">Virement bancaire</option>
                        <option value="Chèque">Chèque</option>
                        <option value="Espèces">Espèces</option>
                        <option value="Carte bancaire">Carte bancaire</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Montant décaissé</label>
                    <input type="number" step="0.01" min="0" name="amount" x-model.number="amount" class="w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Référence</label>
                    <input type="text" name="payment_reference" value="{{ old('payment_reference', $payment->payment_reference) }}" class="w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes', $payment->notes) }}" class="w-full rounded-lg border-gray-300">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Justificatif (remplacer)</label>
                    <input type="file" name="payment_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full rounded-lg border-gray-300">
                </div>
                <label class="flex items-start gap-2 bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-sm sm:col-span-2">
                    <input type="checkbox" name="use_credits" value="1" x-model="useCredits" class="mt-0.5 rounded">
                    <span>Utiliser les avoirs disponibles ({{ $fmt($statement['available_credits']) }} DH)</span>
                </label>
                <label class="flex items-start gap-2 bg-sky-50 border border-sky-200 rounded-lg p-3 text-sm sm:col-span-2">
                    <input type="hidden" name="use_advances" value="0">
                    <input type="checkbox" name="use_advances" value="1" x-model="useAdvances" class="mt-0.5 rounded">
                    <span>Utiliser les avances disponibles ({{ $fmt($statement['available_advances']) }} DH)</span>
                </label>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Motif de la modification</label>
                    <input type="text" name="reason" value="{{ old('reason') }}" class="w-full rounded-lg border-gray-300" placeholder="Ex. : correction du montant">
                </div>
                <div class="grid sm:grid-cols-2 gap-4 sm:col-span-2 rounded-xl border border-amber-200 bg-amber-50 p-4" x-show="method === 'Chèque'" x-cloak>
                    <div>
                        <label class="block text-sm font-medium mb-1">N° chèque</label>
                        <input type="text" name="cheque_number" value="{{ old('cheque_number', $payment->cheque_number) }}" class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Banque</label>
                        <input type="text" name="cheque_bank" value="{{ old('cheque_bank', $payment->cheque_bank) }}" class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Date du chèque</label>
                        <input type="date" name="cheque_date" value="{{ old('cheque_date', $payment->cheque_date?->format('Y-m-d')) }}" class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Statut</label>
                        <select name="cheque_status" class="w-full rounded-lg border-gray-300">
                            @foreach($chequeStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('cheque_status', $payment->cheque_status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-[#0a5d8a] text-white rounded-lg font-medium">Enregistrer les corrections</button>
            </div>
        </form>
    </div>
</main>
<script>
function supplierSettle() {
    const invoices = @json($openInvoices).map(inv => ({ ...inv, selected: inv.selected !== false }));
    const creditPool = {{ (float) $statement['available_credits'] }};
    const advancePool = {{ (float) $statement['available_advances'] }};
    return {
        invoices,
        method: '{{ old('payment_method', $payment->payment_method) }}',
        useCredits: true,
        useAdvances: true,
        amount: {{ (float) old('amount', $payment->amount) }},
        money(n) { return (Number(n) || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        selected() { return this.invoices.filter(i => i.selected); },
        selectedTotal() { return this.selected().reduce((s, i) => s + i.remaining, 0); },
        creditsUsed() { return this.useCredits ? Math.min(creditPool, this.selectedTotal()) : 0; },
        advancesUsed() {
            const after = Math.max(0, this.selectedTotal() - this.creditsUsed());
            return this.useAdvances ? Math.min(advancePool, after) : 0;
        },
    };
}
</script>
@endsection
