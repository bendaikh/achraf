@extends('layouts.with-sidebar')

@section('title', 'Transférer du stock')
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80" x-data="stockTransfer(@js($warehouses->map(fn($w) => [
    'id' => $w->id,
    'name' => $w->name,
    'locations' => $w->locations->map(fn($l) => ['id' => $l->id, 'label' => $l->displayLabel()]),
])))">
    <div class="p-4 sm:p-6 lg:p-8 max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-900 mb-1">Transférer du stock</h1>
        <p class="text-sm text-slate-600 mb-6">Génère automatiquement une sortie puis une entrée entre dépôts / emplacements.</p>

        @if(session('error'))
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded text-red-700">{{ session('error') }}</div>
        @endif

        <form action="{{ route('stock.transfer.store') }}" method="POST" class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Produit *</label>
                <select name="product_id" required class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Sélectionner…</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                            {{ $product->ref }} — {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Quantité *</label>
                <input type="number" name="quantity" min="1" required value="{{ old('quantity', 1) }}" class="w-full rounded-lg border-slate-300 text-sm">
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-3">
                <h3 class="text-sm font-semibold text-slate-900">De</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Dépôt source *</label>
                        <select name="from_warehouse_id" x-model="fromWarehouseId" required class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Sélectionner…</option>
                            <template x-for="w in warehouses" :key="w.id">
                                <option :value="w.id" x-text="w.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Emplacement source</label>
                        <select name="from_location_id" x-model="fromLocationId" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">—</option>
                            <template x-for="l in fromLocations" :key="l.id">
                                <option :value="l.id" x-text="l.label"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-blue-50 border border-blue-100 p-4 space-y-3">
                <h3 class="text-sm font-semibold text-slate-900">Vers</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Dépôt destination *</label>
                        <select name="to_warehouse_id" x-model="toWarehouseId" required class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Sélectionner…</option>
                            <template x-for="w in warehouses" :key="'to-'+w.id">
                                <option :value="w.id" x-text="w.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Emplacement destination</label>
                        <select name="to_location_id" x-model="toLocationId" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">—</option>
                            <template x-for="l in toLocations" :key="'tol-'+l.id">
                                <option :value="l.id" x-text="l.label"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="px-5 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Confirmer le transfert</button>
                <a href="{{ route('stock.movements.index') }}" class="px-5 py-2.5 border border-slate-200 rounded-lg text-sm text-slate-700">Annuler</a>
            </div>
        </form>
    </div>
</main>

<script>
function stockTransfer(warehouses) {
    return {
        warehouses,
        fromWarehouseId: @json(old('from_warehouse_id', '')),
        fromLocationId: @json(old('from_location_id', '')),
        toWarehouseId: @json(old('to_warehouse_id', '')),
        toLocationId: @json(old('to_location_id', '')),
        get fromLocations() {
            const w = this.warehouses.find(x => String(x.id) === String(this.fromWarehouseId));
            return w ? w.locations : [];
        },
        get toLocations() {
            const w = this.warehouses.find(x => String(x.id) === String(this.toWarehouseId));
            return w ? w.locations : [];
        }
    }
}
</script>
@endsection
