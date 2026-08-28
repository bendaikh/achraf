@extends('layouts.with-sidebar')

@section('title', 'Historique des règlements')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Historique des règlements fournisseurs</h2>
                <p class="text-sm text-gray-600 mt-1">Les mêmes règlements que sur le compte fournisseur</p>
            </div>
            <a href="{{ route('purchases.payments.index') }}" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg text-sm">Gestion Paiement</a>
        </div>
    </header>
    <div class="p-8">
        <x-table-filters :action="route('purchases.payments.history')" :search="false" grid-cols="md:grid-cols-5">
            <div>
                <label class="block text-sm font-medium mb-1">Fournisseur</label>
                <select name="supplier_id" class="w-full rounded-lg border-gray-300">
                    <option value="">Tous</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Mode de paiement</label>
                <select name="payment_method" class="w-full rounded-lg border-gray-300">
                    <option value="">Tous</option>
                    <option value="Virement bancaire" @selected(request('payment_method') === 'Virement bancaire')>Virement bancaire</option>
                    <option value="Chèque" @selected(request('payment_method') === 'Chèque')>Chèque</option>
                    <option value="Espèces" @selected(request('payment_method') === 'Espèces')>Espèces</option>
                    <option value="Carte bancaire" @selected(request('payment_method') === 'Carte bancaire')>Carte bancaire</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Référence / n° règlement</label>
                <input type="text" name="reference" value="{{ request('reference') }}" class="w-full rounded-lg border-gray-300">
            </div>
        </x-table-filters>

        @include('purchases.payments.partials.history-table', ['rows' => $rows, 'showSupplier' => true])
        <x-table-pagination :paginator="$payments" :bordered="false" item-label="règlements" />
    </div>
</main>
@endsection
