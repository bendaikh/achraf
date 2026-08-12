@extends('layouts.with-sidebar')

@section('title', 'Importer règlement - Achats')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Importer un fichier de règlement</h2>
                <p class="text-sm text-gray-600 mt-1">CSV / XLSX · rapprochement par n° facture · brouillon avant validation</p>
            </div>
            <a href="{{ route('purchases.payments.index') }}" class="px-4 py-2 bg-gray-100 rounded-lg text-sm">Retour</a>
        </div>
    </header>
    <div class="p-8 max-w-2xl">
        <div class="bg-white rounded-xl border p-6">
            <form method="POST" action="{{ route('purchases.payments.import.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="block text-sm font-medium mb-2">Fichier *</label>
                <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" required class="w-full border rounded-lg p-2 mb-4">
                <p class="text-sm text-gray-600 mb-4">Colonnes : facture / invoice_number, montant / amount, référence.</p>
                <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Analyser</button>
            </form>
        </div>
    </div>
</main>
@endsection
