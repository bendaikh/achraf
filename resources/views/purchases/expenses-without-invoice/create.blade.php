@extends('layouts.with-sidebar')

@section('title', 'Enregistrer une dépense sans facture')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Enregistrer une dépense sans facture</h2>
            <a href="{{ route('expenses-without-invoice.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                Retour
            </a>
        </div>
    </header>

    <div class="p-8">
        <form action="{{ route('expenses-without-invoice.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            @csrf
            @include('purchases.partials.expense-form-fields', ['showInvoiceFile' => true])

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                @php
                    $selectedClientId = old('client_id');
                    $selectedClientLabel = $selectedClientId ? \App\Models\Client::find($selectedClientId)?->selectLabel() : null;
                @endphp
                <x-client-select-with-create
                    :required="false"
                    :selected-id="$selectedClientId"
                    :selected-label="$selectedClientLabel"
                />
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 font-medium">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</main>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    initClientSelect2('#client_id', { placeholder: 'Rechercher un client...' });
});
</script>
@endpush
