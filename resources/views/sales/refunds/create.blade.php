@extends('layouts.with-sidebar')

@section('title', 'Nouveau remboursement client')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Nouveau remboursement client</h2>
                <p class="text-sm text-gray-600 mt-1">Enregistrer l'argent réellement rendu au client (distinct de l'avoir)</p>
            </div>
            <a href="{{ route('sales.refunds.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Retour</a>
        </div>
    </header>

    <div class="p-8 max-w-3xl">
        @if($invoice)
            <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                Facture liée : <strong>{{ $invoice->invoice_number }}</strong> —
                Avoirs : <strong>{{ number_format($invoice->total_credits, 2) }}</strong> —
                Déjà remboursé : <strong>{{ number_format($invoice->total_refunded, 2) }}</strong> —
                Reste à rembourser : <strong>{{ number_format($invoice->remaining_to_refund, 2) }}</strong>
            </div>
        @endif

        <form action="{{ route('sales.refunds.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">N° remboursement (prévisualisation)</label>
                    <input type="text" value="{{ $refundNumber }}" disabled class="w-full rounded-lg border-gray-300 bg-gray-50 text-gray-500">
                </div>
                <div>
                    <label for="refund_date" class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                    <input type="date" name="refund_date" id="refund_date" value="{{ old('refund_date', now()->toDateString()) }}" required class="w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client *</label>
                    <select name="client_id" id="client_id" required class="w-full rounded-lg border-gray-300">
                        <option value="">Sélectionner...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id', $invoice?->client_id) == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="invoice_id" class="block text-sm font-medium text-gray-700 mb-1">Facture liée</label>
                    <input type="number" name="invoice_id" id="invoice_id" value="{{ old('invoice_id', $invoice?->id) }}" class="w-full rounded-lg border-gray-300" placeholder="ID facture (optionnel)">
                </div>
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Montant *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="amount" value="{{ old('amount', $invoice?->remaining_to_refund) }}" required class="w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Mode de remboursement *</label>
                    <select name="payment_method" id="payment_method" required class="w-full rounded-lg border-gray-300">
                        <option value="especes">Espèces</option>
                        <option value="virement">Virement</option>
                        <option value="carte">Carte</option>
                        <option value="cheque">Chèque</option>
                        <option value="shopify">Shopify / Passerelle</option>
                        <option value="jumia">Jumia</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div>
                    <label for="payment_reference" class="block text-sm font-medium text-gray-700 mb-1">Référence</label>
                    <input type="text" name="payment_reference" id="payment_reference" value="{{ old('payment_reference') }}" class="w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                    <select name="source" id="source" class="w-full rounded-lg border-gray-300">
                        <option value="manual">Manuel</option>
                        <option value="shopify">Shopify</option>
                        <option value="jumia">Jumia</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" id="notes" rows="3" class="w-full rounded-lg border-gray-300">{{ old('notes') }}</textarea>
            </div>

            <div>
                <label for="payment_file" class="block text-sm font-medium text-gray-700 mb-1">Justificatif</label>
                <input type="file" name="payment_file" id="payment_file" class="w-full text-sm text-gray-600">
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Enregistrer le remboursement</button>
            </div>
        </form>
    </div>
</main>
@endsection
