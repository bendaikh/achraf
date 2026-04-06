@extends('layouts.with-sidebar')

@section('title', 'Enregistrer une dépense')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Enregistrer une dépense</h2>
                </div>
                <a href="{{ route('expenses.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Retour
                </a>
            </div>
        </header>

        <div class="p-8">
            <form action="{{ route('expenses.store') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Désignation *</label>
                        <input type="text" name="designation" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Catégorie de dépenses</label>
                        <input type="text" name="expense_category" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                        <input type="date" name="expense_date" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Montant *</label>
                        <input type="number" step="0.01" name="amount" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Devise</label>
                        <input type="text" name="currency" value="dh - MAD" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Référence</label>
                        <input type="text" name="reference" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                        <select name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">Sélectionner</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mode de règlement</label>
                        <input type="text" name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Compte</label>
                        <input type="text" name="account" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Taxe</label>
                        <select name="tax_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="NO TAXE">NO TAXE</option>
                            <option value="TVA 20%">TVA 20%</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 font-medium">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
