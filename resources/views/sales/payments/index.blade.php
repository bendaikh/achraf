@extends('layouts.with-sidebar')

@section('title', 'Gestion Paiement - Ventes')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Gestion Paiement</h2>
                <p class="text-sm text-gray-600 mt-1">Rapprochement paiements · tracking Shopify · trésorerie automatique</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="document.getElementById('manualPaymentModal').classList.remove('hidden')"
                    class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition text-sm font-medium">
                    + Paiement manuel
                </button>
                <a href="{{ route('sales.payments.import') }}"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm font-medium">
                    Importer un fichier de règlement
                </a>
                <a href="{{ route('invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm">
                    Voir les factures
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
        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <p class="text-sm text-red-700">{{ session('error') }}</p>
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <ul class="text-sm text-red-700 list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
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
                <h3 class="text-sm font-medium text-gray-500 mb-2">Total encaissé</h3>
                <p class="text-3xl font-bold text-green-600">{{ number_format($stats['total_paid'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Solde restant</h3>
                <p class="text-3xl font-bold text-red-600">{{ number_format($stats['total_remaining'], 2) }} DH</p>
            </div>
        </div>

        <x-table-filters
            :action="route('sales.payments.index')"
            search-placeholder="N° facture, commande, tracking, client..."
            grid-cols="md:grid-cols-3 lg:grid-cols-6"
        >
            <div>
                <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-1">Statut paiement</label>
                <select name="payment_status" id="payment_status" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Tous</option>
                    <option value="unpaid" @selected(request('payment_status') === 'unpaid')>Non payé</option>
                    <option value="partial" @selected(request('payment_status') === 'partial')>Partiellement payé</option>
                    <option value="paid" @selected(request('payment_status') === 'paid')>Payé</option>
                </select>
            </div>
            <div>
                <label for="fulfillment_status" class="block text-sm font-medium text-gray-700 mb-1">Statut livraison</label>
                <select name="fulfillment_status" id="fulfillment_status" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Tous</option>
                    <option value="unfulfilled" @selected(request('fulfillment_status') === 'unfulfilled')>Non livré</option>
                    <option value="partial" @selected(request('fulfillment_status') === 'partial')>Partiel</option>
                    <option value="fulfilled" @selected(request('fulfillment_status') === 'fulfilled')>Livré</option>
                </select>
            </div>
            <div>
                <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                <select name="source" id="source" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                    <option value="">Toutes</option>
                    <option value="shopify" @selected(request('source') === 'shopify')>Shopify</option>
                    <option value="jumia" @selected(request('source') === 'jumia')>Jumia</option>
                    <option value="manual" @selected(request('source') === 'manual')>Manuel / POS</option>
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
                    <option value="Autre" @selected(request('payment_method') === 'Autre')>Autre</option>
                </select>
            </div>
        </x-table-filters>

        <div id="bulkActionsBar-sales-payments" class="hidden bg-[#0a5d8a]/10 border border-[#0a5d8a]/30 rounded-lg p-4 mb-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <span class="text-sm font-medium text-gray-700">
                    <span id="selectedCount-sales-payments">0</span> commande(s) sélectionnée(s)
                </span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openBulkPayment()"
                        class="inline-flex items-center px-4 py-2 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition text-sm font-medium">
                        Enregistrer un paiement
                    </button>
                    <button type="button" onclick="clearTableSelection('sales-payments')"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm font-medium">
                        Annuler
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <x-table-checkbox-header export-type="sales-payments" />
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° facture</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° commande</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <x-table-sort-header column="invoice_date" label="Date" :default="true" default-direction="desc" />
                            <x-table-sort-header column="due_date" label="Échéance" default-direction="desc" />
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Encaissé</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solde</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($invoices as $invoice)
                            @php
                                $totalPaid = (float) ($invoice->payments_sum ?? 0);
                                $invoiceTotal = $invoice->computed_total;
                                $remaining = max(0, $invoiceTotal - $totalPaid);
                                $status = $totalPaid <= 0 ? 'unpaid' : ($totalPaid >= $invoiceTotal ? 'paid' : 'partial');
                                $tracking = $invoice->posSale?->primaryTrackingNumber();
                                $allTracking = $invoice->posSale?->trackingNumbers() ?? [];
                            @endphp
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <x-table-checkbox-cell export-type="sales-payments" :id="$invoice->id" />
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <x-table-show-link :href="route('invoices.show', $invoice)" :label="$invoice->invoice_number" />
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @if($invoice->posSale)
                                        <a href="{{ route('orders.show', $invoice->posSale) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                            {{ $invoice->posSale->ticket_number }}
                                        </a>
                                    @else
                                        <span class="text-sm text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @if($tracking)
                                        <div class="text-sm font-mono text-gray-900">{{ $tracking }}</div>
                                        @if(count($allTracking) > 1)
                                            <div class="text-xs text-gray-500">+{{ count($allTracking) - 1 }} autre(s)</div>
                                        @endif
                                    @else
                                        <span class="text-sm text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $invoice->client->name ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $invoice->invoice_date->format('d/m/Y') }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">{{ number_format($invoiceTotal, 2) }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-green-600">{{ number_format($totalPaid, 2) }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-red-600">{{ number_format($remaining, 2) }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    @if($status === 'paid')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Payé</span>
                                    @elseif($status === 'partial')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Partiellement payé</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Non payé</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('invoices.payments.index', $invoice) }}" class="text-indigo-600 hover:text-indigo-900" title="Historique / paiement">
                                        Paiements
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-6 py-12 text-center text-sm text-gray-500">Aucune facture trouvée</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-table-pagination :paginator="$invoices" :bordered="false" item-label="paiements" />
    </div>

    {{-- Manual payment modal --}}
    <div id="manualPaymentModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/40" onclick="document.getElementById('manualPaymentModal').classList.add('hidden')"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">+ Paiement manuel</h3>
                <form method="POST" action="{{ route('sales.payments.manual') }}" id="manualPaymentForm">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Commande / Facture *</label>
                            <select name="invoice_id" id="manual_invoice_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]"
                                onchange="updateManualPaymentTotals()">
                                <option value="">Sélectionner…</option>
                                @foreach($unpaidInvoices as $inv)
                                    @php
                                        $paid = (float) ($inv->payments_sum ?? 0);
                                        $total = $inv->computed_total;
                                        $bal = max(0, $total - $paid);
                                    @endphp
                                    <option value="{{ $inv->id }}"
                                        data-total="{{ $total }}"
                                        data-paid="{{ $paid }}"
                                        data-balance="{{ $bal }}">
                                        {{ $inv->invoice_number }}
                                        @if($inv->posSale) · {{ $inv->posSale->ticket_number }} @endif
                                        @if($inv->posSale?->primaryTrackingNumber()) · {{ $inv->posSale->primaryTrackingNumber() }} @endif
                                        — {{ $inv->client->name ?? 'Client' }} (solde {{ number_format($bal, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-3 text-sm bg-gray-50 rounded-lg p-3">
                            <div><span class="text-gray-500">Total</span><div id="manual_total" class="font-semibold">—</div></div>
                            <div><span class="text-gray-500">Encaissé</span><div id="manual_paid" class="font-semibold text-green-600">—</div></div>
                            <div><span class="text-gray-500">Solde</span><div id="manual_balance" class="font-semibold text-red-600">—</div></div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Montant *</label>
                                <input type="number" step="0.01" name="amount" id="manual_amount" required class="w-full rounded-lg border-gray-300" oninput="updateManualPaymentTotals()">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border-gray-300">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement *</label>
                            <select name="payment_method" required class="w-full rounded-lg border-gray-300">
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Virement bancaire" selected>Virement bancaire</option>
                                <option value="Carte bancaire">Carte bancaire</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Référence</label>
                            <input type="text" name="payment_reference" class="w-full rounded-lg border-gray-300" placeholder="N° chèque, bordereau…">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                            <input type="text" name="notes" class="w-full rounded-lg border-gray-300">
                        </div>
                        <div id="manual_overpay_warn" class="hidden bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
                            <label class="inline-flex items-start gap-2">
                                <input type="checkbox" name="allow_overpayment" value="1" class="mt-1 rounded border-gray-300">
                                <span>Le montant dépasse le solde. J’autorise l’enregistrement du trop-perçu.</span>
                            </label>
                        </div>
                        <div class="text-sm text-gray-600">
                            Nouveau solde après paiement : <strong id="manual_new_balance">—</strong>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" onclick="document.getElementById('manualPaymentModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Annuler</button>
                        <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
function updateManualPaymentTotals() {
    var sel = document.getElementById('manual_invoice_id');
    var opt = sel.options[sel.selectedIndex];
    var total = parseFloat(opt?.dataset?.total || '0');
    var paid = parseFloat(opt?.dataset?.paid || '0');
    var balance = parseFloat(opt?.dataset?.balance || '0');
    var amount = parseFloat(document.getElementById('manual_amount').value || '0');

    document.getElementById('manual_total').textContent = sel.value ? total.toFixed(2) + ' DH' : '—';
    document.getElementById('manual_paid').textContent = sel.value ? paid.toFixed(2) + ' DH' : '—';
    document.getElementById('manual_balance').textContent = sel.value ? balance.toFixed(2) + ' DH' : '—';
    document.getElementById('manual_new_balance').textContent = sel.value ? Math.max(0, balance - amount).toFixed(2) + ' DH' : '—';

    var warn = document.getElementById('manual_overpay_warn');
    if (sel.value && amount > balance + 0.009) {
        warn.classList.remove('hidden');
    } else {
        warn.classList.add('hidden');
    }

    if (sel.value && !document.getElementById('manual_amount').value) {
        document.getElementById('manual_amount').value = balance.toFixed(2);
        document.getElementById('manual_new_balance').textContent = '0.00 DH';
    }
}

function openBulkPayment() {
    var ids = getSelectedTableIds('sales-payments');
    if (!ids.length) {
        alert('Sélectionnez au moins une commande.');
        return;
    }
    window.location.href = @json(route('sales.payments.bulk')) + '?ids=' + ids.join(',');
}
</script>
@endsection
