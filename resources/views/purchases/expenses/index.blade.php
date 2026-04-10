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
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('expenses.show', $expense) }}" class="text-blue-600 hover:text-blue-900 transition duration-150">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>
                                            <a href="{{ route('expenses.edit', $expense) }}" class="text-green-600 hover:text-green-900 transition duration-150">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <form action="{{ route('expenses.destroy', $expense) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette dépense?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 transition duration-150">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
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
