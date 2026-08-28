@extends('layouts.with-sidebar')

@section('title', 'Paiement groupé - Achats')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Paiement groupé fournisseurs</h2>
                <p class="text-sm text-gray-600 mt-1">{{ $invoices->count() }} facture(s)</p>
            </div>
            <a href="{{ route('purchases.payments.index') }}" class="px-4 py-2 bg-gray-100 rounded-lg text-sm">Retour</a>
        </div>
    </header>
    <div class="p-8">
        <form method="POST" action="{{ route('purchases.payments.bulk.store') }}">
            @csrf
            <div class="bg-white rounded-xl border overflow-hidden mb-6">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Facture</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Fournisseur</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Total</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Payé</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Solde</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Montant</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Trop-perçu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($invoices as $i => $invoice)
                            @php $paid=(float)($invoice->payments_sum??0); $credits=(float)($invoice->credits_sum??0); $total=(float)$invoice->total; $bal=max(0,$total-$paid-$credits); @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $invoice->invoice_number }}
                                    <input type="hidden" name="payments[{{ $i }}][supplier_invoice_id]" value="{{ $invoice->id }}">
                                </td>
                                <td class="px-4 py-3">{{ $invoice->supplier->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ number_format($total,2) }}</td>
                                <td class="px-4 py-3 text-green-600">{{ number_format($paid,2) }}</td>
                                <td class="px-4 py-3 text-red-600">{{ number_format($bal,2) }}</td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.01" name="payments[{{ $i }}][amount]" value="{{ number_format($bal,2,'.','') }}" required
                                        class="w-32 rounded border-gray-300" data-balance="{{ $bal }}" oninput="toggleOver(this,{{ $i }})">
                                </td>
                                <td class="px-4 py-3">
                                    <label id="over_{{ $i }}" class="hidden text-xs text-amber-700">
                                        <input type="checkbox" name="payments[{{ $i }}][allow_overpayment]" value="1"> Autoriser
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="bg-white rounded-xl border p-6 max-w-2xl">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Date *</label>
                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Mode *</label>
                        <select name="payment_method" required class="w-full rounded-lg border-gray-300">
                            <option value="Virement bancaire">Virement bancaire</option>
                            <option value="Espèces">Espèces</option>
                            <option value="Chèque">Chèque</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Référence</label>
                        <input type="text" name="payment_reference" class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Commentaire</label>
                        <input type="text" name="notes" class="w-full rounded-lg border-gray-300">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <a href="{{ route('purchases.payments.index') }}" class="px-4 py-2 border rounded-lg text-sm">Annuler</a>
                    <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Valider</button>
                </div>
            </div>
        </form>
    </div>
</main>
<script>
function toggleOver(input,i){var b=parseFloat(input.dataset.balance||0),a=parseFloat(input.value||0);
document.getElementById('over_'+i).classList.toggle('hidden',!(a>b+0.009));}
</script>
@endsection
