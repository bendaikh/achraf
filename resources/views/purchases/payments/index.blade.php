@extends('layouts.with-sidebar')

@section('title', 'Gestion Paiement - Achats')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Gestion Paiement</h2>
                <p class="text-sm text-gray-600 mt-1">Paiements fournisseurs · import · trésorerie automatique</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="document.getElementById('manualPaymentModal').classList.remove('hidden')"
                    class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition text-sm font-medium">
                    + Paiement manuel
                </button>
                <a href="{{ route('purchases.payments.import') }}"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm font-medium">
                    Importer un fichier de règlement
                </a>
                <a href="{{ route('supplier-invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Voir les factures</a>
            </div>
        </div>
    </header>

    <div class="p-8">
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg"><p class="text-sm text-green-700">{{ session('success') }}</p></div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg"><p class="text-sm text-red-700">{{ session('error') }}</p></div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Factures</h3>
                <p class="text-3xl font-bold text-gray-900">{{ $stats['invoice_count'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Montant total</h3>
                <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_amount'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Total payé</h3>
                <p class="text-3xl font-bold text-green-600">{{ number_format($stats['total_paid'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Solde restant</h3>
                <p class="text-3xl font-bold text-red-600">{{ number_format($stats['total_remaining'], 2) }} DH</p>
            </div>
        </div>

        <x-table-filters :action="route('purchases.payments.index')" search-placeholder="N° facture, fournisseur..." grid-cols="md:grid-cols-5">
            <div>
                <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-1">Statut paiement</label>
                <select name="payment_status" id="payment_status" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Tous</option>
                    <option value="open" @selected(request('payment_status') === 'open')>Non soldé (impayé + partiel)</option>
                    <option value="unpaid" @selected(request('payment_status') === 'unpaid')>Non payé</option>
                    <option value="partial" @selected(request('payment_status') === 'partial')>Partiellement payé</option>
                    <option value="paid" @selected(request('payment_status') === 'paid')>Payé</option>
                </select>
            </div>
            <div>
                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement</label>
                <select name="payment_method" id="payment_method" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Tous</option>
                    <option value="Espèces" @selected(request('payment_method') === 'Espèces')>Espèces</option>
                    <option value="Chèque" @selected(request('payment_method') === 'Chèque')>Chèque</option>
                    <option value="Virement bancaire" @selected(request('payment_method') === 'Virement bancaire')>Virement bancaire</option>
                    <option value="Carte bancaire" @selected(request('payment_method') === 'Carte bancaire')>Carte bancaire</option>
                </select>
            </div>
        </x-table-filters>

        <div id="bulkActionsBar-purchase-payments" class="hidden bg-[#0a5d8a]/10 border border-[#0a5d8a]/30 rounded-lg p-4 mb-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <span class="text-sm font-medium text-gray-700"><span id="selectedCount-purchase-payments">0</span> facture(s) sélectionnée(s)</span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openBulkPayment()" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Enregistrer un paiement</button>
                    <button type="button" onclick="clearTableSelection('purchase-payments')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Annuler</button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <x-table-checkbox-header export-type="purchase-payments" />
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase">N° Facture</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase">Fournisseur</th>
                            <x-table-sort-header column="invoice_date" label="Date" :default="true" default-direction="desc" />
                            <x-table-sort-header column="due_date" label="Échéance" default-direction="desc" />
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase">Payé</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase">Solde</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($invoices as $supplierInvoice)
                            @php
                                $totalPaid = (float) ($supplierInvoice->payments_sum ?? 0);
                                $invoiceTotal = (float) $supplierInvoice->total;
                                $remaining = max(0, $invoiceTotal - $totalPaid);
                                $status = $totalPaid <= 0 ? 'unpaid' : ($totalPaid >= $invoiceTotal ? 'paid' : 'partial');
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <x-table-checkbox-cell export-type="purchase-payments" :id="$supplierInvoice->id" />
                                <td class="px-4 py-4"><x-table-show-link :href="route('supplier-invoices.show', $supplierInvoice)" :label="$supplierInvoice->invoice_number" /></td>
                                <td class="px-4 py-4 text-sm">{{ $supplierInvoice->supplier->name ?? '-' }}</td>
                                <td class="px-4 py-4 text-sm">{{ $supplierInvoice->invoice_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-4 text-sm">{{ $supplierInvoice->due_date ? $supplierInvoice->due_date->format('d/m/Y') : '-' }}</td>
                                <td class="px-4 py-4 text-sm font-semibold">{{ number_format($invoiceTotal, 2) }}</td>
                                <td class="px-4 py-4 text-sm font-semibold text-green-600">{{ number_format($totalPaid, 2) }}</td>
                                <td class="px-4 py-4 text-sm font-semibold text-red-600">{{ number_format($remaining, 2) }}</td>
                                <td class="px-4 py-4">
                                    @if($status === 'paid')
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Payé</span>
                                    @elseif($status === 'partial')
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Partiellement payé</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Non payé</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('supplier-invoices.payments.index', $supplierInvoice) }}" class="text-indigo-600 text-sm">Paiements</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-6 py-12 text-center text-sm text-gray-500">Aucune facture</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <x-table-pagination :paginator="$invoices" :bordered="false" item-label="paiements" />
    </div>

    <div id="manualPaymentModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/40" onclick="document.getElementById('manualPaymentModal').classList.add('hidden')"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold mb-4">+ Paiement manuel</h3>
                <form method="POST" action="{{ route('purchases.payments.manual') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Facture *</label>
                            <select name="supplier_invoice_id" required class="w-full rounded-lg border-gray-300" id="manual_invoice_id" onchange="updateManual()">
                                <option value="">Sélectionner…</option>
                                @foreach($openInvoices as $inv)
                                    @php $bal = max(0, (float)$inv->total - (float)($inv->payments_sum ?? 0)); @endphp
                                    <option value="{{ $inv->id }}" data-total="{{ $inv->total }}" data-paid="{{ $inv->payments_sum ?? 0 }}" data-balance="{{ $bal }}">
                                        {{ $inv->invoice_number }} — {{ $inv->supplier->name ?? '' }} (solde {{ number_format($bal, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-3 text-sm bg-gray-50 rounded-lg p-3">
                            <div><span class="text-gray-500">Total</span><div id="m_total" class="font-semibold">—</div></div>
                            <div><span class="text-gray-500">Payé</span><div id="m_paid" class="font-semibold text-green-600">—</div></div>
                            <div><span class="text-gray-500">Solde</span><div id="m_bal" class="font-semibold text-red-600">—</div></div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1">Montant *</label>
                                <input type="number" step="0.01" name="amount" id="m_amount" required class="w-full rounded-lg border-gray-300" oninput="updateManual()">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Date *</label>
                                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border-gray-300">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Mode *</label>
                            <select name="payment_method" required class="w-full rounded-lg border-gray-300">
                                <option value="Virement bancaire">Virement bancaire</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Carte bancaire">Carte bancaire</option>
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
                        <div id="m_over" class="hidden bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm">
                            <label class="inline-flex gap-2"><input type="checkbox" name="allow_overpayment" value="1" class="rounded"> Autoriser le trop-perçu</label>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" onclick="document.getElementById('manualPaymentModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm">Annuler</button>
                        <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<script>
function updateManual(){
    var sel=document.getElementById('manual_invoice_id');
    var opt=sel.options[sel.selectedIndex];
    var total=parseFloat(opt?.dataset?.total||0), paid=parseFloat(opt?.dataset?.paid||0), bal=parseFloat(opt?.dataset?.balance||0);
    var amount=parseFloat(document.getElementById('m_amount').value||0);
    document.getElementById('m_total').textContent=sel.value?total.toFixed(2)+' DH':'—';
    document.getElementById('m_paid').textContent=sel.value?paid.toFixed(2)+' DH':'—';
    document.getElementById('m_bal').textContent=sel.value?bal.toFixed(2)+' DH':'—';
    document.getElementById('m_over').classList.toggle('hidden', !(sel.value && amount>bal+0.009));
    if(sel.value && !document.getElementById('m_amount').value) document.getElementById('m_amount').value=bal.toFixed(2);
}
function openBulkPayment(){
    var ids=getSelectedTableIds('purchase-payments');
    if(!ids.length){alert('Sélectionnez au moins une facture.');return;}
    window.location.href=@json(route('purchases.payments.bulk'))+'?ids='+ids.join(',');
}
</script>
@endsection
