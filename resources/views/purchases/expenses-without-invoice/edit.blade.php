@extends('layouts.with-sidebar')

@section('title', 'Modifier une dépense sans facture')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Modifier une dépense sans facture</h2>
            <a href="{{ route('expenses-without-invoice.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                Retour
            </a>
        </div>
    </header>

    <div class="p-8">
        <form action="{{ route('expenses-without-invoice.update', $expense) }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            @csrf
            @method('PUT')
            @include('purchases.partials.expense-form-fields', ['expense' => $expense])

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                <select name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">Sélectionner</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id', $expense->client_id) == $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('expenses-without-invoice.show', $expense) }}" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150 font-medium">
                    Annuler
                </a>
                <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 font-medium">
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</main>
@endsection
