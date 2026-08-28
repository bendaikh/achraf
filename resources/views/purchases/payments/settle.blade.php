@extends('layouts.with-sidebar')

@section('title', 'Règlement fournisseur')

@section('main')
@php
    $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
@endphp
<main class="flex-1 w-full min-w-0" x-data="supplierSettle()">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Compte fournisseur — {{ $supplier->name }}</h2>
                <p class="text-sm text-gray-600 mt-1">Factures – avoirs – paiements – avances = solde réel à payer</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('suppliers.show', $supplier) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Fiche fournisseur</a>
                <a href="{{ route('purchases.payments.index') }}" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg text-sm">Gestion Paiement</a>
            </div>
        </div>
    </header>

    <div class="p-8 space-y-6">
        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg"><p class="text-sm text-green-700">{{ session('success') }}</p></div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <ul class="text-sm text-red-700 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 uppercase">Total factures ouvertes</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ $fmt($statement['open_invoices']) }} DH</p>
            </div>
            <div class="bg-white rounded-xl border border-emerald-200 p-4">
                <p class="text-xs text-emerald-700 uppercase">Avoirs disponibles</p>
                <p class="text-xl font-bold text-emerald-700 mt-1">- {{ $fmt($statement['available_credits']) }} DH</p>
            </div>
            <div class="bg-white rounded-xl border border-sky-200 p-4">
                <p class="text-xs text-sky-700 uppercase">Avances disponibles</p>
                <p class="text-xl font-bold text-sky-700 mt-1">- {{ $fmt($statement['available_advances']) }} DH</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500 uppercase">Paiements déjà enregistrés</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ $fmt($statement['total_payments']) }} DH</p>
            </div>
            <div class="bg-[#0a5d8a] rounded-xl p-4 text-white">
                <p class="text-xs uppercase opacity-80">Solde net à payer</p>
                <p class="text-xl font-bold mt-1">{{ $fmt($statement['balance']) }} DH</p>
            </div>
        </div>

        <form method="POST" action="{{ route('purchases.payments.settle.store', $supplier) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Factures ouvertes</h3>
                    <button type="button" class="text-sm text-[#0a5d8a]" @click="toggleAll()">Tout sélectionner</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Sel.</th>
                                <th class="px-4 py-3 text-left">Facture</th>
                                <th class="px-4 py-3 text-left">Échéance</th>
                                <th class="px-4 py-3 text-right">Montant</th>
                                <th class="px-4 py-3 text-right">Déjà payé</th>
                                <th class="px-4 py-3 text-right">Avoirs imputés</th>
                                <th class="px-4 py-3 text-right">Reste</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template x-for="inv in invoices" :key="inv.id">
                                <tr :class="inv.selected ? 'bg-amber-50' : ''">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" :name="'invoice_ids[]'" :value="inv.id" x-model="inv.selected" class="rounded border-gray-300">
                                    </td>
                                    <td class="px-4 py-3 font-medium" x-text="inv.number"></td>
                                    <td class="px-4 py-3" x-text="inv.due_date || '—'"></td>
                                    <td class="px-4 py-3 text-right" x-text="money(inv.total)"></td>
                                    <td class="px-4 py-3 text-right text-green-700" x-text="money(inv.paid)"></td>
                                    <td class="px-4 py-3 text-right text-emerald-700" x-text="money(inv.credits_applied)"></td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-600" x-text="money(inv.remaining)"></td>
                                </tr>
                            </template>
                            <tr x-show="invoices.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucune facture ouverte</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-900">Saisie du règlement</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Date *</label>
                            <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required class="w-full rounded-lg border-gray-300">
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
                            <label class="block text-sm font-medium mb-1">Montant à décaisser</label>
                            <input type="number" step="0.01" min="0" name="amount" x-model.number="amount" class="w-full rounded-lg border-gray-300" placeholder="0.00">
                            <p class="text-xs text-gray-500 mt-1">Un montant supérieur au reste des factures sélectionnées est conservé en avance fournisseur.</p>
                        </div>
                    </div>

                    <label class="flex items-start gap-2 bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-sm">
                        <input type="checkbox" name="use_credits" value="1" x-model="useCredits" class="mt-0.5 rounded">
                        <span>
                            <strong>Utiliser les avoirs disponibles</strong>
                            <span class="block text-emerald-800">Avoirs : {{ $fmt($statement['available_credits']) }} DH — le net à payer est recalculé immédiatement.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 bg-sky-50 border border-sky-200 rounded-lg p-3 text-sm">
                        <input type="hidden" name="use_advances" value="0">
                        <input type="checkbox" name="use_advances" value="1" x-model="useAdvances" class="mt-0.5 rounded">
                        <span>
                            <strong>Utiliser les avances disponibles</strong>
                            <span class="block text-sky-800">Avances : {{ $fmt($statement['available_advances']) }} DH</span>
                        </span>
                    </label>

                    <div>
                        <label class="block text-sm font-medium mb-1">Référence</label>
                        <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="w-full rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" x-text="method === 'Chèque' ? 'Scan du chèque' : 'Justificatif'"></label>
                        <input type="file" name="payment_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full rounded-lg border-gray-300">
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4" x-show="method === 'Chèque'" x-cloak>
                        <div>
                            <label class="block text-sm font-medium mb-1">N° chèque *</label>
                            <input type="text" name="cheque_number" value="{{ old('cheque_number') }}" class="w-full rounded-lg border-gray-300" :required="method === 'Chèque'">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Banque *</label>
                            <input type="text" name="cheque_bank" value="{{ old('cheque_bank') }}" class="w-full rounded-lg border-gray-300" :required="method === 'Chèque'">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Date du chèque *</label>
                            <input type="date" name="cheque_date" value="{{ old('cheque_date') }}" class="w-full rounded-lg border-gray-300" :required="method === 'Chèque'">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Date d’échéance</label>
                            <input type="date" name="cheque_due_date" value="{{ old('cheque_due_date') }}" class="w-full rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Bénéficiaire</label>
                            <input type="text" name="cheque_beneficiary" value="{{ old('cheque_beneficiary', $supplier->name) }}" class="w-full rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Statut *</label>
                            <select name="cheque_status" class="w-full rounded-lg border-gray-300" :required="method === 'Chèque'">
                                <option value="">Sélectionner</option>
                                @foreach($chequeStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('cheque_status') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <h3 class="font-semibold text-gray-900 mb-3">Avoirs disponibles</h3>
                        @forelse($credits as $credit)
                            <div class="flex justify-between text-sm py-2 border-b last:border-0">
                                <span>{{ $credit['number'] }} · {{ $credit['date'] }}</span>
                                <span class="font-semibold text-emerald-700">{{ $fmt($credit['remaining']) }} DH</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Aucun avoir disponible</p>
                        @endforelse
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <h3 class="font-semibold text-gray-900 mb-3">Récapitulatif automatique</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt>Factures sélectionnées</dt><dd class="font-medium" x-text="money(selectedTotal()) + ' DH'"></dd></div>
                            <div class="flex justify-between text-emerald-700"><dt>Avoirs utilisés</dt><dd x-text="'- ' + money(creditsUsed()) + ' DH'"></dd></div>
                            <div class="flex justify-between text-sky-700"><dt>Avances utilisées</dt><dd x-text="'- ' + money(advancesUsed()) + ' DH'"></dd></div>
                            <div class="flex justify-between"><dt>Net après avoirs / avances</dt><dd class="font-semibold" x-text="money(netAfterCredits()) + ' DH'"></dd></div>
                            <div class="flex justify-between"><dt>Montant saisi</dt><dd x-text="money(amount || 0) + ' DH'"></dd></div>
                            <div class="flex justify-between text-[#0a5d8a]"><dt>Affecté aux factures</dt><dd x-text="money(cashApplied()) + ' DH'"></dd></div>
                            <div class="flex justify-between text-amber-700"><dt>Reste en avance</dt><dd x-text="money(advanceCreated()) + ' DH'"></dd></div>
                            <div class="border-t pt-2 flex justify-between text-base font-bold">
                                <dt>NET À PAYER</dt>
                                <dd x-text="money(netToPay()) + ' DH'"></dd>
                            </div>
                        </dl>
                        <button type="button" class="mt-4 text-sm text-[#0a5d8a]" @click="amount = netToPay()">Préremplir le net à payer</button>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-[#0a5d8a] text-white rounded-lg font-medium">Valider le règlement</button>
            </div>
        </form>

        @include('purchases.payments.partials.history-table', ['rows' => $paymentHistory, 'supplier' => $supplier])
    </div>
</main>
<script>
function supplierSettle() {
    const invoices = @json($openInvoices).map(inv => ({
        ...inv,
        selected: @json($preselected).includes(inv.id) || @json($preselected).length === 0
    }));
    const creditPool = {{ (float) $statement['available_credits'] }};
    const advancePool = {{ (float) $statement['available_advances'] }};
    const selectedInitially = invoices.filter(i => i.selected);
    const startNet = Math.max(0, selectedInitially.reduce((s, i) => s + i.remaining, 0) - creditPool - advancePool);
    return {
        invoices,
        method: '{{ old('payment_method', 'Virement bancaire') }}',
        useCredits: true,
        useAdvances: true,
        amount: startNet,
        money(n) { return (Number(n) || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        selected() { return this.invoices.filter(i => i.selected); },
        selectedTotal() { return this.selected().reduce((s, i) => s + i.remaining, 0); },
        creditsUsed() { return this.useCredits ? Math.min(creditPool, this.selectedTotal()) : 0; },
        advancesUsed() {
            const after = Math.max(0, this.selectedTotal() - this.creditsUsed());
            return this.useAdvances ? Math.min(advancePool, after) : 0;
        },
        netAfterCredits() { return Math.max(0, this.selectedTotal() - this.creditsUsed() - this.advancesUsed()); },
        netToPay() { return this.netAfterCredits(); },
        cashApplied() { return Math.min(Number(this.amount) || 0, this.netAfterCredits()); },
        advanceCreated() { return Math.max(0, (Number(this.amount) || 0) - this.netAfterCredits()); },
        toggleAll() {
            const all = this.invoices.every(i => i.selected);
            this.invoices.forEach(i => i.selected = !all);
        }
    };
}
</script>
@endsection
