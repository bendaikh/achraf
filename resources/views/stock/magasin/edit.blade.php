@extends('layouts.with-sidebar')

@section('title')
Ajuster le stock physique — {{ $product->ref }}
@endsection

@section('sidebar_page_title', 'Ajuster le stock')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8 max-w-2xl mx-auto">
        <div class="mb-6">
            <div class="flex items-center space-x-2 text-sm text-slate-500 mb-2">
                <a href="{{ route('products.index') }}" class="hover:text-emerald-600">Produits</a>
                <span>/</span>
                <a href="{{ route('products.show', $product) }}" class="hover:text-emerald-600">{{ $product->ref }}</a>
                <span>/</span>
                <span class="text-slate-900">Ajuster le stock</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">Ajuster le stock physique</h1>
            <p class="text-slate-600 mt-1">{{ $product->name }}</p>
            <p class="text-sm text-slate-500 mt-0.5">Réf. {{ $product->ref }}</p>
            <p class="mt-2 text-xs text-amber-800 bg-amber-50 rounded-lg px-3 py-2 inline-block">
                Le stock Shopify / En ligne n'est pas modifié par cette opération.
            </p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 text-red-800 text-sm px-4 py-3">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('stock.magasin.update', $product) }}" method="POST"
              class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4"
              x-data="stockAdjustForm({
                  warehouses: @js($warehouses->map(fn ($w) => [
                      'id' => (string) $w->id,
                      'name' => $w->name,
                      'locations' => $w->locations->map(fn ($l) => ['id' => (string) $l->id, 'label' => $l->displayLabel()])->values(),
                  ])),
                  slotUrl: @js(route('stock.magasin.slot-quantity', $product)),
                  initialWarehouseId: @js((string) old('warehouse_id', $defaultWarehouseId ?? '')),
                  initialLocationId: @js((string) old('warehouse_location_id', $defaultLocationId ?? '')),
                  initialQuantity: @js((int) old('quantity', $currentQuantity)),
                  hasVariants: @js($product->hasVariants()),
                  initialVariantId: @js((string) old('product_variant_id', $product->variants->first()?->id ?? '')),
              })"
              x-init="refreshCurrentQuantity()">
            @csrf
            @method('PATCH')

            @if($product->hasVariants())
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Variante <span class="text-red-500">*</span></label>
                <select name="product_variant_id" x-model="variantId" @change="refreshCurrentQuantity()" required
                        class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($product->variants as $variant)
                        <option value="{{ $variant->id }}" @selected(old('product_variant_id') == $variant->id)>{{ $variant->name ?: $variant->sku }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Dépôt <span class="text-red-500">*</span></label>
                <select name="warehouse_id" x-model="warehouseId" @change="onWarehouseChange()" required
                        class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $defaultWarehouseId) == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Emplacement</label>
                <select name="warehouse_location_id" x-model="locationId" @change="refreshCurrentQuantity()"
                        class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">— Aucun emplacement —</option>
                    <template x-for="loc in filteredLocations" :key="loc.id">
                        <option :value="loc.id" x-text="loc.label"></option>
                    </template>
                </select>
            </div>

            <div class="rounded-lg bg-sky-50 border border-sky-100 px-4 py-3">
                <p class="text-xs font-semibold uppercase text-sky-700 mb-1">Stock physique actuel</p>
                <p class="text-2xl font-bold text-sky-900"><span x-text="currentQuantity"></span> <span class="text-sm font-normal">unité(s)</span></p>
                <p class="text-xs text-sky-600 mt-1" x-show="loading">Chargement…</p>
            </div>

            <div>
                <label for="quantity" class="block text-xs font-semibold uppercase text-slate-500 mb-1">Nouvelle quantité physique <span class="text-red-500">*</span></label>
                <input type="number" name="quantity" id="quantity" min="0" step="1" required
                       x-model.number="newQuantity"
                       class="w-full rounded-lg border-slate-300 text-sm">
                <p class="mt-1 text-xs text-emerald-700 font-medium">Valeur valide : 0 ou plus. Aucune quantité négative.</p>
                <p class="mt-1 text-xs text-slate-500">Saisissez la quantité réelle constatée dans cet emplacement (ex. dernière unité vendue → 0). Le stock Shopify / En ligne n’est pas modifié.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Motif <span class="text-red-500">*</span></label>
                <select name="reason" required class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach(\App\Models\StockMovement::STOCK_ADJUSTMENT_REASONS as $value => $label)
                        <option value="{{ $value }}" @selected(old('reason', \App\Models\StockMovement::REASON_INVENTORY_CORRECTION) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Note <span class="font-normal normal-case text-slate-400">(optionnelle)</span></label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Commentaire libre…">{{ old('notes') }}</textarea>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-700 text-sm">
                    Enregistrer
                </button>
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('products.index') }}"
                   class="px-6 py-2.5 border border-slate-300 rounded-lg text-slate-700 font-medium hover:bg-slate-50 text-sm">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</main>

<script>
function stockAdjustForm(config) {
    return {
        warehouses: config.warehouses || [],
        slotUrl: config.slotUrl,
        warehouseId: config.initialWarehouseId || (config.warehouses[0]?.id ?? ''),
        locationId: config.initialLocationId || '',
        variantId: config.hasVariants ? (config.initialVariantId || '') : '',
        currentQuantity: config.initialQuantity ?? 0,
        newQuantity: config.initialQuantity ?? 0,
        loading: false,
        get filteredLocations() {
            const wh = this.warehouses.find(w => String(w.id) === String(this.warehouseId));
            return wh ? wh.locations : [];
        },
        onWarehouseChange() {
            const ok = this.filteredLocations.some(l => String(l.id) === String(this.locationId));
            if (!ok) this.locationId = '';
            this.refreshCurrentQuantity();
        },
        refreshCurrentQuantity() {
            if (!this.slotUrl || !this.warehouseId) return;
            this.loading = true;
            const params = new URLSearchParams({ warehouse_id: this.warehouseId });
            if (this.locationId) params.set('warehouse_location_id', this.locationId);
            if (this.variantId) params.set('product_variant_id', this.variantId);
            fetch(this.slotUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => r.json())
                .then(data => {
                    this.currentQuantity = data.quantity ?? 0;
                    if (!this.newQuantity && this.newQuantity !== 0) {
                        this.newQuantity = this.currentQuantity;
                    }
                })
                .finally(() => { this.loading = false; });
        }
    };
}
</script>
@endsection
