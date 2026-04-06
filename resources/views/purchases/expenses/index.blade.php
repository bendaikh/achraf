@extends('layouts.with-sidebar')

@section('title', 'Liste des dépenses')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Liste des dépenses</h2>
                    <p class="text-sm text-gray-600 mt-1">Gérer toutes vos dépenses</p>
                </div>
                <a href="{{ route('expenses.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                    + Nouvelle dépense
                </a>
            </div>
        </header>

        <div class="p-8">
            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Catégorie</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($expenses as $expense)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">{{ $expense->designation }}</td>
                                    <td class="px-6 py-4">{{ $expense->expense_date->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4 font-semibold">{{ number_format($expense->amount, 2) }} {{ $expense->currency }}</td>
                                    <td class="px-6 py-4">{{ $expense->expense_category ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        <form action="{{ route('expenses.destroy', $expense) }}" method="POST" onsubmit="return confirm('Supprimer?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">Aucune dépense trouvée</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-6">{{ $expenses->links() }}</div>
        </div>
    </main>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
