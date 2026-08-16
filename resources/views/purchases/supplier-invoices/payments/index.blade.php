@extends('layouts.with-sidebar')

@section('title', 'Règlement de paiement')

@section('main')
<main class="flex-1 w-full min-w-0" x-data="{ method: '{{ old('payment_method', '') }}' }">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Règlement de paiement</h2>
                <p class="text-sm text-gray-600 mt-1">Facture {{ $supplierInvoice->invoice_number }} - {{ $supplierInvoice->supplier->name }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('supplier-invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">Retour à la liste</a>
                <a href="{{ route('purchases.payments.index') }}" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 transition duration-150">Gestion Paiement</a>
            </div>
        </div>
    </header>

    <div class="p-8">
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Montant Total</h3>
                <p class="text-3xl font-bold text-gray-900">{{ number_format($supplierInvoice->total, 2) }} {{ $supplierInvoice->currency }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Total Payé</h3>
                <p class="text-3xl font-bold text-green-600">{{ number_format($supplierInvoice->total_paid, 2) }} {{ $supplierInvoice->currency }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Solde Restant</h3>
                <p class="text-3xl font-bold text-red-600">{{ number_format($supplierInvoice->remaining_balance, 2) }} {{ $supplierInvoice->currency }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Ajouter un paiement</h3>
            <form action="{{ route('supplier-invoices.payments.store', $supplierInvoice) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date de paiement *</label>
                        <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Montant *</label>
                        <input type="number" step="0.01" name="amount" value="{{ old('amount') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Méthode de paiement *</label>
                        <select name="payment_method" x-model="method" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
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
                        <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Référence virement / chèque…">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" x-text="method === 'Chèque' ? 'Scanner le chèque / justificatif' : (method === 'Virement bancaire' ? 'Justificatif de virement' : 'Justificatif de paiement')"></label>
                        <input type="file" name="payment_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Notes optionnelles...">
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4" x-show="method === 'Chèque'" x-cloak>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">N° chèque *</label>
                        <input type="text" name="cheque_number" value="{{ old('cheque_number') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" :required="method === 'Chèque'">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Banque *</label>
                        <input type="text" name="cheque_bank" value="{{ old('cheque_bank') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" :required="method === 'Chèque'">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date du chèque *</label>
                        <input type="date" name="cheque_date" value="{{ old('cheque_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" :required="method === 'Chèque'">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date d’échéance</label>
                        <input type="date" name="cheque_due_date" value="{{ old('cheque_due_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bénéficiaire</label>
                        <input type="text" name="cheque_beneficiary" value="{{ old('cheque_beneficiary', $supplierInvoice->supplier->name) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Statut *</label>
                        <select name="cheque_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg" :required="method === 'Chèque'">
                            <option value="">Sélectionner</option>
                            @foreach($chequeStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('cheque_status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-3 text-xs text-amber-800">
                        Facture concernée : <strong>{{ $supplierInvoice->invoice_number }}</strong>. Le scan sera nommé automatiquement <code>CHQ-[N° chèque].pdf</code>.
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                        Ajouter le paiement
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Historique des paiements</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Méthode</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Chèque / Réf.</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Document</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($supplierInvoice->payments as $payment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm">{{ $payment->payment_date->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-sm font-semibold">{{ number_format($payment->amount, 2) }} {{ $supplierInvoice->currency }}</td>
                                <td class="px-6 py-4 text-sm">{{ $payment->payment_method }}</td>
                                <td class="px-6 py-4 text-sm">
                                    @if($payment->payment_method === 'Chèque')
                                        <div class="space-y-0.5">
                                            <div><strong>{{ $payment->cheque_number ?: $payment->payment_reference ?: '—' }}</strong></div>
                                            <div class="text-xs text-gray-500">{{ $payment->cheque_bank }}</div>
                                            <div class="text-xs text-gray-500">{{ $chequeStatuses[$payment->cheque_status] ?? $payment->cheque_status }}</div>
                                        </div>
                                    @else
                                        {{ $payment->payment_reference ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <x-managed-document-actions
                                        type="supplier-payments"
                                        :id="$payment->id"
                                        :category="$payment->payment_method === 'Chèque' ? 'cheque_scan' : ($payment->payment_method === 'Virement bancaire' ? 'transfer_proof' : 'primary')"
                                        :label="$payment->payment_method === 'Chèque' ? 'Scanner le chèque' : 'Ajouter un justificatif'"
                                    />
                                </td>
                                <td class="px-6 py-4 text-sm">{{ $payment->notes ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <form action="{{ route('supplier-invoices.payments.destroy', [$supplierInvoice, $payment]) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce paiement?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Supprimer">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">Aucun paiement enregistré</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
@endsection
