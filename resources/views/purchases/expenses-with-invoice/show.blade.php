@extends('layouts.with-sidebar')

@section('title', 'Détails de la dépense')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Détails de la dépense</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $expense->designation }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('expenses.edit', $expense) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                        Modifier
                    </a>
                    <a href="{{ route('expenses.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                        Retour
                    </a>
                </div>
            </div>
        </header>

        <div class="p-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Désignation</label>
                            <p class="text-base text-gray-900">{{ $expense->designation }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Catégorie de dépenses</label>
                            <p class="text-base text-gray-900">{{ $expense->expense_category ?? '-' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Date</label>
                            <p class="text-base text-gray-900">{{ $expense->expense_date->format('d/m/Y') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Montant</label>
                            <p class="text-base font-semibold text-gray-900">{{ number_format($expense->amount, 2) }} {{ $expense->currency }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Devise</label>
                            <p class="text-base text-gray-900">{{ $expense->currency }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Référence</label>
                            <p class="text-base text-gray-900">{{ $expense->reference ?? '-' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Client</label>
                            <p class="text-base text-gray-900">{{ $expense->client ? $expense->client->name : '-' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Mode de règlement</label>
                            <p class="text-base text-gray-900">{{ $expense->payment_method ?? '-' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Compte</label>
                            <p class="text-base text-gray-900">{{ $expense->account ?? '-' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Taxe</label>
                            <p class="text-base text-gray-900">{{ $expense->tax_type }}</p>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Créé le: {{ $expense->created_at->format('d/m/Y H:i') }}
                            </div>
                            <form action="{{ route('expenses.destroy', $expense) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette dépense?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-150">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
