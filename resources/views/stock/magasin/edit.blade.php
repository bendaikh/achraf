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
                Corrige une ligne existante (dépôt + emplacement). Le stock Shopify / En ligne n’est jamais modifié.
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

        @if(empty($slots))
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                <p class="text-sm text-slate-700">
                    Aucune ligne de stock physique pour ce produit. Utilisez <strong>Ajouter</strong> pour créer du stock dans un dépôt / emplacement.
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('products.show', $product) }}"
                       class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-700 text-sm">
                        Retour au produit (Ajouter)
                    </a>
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('products.index') }}"
                       class="px-6 py-2.5 border border-slate-300 rounded-lg text-slate-700 font-medium hover:bg-slate-50 text-sm">
                        Annuler
                    </a>
                </div>
            </div>
        @else
            <form action="{{ route('stock.magasin.update', $product) }}" method="POST"
                  class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-5"
                  x-data="stockAdjustForm({
                      slots: @js($slots),
                      selectedKey: @js(old('slot_key', $selectedKey)),
                      initialQuantity: @js(old('quantity')),
                  })">
                @csrf
                @method('PATCH')

                <input type="hidden" name="warehouse_id" :value="selected?.warehouse_id || ''">
                <input type="hidden" name="warehouse_location_id" :value="selected?.warehouse_location_id || ''">
                <input type="hidden" name="product_variant_id" :value="selected?.product_variant_id || ''">

                <div>
                    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Stock physique actuel</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Sélectionnez la ligne dépôt + emplacement à corriger.</p>
                        </div>
                        <p class="text-sm font-semibold text-slate-800">
                            Total physique : {{ $physicalTotal }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <template x-for="slot in slots" :key="slot.key">
                            <label class="flex items-start gap-3 rounded-xl border px-4 py-3 cursor-pointer transition"
                                   :class="selectedKey === slot.key
                                       ? 'border-amber-400 bg-amber-50 ring-1 ring-amber-200'
                                       : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'">
                                <input type="radio" class="mt-1"
                                       name="slot_key"
                                       :value="slot.key"
                                       x-model="selectedKey"
                                       @change="onSelect()">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-900" x-text="slot.warehouse_name"></p>
                                    <p class="text-sm mt-0.5"
                                       :class="slot.has_location ? 'text-slate-700' : 'text-amber-800 font-medium'">
                                        <span x-text="slot.location_label"></span>
                                        <span class="text-slate-500 font-normal"> → Stock actuel :</span>
                                        <strong class="text-slate-900" x-text="slot.quantity"></strong>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-1" x-show="slot.variant_label" x-text="'Variante : ' + slot.variant_label"></p>
                                    <p class="text-xs text-slate-400 mt-1" x-show="slot.reserved > 0" x-text="'Dont réservé : ' + slot.reserved"></p>
                                </div>
                            </label>
                        </template>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-4" x-show="selected" x-cloak>
                    <div class="rounded-lg bg-sky-50 border border-sky-100 px-4 py-3">
                        <p class="text-xs font-semibold uppercase text-sky-700 mb-1">Ligne sélectionnée</p>
                        <p class="text-sm font-semibold text-sky-950">
                            <span x-text="selected?.warehouse_name"></span>
                            <span class="font-normal text-sky-700"> · </span>
                            <span x-text="selected?.location_label"></span>
                        </p>
                        <p class="text-2xl font-bold text-sky-900 mt-1">
                            <span x-text="currentQuantity"></span>
                            <span class="text-sm font-normal">unité(s) actuelle(s)</span>
                        </p>
                    </div>

                    <div>
                        <label for="quantity" class="block text-xs font-semibold uppercase text-slate-500 mb-1">
                            Nouvelle quantité réelle <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="quantity" id="quantity" min="0" step="1" required
                               x-model.number="newQuantity"
                               class="w-full rounded-lg border-slate-300 text-sm">
                        <p class="mt-1 text-xs text-emerald-700 font-medium">Valeur valide : 0 ou plus. Aucune quantité négative.</p>
                    </div>

                    <div class="rounded-lg border px-4 py-3"
                         :class="delta === 0 ? 'border-slate-200 bg-white' : (delta > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50')">
                        <p class="text-xs font-semibold uppercase mb-1"
                           :class="delta === 0 ? 'text-slate-500' : (delta > 0 ? 'text-emerald-700' : 'text-rose-700')">
                            Écart calculé (mouvement)
                        </p>
                        <p class="text-lg font-bold"
                           :class="delta === 0 ? 'text-slate-700' : (delta > 0 ? 'text-emerald-900' : 'text-rose-900')">
                            <span x-text="currentQuantity"></span>
                            <span class="font-normal text-sm">→</span>
                            <span x-text="newQuantity"></span>
                            <span class="font-normal text-sm">=</span>
                            <span x-text="deltaLabel"></span>
                        </p>
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
                </div>

                <div class="flex flex-wrap gap-3 pt-1">
                    <button type="submit"
                            class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-700 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!selected || delta === 0">
                        Enregistrer la correction
                    </button>
                    <a href="{{ route('products.show', $product) }}"
                       class="px-6 py-2.5 border border-emerald-300 text-emerald-800 rounded-lg font-medium hover:bg-emerald-50 text-sm">
                        + Ajouter du stock
                    </a>
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('products.index') }}"
                       class="px-6 py-2.5 border border-slate-300 rounded-lg text-slate-700 font-medium hover:bg-slate-50 text-sm">
                        Annuler
                    </a>
                </div>
            </form>
        @endif
    </div>
</main>

<script>
function stockAdjustForm(config) {
    return {
        slots: config.slots || [],
        selectedKey: config.selectedKey || (config.slots?.[0]?.key ?? ''),
        newQuantity: 0,
        get selected() {
            return this.slots.find(s => String(s.key) === String(this.selectedKey)) || null;
        },
        get currentQuantity() {
            return this.selected ? Number(this.selected.quantity) : 0;
        },
        get delta() {
            const next = Number(this.newQuantity);
            if (Number.isNaN(next)) return 0;
            return next - this.currentQuantity;
        },
        get deltaLabel() {
            const d = this.delta;
            if (d > 0) return 'mouvement +' + d;
            if (d < 0) return 'mouvement ' + d;
            return 'aucun mouvement';
        },
        init() {
            if (config.initialQuantity !== null && config.initialQuantity !== undefined && config.initialQuantity !== '') {
                this.newQuantity = Number(config.initialQuantity);
            } else {
                this.newQuantity = this.currentQuantity;
            }
        },
        onSelect() {
            this.newQuantity = this.currentQuantity;
        }
    };
}
</script>
@endsection
