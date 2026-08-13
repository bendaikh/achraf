@extends('layouts.with-sidebar')

@section('title', 'Alertes stock')
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Alertes stock</h1>
            <p class="text-sm text-slate-600 mt-0.5">Produits en stock faible ou en rupture (services et non stockés exclus)</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <a href="{{ route('stock.alerts.index', ['status' => 'low_stock']) }}" class="rounded-xl border p-4 {{ $status === 'low_stock' ? 'border-orange-300 bg-orange-50' : 'border-slate-200 bg-white' }}">
                <p class="text-sm text-slate-500">Stock faible</p>
                <p class="text-2xl font-bold text-orange-600">{{ $lowCount }}</p>
            </a>
            <a href="{{ route('stock.alerts.index', ['status' => 'out_of_stock']) }}" class="rounded-xl border p-4 {{ $status === 'out_of_stock' ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white' }}">
                <p class="text-sm text-slate-500">Rupture de stock</p>
                <p class="text-2xl font-bold text-red-600">{{ $outCount }}</p>
            </a>
        </div>

        <form method="GET" class="mb-5 flex flex-wrap gap-3 items-end bg-white border border-slate-200 rounded-xl p-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
                <select name="status" class="rounded-lg border-slate-300 text-sm">
                    <option value="all" @selected($status === 'all')>Tous (faible + rupture)</option>
                    <option value="low_stock" @selected($status === 'low_stock')>Stock faible</option>
                    <option value="out_of_stock" @selected($status === 'out_of_stock')>Rupture</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" class="rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Dépôt</label>
                <select name="warehouse_id" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Tous</option>
                    @foreach($warehouses as $id => $name)
                        <option value="{{ $id }}" @selected(request('warehouse_id') == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Filtrer</button>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Produit</th>
                        <th class="px-4 py-3 text-right">Disponible</th>
                        <th class="px-4 py-3 text-right">Seuil</th>
                        <th class="px-4 py-3 text-left">Dépôt</th>
                        <th class="px-4 py-3 text-left">Emplacement</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Fournisseur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($products as $product)
                        @php $st = $product->stockStatus(); @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('products.show', $product) }}" class="font-medium text-slate-900 hover:text-[#0a5d8a]">{{ $product->name }}</a>
                                <div class="text-xs text-slate-500 font-mono">{{ $product->ref }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $product->available_stock }}</td>
                            <td class="px-4 py-3 text-right">{{ $product->alertThreshold() }}</td>
                            <td class="px-4 py-3">{{ $product->warehouse?->name ?: ($product->depot ?: '—') }}</td>
                            <td class="px-4 py-3">{{ $product->warehouseLocation?->code ?: ($product->location ?: '—') }}</td>
                            <td class="px-4 py-3">
                                @if($st === 'out_of_stock')
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Rupture</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-800">Stock faible</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $product->primarySupplier?->name ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucune alerte</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if(method_exists($products, 'links'))
                <div class="px-4 py-3 border-t">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</main>
@endsection
