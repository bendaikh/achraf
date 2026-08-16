@extends('layouts.with-sidebar')

@section('title', 'Importer un règlement - Ventes')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Importer un fichier de règlement</h2>
                <p class="text-sm text-gray-600 mt-1">CSV ou XLSX · rapprochement par tracking Shopify · brouillon avant validation</p>
            </div>
            <a href="{{ route('sales.payments.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Retour</a>
        </div>
    </header>

    <div class="p-8 max-w-2xl">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form method="POST" action="{{ route('sales.payments.import.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fichier CSV / XLSX *</label>
                    <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" required
                        class="w-full rounded-lg border border-gray-300 p-2">
                    @error('file')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-900 mb-6">
                    <p class="font-medium mb-2">Colonnes reconnues (en-têtes flexibles) :</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Code d'envoi</strong> / tracking / n° suivi (prioritaire)</li>
                        <li><strong>commande</strong> / order (ex. EGRFTC11470 → FTC11470)</li>
                        <li><strong>Crbt</strong> (montant client) / montant / amount</li>
                        <li><strong>Frais</strong> et <strong>Total</strong> (affichés pour contrôle)</li>
                        <li><strong>Status</strong>, ville, dates de ramassage et de livraison</li>
                        <li>facture, référence (optionnels)</li>
                    </ul>
                    <p class="mt-3">Les lignes livrées avec un CRBT positif sont proposées à la validation. Les retours, remboursements et montants nuls sont conservés dans le contrôle mais automatiquement exclus.</p>
                    <p class="mt-3">Aucun paiement ni mouvement de trésorerie ne sera créé avant votre validation finale.</p>
                </div>
                <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">
                    Analyser le fichier
                </button>
            </form>
        </div>
    </div>
</main>
@endsection
