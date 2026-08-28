@extends('layouts.with-sidebar')

@section('title', 'À approvisionner')
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        <h1 class="text-2xl font-bold text-slate-900 mb-1">À approvisionner</h1>
        <p class="text-sm text-slate-600 mb-6">Besoins nés des commandes sans stock physique à Belvédère. Plusieurs besoins du même fournisseur peuvent être regroupés dans un seul BC.</p>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded text-green-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded text-red-700">{{ session('error') }}</div>
        @endif

        @forelse($groups as $supplierKey => $groupNeeds)
            @php
                $sid = (int) $supplierKey;
                $label = $sid
                    ? ($groupNeeds->first()->supplier?->name ?: $groupNeeds->first()->suggestedSupplier?->name ?: 'Fournisseur #'.$sid)
                    : 'Aucun fournisseur connu';
            @endphp
            <div class="mb-6 bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-slate-900">{{ $label }}</h2>
                    @if($sid)
                        <form method="POST" action="{{ route('stock.replenishment.generate-po') }}">
                            @csrf
                            <input type="hidden" name="supplier_id" value="{{ $sid }}">
                            @foreach($groupNeeds as $need)
                                <input type="hidden" name="need_ids[]" value="{{ $need->id }}">
                            @endforeach
                            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Générer BC fournisseur</button>
                        </form>
                    @endif
                </div><x-table-list-toolbar table-id="stock-replenishment" />


                <table data-lm-table="stock-replenishment" class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left lm-col lm-col-produit column-produit" data-lm-col="produit">Produit</th>
                            <th class="px-4 py-2 text-right lm-col lm-col-ref column-ref" data-lm-col="ref">Besoin</th>
                            <th class="px-4 py-2 text-left lm-col lm-col-stock column-stock" data-lm-col="stock">Commande</th>
                            <th class="px-4 py-2 text-left lm-col lm-col-minimum column-minimum" data-lm-col="minimum">Fournisseur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($groupNeeds as $need)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $need->product?->name }}</div>
                                    <div class="text-xs font-mono text-slate-500">{{ $need->product?->ref }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $need->quantity_needed }}</td>
                                <td class="px-4 py-3 text-xs">{{ $need->posSale?->ticket_number ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('stock.replenishment.supplier', $need) }}" class="flex gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="supplier_id" class="rounded-lg border-slate-300 text-sm" onchange="this.form.submit()">
                                            <option value="">Aucun fournisseur connu</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}" @selected((int) ($need->supplier_id ?: $need->suggested_supplier_id) === (int) $supplier->id)>
                                                    {{ $supplier->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="bg-white border rounded-xl p-10 text-center text-slate-500">Aucun besoin d’approvisionnement ouvert.</div>
        @endforelse
    </div>
</main>
@endsection
