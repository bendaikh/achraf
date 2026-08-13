{{-- Shared product/service form. Expects: vatCategories, productTypeCategories, serviceCategories, billingUnits, suppliers, optional $product --}}
@php
    $isEdit = isset($product);
    $defaultKind = old('item_kind', $isEdit ? ($product->item_kind ?? 'stocked') : ($preselectedKind ?? 'stocked'));
    $field = 'w-full px-3.5 py-2.5 border border-slate-200 rounded-lg text-sm text-slate-900 bg-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#fdb819]/40 focus:border-[#fdb819] transition';
    $label = 'block text-sm font-medium text-slate-700 mb-1.5';
@endphp

<div
    x-data="{
        itemKind: @js($defaultKind),
        warehouseId: @js((string) old('warehouse_id', $isEdit ? ($product->warehouse_id ?? '') : (($warehouses ?? collect())->firstWhere('is_primary')?->id ?? ($warehouses ?? collect())->first()?->id ?? ''))),
        locationId: @js((string) old('warehouse_location_id', $isEdit ? ($product->warehouse_location_id ?? '') : '')),
        allLocations: @js(($warehouses ?? collect())->flatMap(fn ($w) => $w->locations->map(fn ($l) => [
            'id' => (string) $l->id,
            'warehouse_id' => (string) $w->id,
            'label' => $l->displayLabel(),
        ]))->values()),
        get filteredLocations() {
            if (!this.warehouseId) return this.allLocations;
            return this.allLocations.filter(l => String(l.warehouse_id) === String(this.warehouseId));
        },
        onWarehouseChange() {
            const stillValid = this.filteredLocations.some(l => String(l.id) === String(this.locationId));
            if (!stillValid) this.locationId = '';
        },
        isStocked() { return this.itemKind === 'stocked'; },
        isService() { return this.itemKind === 'service'; },
        isNonStocked() { return this.itemKind === 'non_stocked'; },
        tracksStock() { return this.isStocked(); },
        nameLabel() { return this.isService() ? 'Nom du service' : 'Nom du produit'; },
        kindTitle() {
            if (this.isService()) return 'Création d’un SERVICE';
            if (this.isNonStocked()) return 'Création d’un PRODUIT (non stocké)';
            return 'Création d’un PRODUIT (stocké)';
        },
        kindHint() {
            if (this.isService()) return 'Les champs de STOCK sont masqués';
            if (this.isNonStocked()) return 'Aucun stock — vente jamais bloquée';
            return 'Les champs de STOCK sont affichés';
        }
    }"
    class="space-y-6"
>

    <input type="hidden" name="item_kind" :value="itemKind">

    {{-- 1. Type selector --}}
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#fdb819] text-white text-xs font-bold">1</span>
                <h2 class="text-base font-semibold text-slate-900">Choisir le type d’élément</h2>
            </div>
            <p class="mt-1 text-sm text-slate-500 pl-8">Ce choix change automatiquement les champs et le comportement stock / ventes.</p>
        </div>

        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
            <button type="button" @click="itemKind = 'stocked'"
                :class="itemKind === 'stocked'
                    ? 'border-[#2563eb] bg-blue-50/70 ring-2 ring-blue-200 shadow-sm'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'"
                class="group text-left rounded-xl border-2 p-4 transition-all duration-150">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 h-11 w-11 rounded-xl flex items-center justify-center"
                         :class="itemKind === 'stocked' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-600'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-900">Produit stocké</p>
                            <span x-show="itemKind === 'stocked'" x-cloak class="text-[10px] font-semibold uppercase tracking-wide text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded">Actif</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Article physique avec stock. Ex: Tapis, LED, Accessoires…</p>
                    </div>
                </div>
            </button>

            <button type="button" @click="itemKind = 'non_stocked'"
                :class="itemKind === 'non_stocked'
                    ? 'border-amber-500 bg-amber-50/70 ring-2 ring-amber-200 shadow-sm'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'"
                class="group text-left rounded-xl border-2 p-4 transition-all duration-150">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 h-11 w-11 rounded-xl flex items-center justify-center"
                         :class="itemKind === 'non_stocked' ? 'bg-amber-500 text-white' : 'bg-amber-100 text-amber-600'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-900">Produit non stocké</p>
                            <span x-show="itemKind === 'non_stocked'" x-cloak class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded">Actif</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Sans stock. Ex: à la demande, personnalisé…</p>
                    </div>
                </div>
            </button>

            <button type="button" @click="itemKind = 'service'"
                :class="itemKind === 'service'
                    ? 'border-violet-500 bg-violet-50/70 ring-2 ring-violet-200 shadow-sm'
                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'"
                class="group text-left rounded-xl border-2 p-4 transition-all duration-150">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 h-11 w-11 rounded-xl flex items-center justify-center"
                         :class="itemKind === 'service' ? 'bg-violet-600 text-white' : 'bg-violet-100 text-violet-600'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-900">Service</p>
                            <span x-show="itemKind === 'service'" x-cloak class="text-[10px] font-semibold uppercase tracking-wide text-violet-800 bg-violet-100 px-1.5 py-0.5 rounded">Actif</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Prestation. Ex: Installation, Montage, Livraison…</p>
                    </div>
                </div>
            </button>
        </div>
        @error('item_kind')
            <p class="px-5 pb-4 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Main form column --}}
        <div class="xl:col-span-2 space-y-6">

            {{-- Form card header --}}
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2"
                     :class="isService() ? 'bg-violet-50/60' : (isNonStocked() ? 'bg-amber-50/60' : 'bg-blue-50/60')">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-white text-xs font-bold"
                              :class="isService() ? 'bg-violet-600' : (isNonStocked() ? 'bg-amber-500' : 'bg-blue-600')">2</span>
                        <h2 class="text-base font-semibold text-slate-900" x-text="kindTitle()"></h2>
                    </div>
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full"
                          :class="isService() ? 'bg-violet-100 text-violet-800' : (isNonStocked() ? 'bg-amber-100 text-amber-900' : 'bg-blue-100 text-blue-800')"
                          x-text="kindHint()"></span>
                </div>

                <div class="p-5 space-y-8">

                    {{-- Image (products only) --}}
                    <div x-show="!isService()" x-cloak>
                        <h3 class="text-sm font-semibold text-slate-900 mb-3">Image</h3>
                        <div x-data="{ imagePreview: {{ $isEdit && $product->image_url ? json_encode($product->image_url) : 'null' }} }" class="flex items-center gap-4">
                            <div class="shrink-0">
                                <template x-if="imagePreview">
                                    <img :src="imagePreview" class="h-24 w-24 rounded-xl object-cover border border-slate-200" alt="">
                                </template>
                                <template x-if="!imagePreview">
                                    <div class="h-24 w-24 rounded-xl border-2 border-dashed border-slate-200 flex items-center justify-center bg-slate-50">
                                        <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                </template>
                            </div>
                            <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <span class="text-[#c48a00]">Choisir une image</span>
                                <input type="file" name="image" accept="image/*" class="hidden"
                                    @change="const file = $event.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = (e) => imagePreview = e.target.result; reader.readAsDataURL(file); }">
                            </label>
                        </div>
                        @error('image')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    {{-- Informations générales --}}
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                            Informations générales
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label for="name" class="{{ $label }}"><span x-text="nameLabel()"></span> <span class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" required
                                    value="{{ old('name', $isEdit ? $product->name : '') }}"
                                    class="{{ $field }} @error('name') border-red-500 @enderror"
                                    :placeholder="isService() ? 'Ex: Installation caméra de recul' : 'Ex: Tapis sur mesure 5D'">
                                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="ref" class="{{ $label }}">Référence (SKU) <span class="text-red-500">*</span></label>
                                <input type="text" name="ref" id="ref" required
                                    value="{{ old('ref', $isEdit ? $product->ref : '') }}"
                                    placeholder="Ex: SKU-001"
                                    class="{{ $field }} @error('ref') border-red-500 @enderror">
                                @error('ref')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div x-show="!isService()" x-cloak>
                                <label for="barcode" class="{{ $label }}">Code-barres (EAN)</label>
                                <input type="text" name="barcode" id="barcode"
                                    value="{{ old('barcode', $isEdit ? $product->barcode : '') }}"
                                    placeholder="Ex: 611125..."
                                    class="{{ $field }}"
                                    :disabled="isService()">
                            </div>

                            <div x-show="isService()" x-cloak>
                                <label for="service_category" class="{{ $label }}">Catégorie de service</label>
                                <select name="service_category" id="service_category" class="{{ $field }}" :disabled="!isService()">
                                    <option value="">Sélectionner…</option>
                                    @foreach($serviceCategories as $category)
                                        <option value="{{ $category }}" {{ old('service_category', $isEdit ? $product->service_category : '') == $category ? 'selected' : '' }}>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="sale_price" class="{{ $label }}">Prix de vente TTC (DHS)</label>
                                <input type="number" name="sale_price" id="sale_price" step="0.01" min="0"
                                    value="{{ old('sale_price', $isEdit ? $product->sale_price : '') }}"
                                    class="{{ $field }}" onchange="calculateHT()">
                            </div>

                            <div>
                                <label for="sale_price_ht" class="{{ $label }}">Prix de vente HT (DHS)</label>
                                <input type="number" name="sale_price_ht" id="sale_price_ht" step="0.01" min="0"
                                    value="{{ old('sale_price_ht', $isEdit ? $product->sale_price_ht : '') }}"
                                    class="{{ $field }}" onchange="calculateTTC()">
                            </div>

                            <div x-show="!isService()" x-cloak>
                                <label for="cost_price_ht" class="{{ $label }}">Prix d’achat / revient HT (DHS)</label>
                                <input type="number" name="cost_price_ht" id="cost_price_ht" step="0.01" min="0"
                                    value="{{ old('cost_price_ht', $isEdit ? $product->cost_price_ht : '') }}"
                                    class="{{ $field }}"
                                    :disabled="isService()">
                            </div>

                            <div x-show="isService()" x-cloak>
                                <label for="cost_price_ht_svc" class="{{ $label }}">Coût (si applicable) HT (DHS)</label>
                                <input type="number" name="cost_price_ht" id="cost_price_ht_svc" step="0.01" min="0"
                                    value="{{ old('cost_price_ht', $isEdit ? $product->cost_price_ht : '') }}"
                                    class="{{ $field }}"
                                    :disabled="!isService()">
                            </div>

                            <div>
                                <label for="vat_category" class="{{ $label }}">TVA</label>
                                <select name="vat_category" id="vat_category" class="{{ $field }}">
                                    <option value="">Sélectionner…</option>
                                    @foreach($vatCategories as $vatCategory)
                                        <option value="{{ $vatCategory }}" {{ old('vat_category', $isEdit ? $product->vat_category : '') == $vatCategory ? 'selected' : '' }}>{{ $vatCategory }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div x-show="!isService()" x-cloak>
                                <label for="last_purchase_price" class="{{ $label }}">Prix dernier achat TTC (DHS)</label>
                                <input type="number" name="last_purchase_price" id="last_purchase_price" step="0.01" min="0"
                                    value="{{ old('last_purchase_price', $isEdit ? $product->last_purchase_price : '') }}"
                                    class="{{ $field }}"
                                    :disabled="isService()">
                            </div>

                            <div x-show="!isService()" x-cloak>
                                <label for="product_margin" class="{{ $label }}">Marge (%)</label>
                                <input type="number" name="product_margin" id="product_margin" step="0.01" min="0"
                                    value="{{ old('product_margin', $isEdit ? $product->product_margin : '') }}"
                                    class="{{ $field }}" onchange="calculatePrices()"
                                    :disabled="isService()">
                            </div>
                        </div>
                    </div>

                    {{-- Stock section --}}
                    <div x-show="tracksStock()" x-cloak>
                        <h3 class="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                            Stock et inventaire
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="stock_quantity" class="{{ $label }}">Quantité en stock (physique)</label>
                                <input type="number" name="stock_quantity" id="stock_quantity" min="0"
                                    value="{{ old('stock_quantity', $isEdit ? $product->stock_quantity : 0) }}"
                                    class="{{ $field }}" :disabled="!tracksStock()">
                            </div>
                            <div>
                                <label for="stock_reserved" class="{{ $label }}">Stock réservé</label>
                                <input type="number" name="stock_reserved" id="stock_reserved" min="0"
                                    value="{{ old('stock_reserved', $isEdit ? ($product->stock_reserved ?? 0) : 0) }}"
                                    class="{{ $field }}" :disabled="!tracksStock()">
                                <p class="mt-1 text-xs text-slate-500">Disponible = physique − réservé</p>
                            </div>
                            <div>
                                <label for="minimum_safety_stock" class="{{ $label }}">Stock minimum</label>
                                <input type="number" name="minimum_safety_stock" id="minimum_safety_stock" min="0"
                                    value="{{ old('minimum_safety_stock', $isEdit ? $product->minimum_safety_stock : '') }}"
                                    class="{{ $field }}" :disabled="!tracksStock()">
                            </div>
                            <div>
                                <label for="minimum_alert_stock" class="{{ $label }}">Stock d’alerte <span class="text-slate-400 font-normal">(défaut {{ $lowStockThreshold ?? 3 }})</span></label>
                                <input type="number" name="minimum_alert_stock" id="minimum_alert_stock" min="0"
                                    value="{{ old('minimum_alert_stock', $isEdit ? $product->minimum_alert_stock : ($lowStockThreshold ?? 3)) }}"
                                    class="{{ $field }} border-amber-200" :disabled="!tracksStock()">
                            </div>
                            <div>
                                <label for="maximum_stock" class="{{ $label }}">Stock maximum</label>
                                <input type="number" name="maximum_stock" id="maximum_stock" min="0"
                                    value="{{ old('maximum_stock', $isEdit ? $product->maximum_stock : '') }}"
                                    class="{{ $field }}" :disabled="!tracksStock()">
                            </div>
                            <div>
                                <label for="warehouse_id" class="{{ $label }}">Dépôt</label>
                                <select name="warehouse_id" id="warehouse_id" class="{{ $field }}" :disabled="!tracksStock()"
                                    x-model="warehouseId" @change="onWarehouseChange()">
                                    <option value="">Sélectionner…</option>
                                    @foreach(($warehouses ?? []) as $warehouse)
                                        <option value="{{ $warehouse->id }}"
                                            @selected((string) old('warehouse_id', $isEdit ? $product->warehouse_id : ($warehouse->is_primary ? $warehouse->id : '')) === (string) $warehouse->id)>
                                            {{ $warehouse->displayLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="warehouse_location_id" class="{{ $label }}">Emplacement</label>
                                <select name="warehouse_location_id" id="warehouse_location_id" class="{{ $field }}" :disabled="!tracksStock() || !warehouseId"
                                    x-model="locationId">
                                    <option value="">Sélectionner…</option>
                                    <template x-for="loc in filteredLocations" :key="loc.id">
                                        <option :value="loc.id" x-text="loc.label" :selected="String(loc.id) === String(locationId)"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-slate-500">Uniquement les emplacements du dépôt sélectionné.</p>
                            </div>
                            <div>
                                <label for="primary_supplier_id" class="{{ $label }}">Fournisseur principal</label>
                                <select name="primary_supplier_id" id="primary_supplier_id" class="{{ $field }}" :disabled="!tracksStock()">
                                    <option value="">Sélectionner…</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ (string) old('primary_supplier_id', $isEdit ? $product->primary_supplier_id : '') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 text-sm text-blue-800 flex gap-2.5">
                            <svg class="w-5 h-5 shrink-0 mt-0.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Ce produit sera géré en stock (entrées, sorties, inventaire, alertes). La vente peut être bloquée si le stock est insuffisant.</span>
                        </div>
                    </div>

                    {{-- Non-stocked --}}
                    <div x-show="isNonStocked()" x-cloak>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="primary_supplier_id_ns" class="{{ $label }}">Fournisseur principal</label>
                                <select name="primary_supplier_id" id="primary_supplier_id_ns" class="{{ $field }}" :disabled="!isNonStocked()">
                                    <option value="">Sélectionner…</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ (string) old('primary_supplier_id', $isEdit ? $product->primary_supplier_id : '') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-900 flex gap-2.5">
                            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Produit non stocké : pas de quantités, pas d’alertes, vente jamais bloquée. Peut être commandé au fournisseur.</span>
                        </div>
                    </div>

                    {{-- Service fields --}}
                    <div x-show="isService()" x-cloak>
                        <h3 class="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-violet-500"></span>
                            Informations spécifiques au service
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="estimated_duration" class="{{ $label }}">Durée estimée</label>
                                <input type="text" name="estimated_duration" id="estimated_duration"
                                    value="{{ old('estimated_duration', $isEdit ? $product->estimated_duration : '') }}"
                                    placeholder="Ex: 1 heure"
                                    class="{{ $field }}" :disabled="!isService()">
                            </div>
                            <div>
                                <label for="billing_unit" class="{{ $label }}">Unité de facturation</label>
                                <select name="billing_unit" id="billing_unit" class="{{ $field }}" :disabled="!isService()">
                                    <option value="">Sélectionner…</option>
                                    @foreach($billingUnits as $value => $unitLabel)
                                        <option value="{{ $value }}" {{ old('billing_unit', $isEdit ? $product->billing_unit : '') == $value ? 'selected' : '' }}>{{ $unitLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="technician_required" class="{{ $label }}">Technicien requis</label>
                                <select name="technician_required" id="technician_required" class="{{ $field }}" :disabled="!isService()">
                                    <option value="0" {{ !old('technician_required', $isEdit ? $product->technician_required : false) ? 'selected' : '' }}>Non</option>
                                    <option value="1" {{ old('technician_required', $isEdit ? $product->technician_required : false) ? 'selected' : '' }}>Oui</option>
                                </select>
                            </div>
                        </div>

                        <div class="rounded-xl bg-violet-50 border border-violet-100 px-4 py-3 text-sm text-violet-900 flex gap-2.5">
                            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Ce service n’est pas géré en stock, peut être vendu sans limite, et ne génère aucun mouvement de stock.</span>
                        </div>
                    </div>

                    {{-- Extra / description --}}
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                            Autres informations
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="status" class="{{ $label }}">Statut</label>
                                <select name="status" id="status" class="{{ $field }}">
                                    <option value="Activer" {{ old('status', $isEdit ? $product->status : 'Activer') == 'Activer' ? 'selected' : '' }}>Activer</option>
                                    <option value="Désactiver" {{ old('status', $isEdit ? $product->status : '') == 'Désactiver' ? 'selected' : '' }}>Désactiver</option>
                                </select>
                            </div>
                            <div>
                                <label for="tag" class="{{ $label }}">Tag</label>
                                <input type="text" name="tag" id="tag"
                                    value="{{ old('tag', $isEdit ? $product->tag : '') }}"
                                    class="{{ $field }}">
                            </div>
                            <div x-show="!isService()" x-cloak>
                                <label for="product_type_category" class="{{ $label }}">Catégorie de type produit</label>
                                <select name="product_type_category" id="product_type_category" class="{{ $field }}" :disabled="isService()">
                                    <option value="">Sélectionner…</option>
                                    @foreach($productTypeCategories as $category)
                                        <option value="{{ $category }}" {{ old('product_type_category', $isEdit ? $product->product_type_category : '') == $category ? 'selected' : '' }}>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="!isService()" x-cloak>
                                <label for="product_category" class="{{ $label }}">Catégorie produit</label>
                                <input type="text" name="product_category" id="product_category"
                                    value="{{ old('product_category', $isEdit ? $product->product_category : '') }}"
                                    class="{{ $field }}" :disabled="isService()">
                            </div>
                        </div>
                        <div>
                            <label for="description" class="{{ $label }}">Description</label>
                            <textarea name="description" id="description" rows="3"
                                class="{{ $field }}"
                                placeholder="Description libre…">{{ old('description', $isEdit ? $product->description : '') }}</textarea>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- Help sidebar --}}
        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-3">Comment ça fonctionne ?</h3>

                <ul x-show="isStocked()" x-cloak class="space-y-2.5 text-sm text-slate-600">
                    <li class="flex gap-2"><span class="text-blue-600 font-bold">✓</span> Entrées / sorties de stock automatiques</li>
                    <li class="flex gap-2"><span class="text-blue-600 font-bold">✓</span> Alertes stock min / alerte</li>
                    <li class="flex gap-2"><span class="text-blue-600 font-bold">✓</span> Inventaire et emplacement</li>
                    <li class="flex gap-2"><span class="text-blue-600 font-bold">✓</span> Blocage vente si stock insuffisant</li>
                </ul>

                <ul x-show="isNonStocked()" x-cloak class="space-y-2.5 text-sm text-slate-600">
                    <li class="flex gap-2"><span class="text-amber-600 font-bold">✓</span> Pas de quantités ni d’alertes</li>
                    <li class="flex gap-2"><span class="text-amber-600 font-bold">✓</span> Vente jamais bloquée</li>
                    <li class="flex gap-2"><span class="text-amber-600 font-bold">✓</span> Commande possible chez le fournisseur</li>
                    <li class="flex gap-2"><span class="text-amber-600 font-bold">✓</span> Idéal pour le sur-mesure / à la demande</li>
                </ul>

                <ul x-show="isService()" x-cloak class="space-y-2.5 text-sm text-slate-600">
                    <li class="flex gap-2"><span class="text-violet-600 font-bold">✓</span> Champs stock masqués automatiquement</li>
                    <li class="flex gap-2"><span class="text-violet-600 font-bold">✓</span> Aucune vérification de stock</li>
                    <li class="flex gap-2"><span class="text-violet-600 font-bold">✓</span> Vente en quantité illimitée</li>
                    <li class="flex gap-2"><span class="text-violet-600 font-bold">✓</span> Aucun mouvement de stock généré</li>
                </ul>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-900 mb-3">Exemples</h3>
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl bg-blue-50 border border-blue-100 p-3">
                        <p class="font-semibold text-blue-800 mb-1.5">Produits</p>
                        <ul class="space-y-1 text-blue-900/80">
                            <li>Tapis 5D</li>
                            <li>LED</li>
                            <li>Essuie-glaces</li>
                            <li>Housses</li>
                        </ul>
                    </div>
                    <div class="rounded-xl bg-violet-50 border border-violet-100 p-3">
                        <p class="font-semibold text-violet-800 mb-1.5">Services</p>
                        <ul class="space-y-1 text-violet-900/80">
                            <li>Installation LED</li>
                            <li>Montage</li>
                            <li>Lavage</li>
                            <li>Livraison</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-xs text-slate-500 leading-relaxed">
                Astuce : sélectionnez d’abord le type, puis remplissez uniquement les champs affichés. Le reste est géré automatiquement.
            </div>
        </aside>
    </div>
</div>
