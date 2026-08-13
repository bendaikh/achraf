@extends('layouts.with-sidebar')

@section('title', 'Inventaire')
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Inventaire</h1>
                <p class="text-sm text-slate-600 mt-0.5">Stock par produit, dépôt et emplacement</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stock.transfer.create') }}" class="px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Transférer du stock</a>
                <a href="{{ route('stock.alerts.index') }}" class="px-4 py-2.5 bg-orange-50 border border-orange-200 rounded-lg text-sm font-medium text-orange-800">
                    Alertes ({{ $lowStockCount }})
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded text-green-700">{{ session('success') }}</div>
        @endif

        <form method="GET" class="mb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 bg-white border border-slate-200 rounded-xl p-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Nom, réf…">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Dépôt</label>
                <select name="warehouse_id" class="w-full rounded-lg border-slate-300 text-sm" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Emplacement</label>
                <select name="warehouse_location_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Tous</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(request('warehouse_location_id') == $loc->id)>{{ $loc->displayLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Niveau</label>
                <select name="filter" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Tous</option>
                    <option value="low" @selected(request('filter') === 'low')>Stock faible / rupture</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Filtrer</button>
                <a href="{{ route('stock.inventory.index') }}" class="px-3 py-2 text-sm text-slate-600">Réinit.</a>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Référence</th>
                            <th class="px-4 py-3 text-left">Produit</th>
                            <th class="px-4 py-3 text-left">Dépôt</th>
                            <th class="px-4 py-3 text-left">Emplacement</th>
                            <th class="px-4 py-3 text-right">Quantité</th>
                            <th class="px-4 py-3 text-right">Disponible</th>
                            <th class="px-4 py-3 text-left">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($stocks as $stock)
                            @php
                                $product = $stock->product;
                                $available = $stock->available();
                                $threshold = $product?->alertThreshold() ?? 3;
                                if ($available <= 0) {
                                    $badge = ['Rupture', 'bg-red-100 text-red-800'];
                                } elseif ($available <= $threshold) {
                                    $badge = ['Stock faible', 'bg-orange-100 text-orange-800'];
                                } else {
                                    $badge = ['En stock', 'bg-green-100 text-green-800'];
                                }
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs">{{ $product?->ref }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ $product ? route('products.show', $product) : '#' }}" class="font-medium text-slate-900 hover:text-[#0a5d8a]">{{ $product?->name }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $stock->warehouse?->name ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $stock->location?->code ?: '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $stock->quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ $available }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucun stock trouvé</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($stocks->hasPages())
                <div class="px-4 py-3 border-t border-slate-100">{{ $stocks->links() }}</div>
            @endif
        </div>
    </div>
</main>
@endsection
