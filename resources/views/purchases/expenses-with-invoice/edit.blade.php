@extends('layouts.with-sidebar')

@section('title', 'Modifier une dépense')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Modifier une dépense</h2>
                </div>
                <a href="{{ route('expenses.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Retour
                </a>
            </div>
        </header>

        <div class="p-8">
            <form action="{{ route('expenses.update', $expense) }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Désignation *</label>
                        <input type="text" name="designation" value="{{ old('designation', $expense->designation) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Catégorie de dépenses</label>
                        <input type="text" name="expense_category" value="{{ old('expense_category', $expense->expense_category) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                        <input type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Montant *</label>
                        <input type="number" step="0.01" name="amount" value="{{ old('amount', $expense->amount) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Devise</label>
                        <input type="text" name="currency" value="{{ old('currency', $expense->currency) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Référence</label>
                        <input type="text" name="reference" value="{{ old('reference', $expense->reference) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                        <select name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">Sélectionner</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ old('client_id', $expense->client_id) == $client->id ? 'selected' : '' }}>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mode de règlement</label>
                        <input type="text" name="payment_method" value="{{ old('payment_method', $expense->payment_method) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Compte</label>
                        <input type="text" name="account" value="{{ old('account', $expense->account) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Taxe</label>
                        <select name="tax_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="NO TAXE" {{ old('tax_type', $expense->tax_type) == 'NO TAXE' ? 'selected' : '' }}>NO TAXE</option>
                            <option value="TVA 20%" {{ old('tax_type', $expense->tax_type) == 'TVA 20%' ? 'selected' : '' }}>TVA 20%</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <a href="{{ route('expenses.show', $expense) }}" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150 font-medium">
                        Annuler
                    </a>
                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 font-medium">
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
