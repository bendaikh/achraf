@extends('layouts.with-sidebar')

@section('title', 'Paiement groupé - Ventes')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Enregistrer un paiement groupé</h2>
                <p class="text-sm text-gray-600 mt-1">{{ $invoices->count() }} commande(s) sélectionnée(s) — saisissez les frais par facture pour conserver le net encaissé</p>
            </div>
            <a href="{{ route('sales.payments.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">Retour</a>
        </div>
    </header>

    <div class="p-8">
        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <ul class="text-sm text-red-700 list-disc pl-4">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('sales.payments.bulk.store') }}">
            @csrf
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commande</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Solde</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant facture</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frais livraison</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Net encaissé</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trop-perçu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($invoices as $i => $invoice)
                                @php
                                    $paid = (float) ($invoice->payments_sum ?? 0);
                                    $total = $invoice->computed_total;
                                    $balance = max(0, $total - $paid);
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="font-medium">{{ $invoice->posSale->ticket_number ?? $invoice->invoice_number }}</div>
                                        <div class="text-xs text-gray-500">{{ $invoice->invoice_number }}</div>
                                        <input type="hidden" name="payments[{{ $i }}][invoice_id]" value="{{ $invoice->id }}">
                                    </td>
                                    <td class="px-4 py-3 text-sm font-mono">{{ $invoice->posSale?->primaryTrackingNumber() ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $invoice->client->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-red-600">{{ number_format($balance, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.01" name="payments[{{ $i }}][amount]" id="bulk_amount_{{ $i }}"
                                            value="{{ number_format($balance, 2, '.', '') }}"
                                            required class="w-28 rounded-lg border-gray-300 text-sm"
                                            data-balance="{{ $balance }}"
                                            oninput="bulkRecalc({{ $i }})">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.01" min="0" name="payments[{{ $i }}][delivery_fees]" id="bulk_fees_{{ $i }}"
                                            class="w-24 rounded-lg border-gray-300 text-sm" placeholder="0.00"
                                            oninput="bulkRecalc({{ $i }})">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.01" min="0" name="payments[{{ $i }}][net_received]" id="bulk_net_{{ $i }}"
                                            readonly class="w-28 rounded-lg border-green-300 bg-green-50 text-sm text-green-800 font-semibold" placeholder="—">
                                    </td>
                                    <td class="px-4 py-3">
                                        <label id="overpay_{{ $i }}" class="hidden text-xs text-amber-700 inline-flex items-center gap-1">
                                            <input type="checkbox" name="payments[{{ $i }}][allow_overpayment]" value="1" class="rounded border-gray-300">
                                            Autoriser
                                        </label>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-sm font-medium text-right text-gray-600">Totaux</td>
                                <td class="px-4 py-3 text-sm font-semibold" id="bulk_total_gross">0.00</td>
                                <td class="px-4 py-3 text-sm font-semibold" id="bulk_total_fees">0.00</td>
                                <td class="px-4 py-3 text-sm font-semibold text-green-700" id="bulk_total_net">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 max-w-2xl">
                <h3 class="text-lg font-semibold mb-4">Informations du règlement</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement *</label>
                        <select name="payment_method" required class="w-full rounded-lg border-gray-300">
                            <option value="Virement bancaire">Virement bancaire</option>
                            <option value="Espèces">Espèces</option>
                            <option value="Chèque">Chèque</option>
                            <option value="Carte bancaire">Carte bancaire</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Référence du règlement</label>
                        <input type="text" name="payment_reference" class="w-full rounded-lg border-gray-300" placeholder="Bordereau transporteur…">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                        <input type="text" name="notes" class="w-full rounded-lg border-gray-300">
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <a href="{{ route('sales.payments.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Annuler</a>
                    <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Valider les paiements</button>
                </div>
            </div>
        </form>
    </div>
</main>
<script>
function bulkRecalc(index) {
    var amountEl = document.getElementById('bulk_amount_' + index);
    var feesEl = document.getElementById('bulk_fees_' + index);
    var netEl = document.getElementById('bulk_net_' + index);
    var balance = parseFloat(amountEl.dataset.balance || '0');
    var amount = parseFloat(amountEl.value || '0');
    var feesRaw = feesEl.value;
    var overpay = document.getElementById('overpay_' + index);

    if (amount > balance + 0.009) overpay.classList.remove('hidden');
    else overpay.classList.add('hidden');

    if (feesRaw === '' || feesRaw === null) {
        netEl.value = '';
    } else {
        netEl.value = Math.max(0, amount - parseFloat(feesRaw || '0')).toFixed(2);
    }
    updateBulkTotals();
}

function updateBulkTotals() {
    var gross = 0, fees = 0, net = 0, count = {{ $invoices->count() }};
    for (var i = 0; i < count; i++) {
        gross += parseFloat(document.getElementById('bulk_amount_' + i).value || '0');
        var f = document.getElementById('bulk_fees_' + i).value;
        var n = document.getElementById('bulk_net_' + i).value;
        if (f !== '') fees += parseFloat(f || '0');
        if (n !== '') net += parseFloat(n || '0');
        else net += parseFloat(document.getElementById('bulk_amount_' + i).value || '0');
    }
    document.getElementById('bulk_total_gross').textContent = gross.toFixed(2);
    document.getElementById('bulk_total_fees').textContent = fees.toFixed(2);
    document.getElementById('bulk_total_net').textContent = net.toFixed(2);
}

@for($i = 0; $i < $invoices->count(); $i++)
bulkRecalc({{ $i }});
@endfor
</script>
@endsection
