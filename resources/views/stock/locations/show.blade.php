@extends('layouts.with-sidebar')

@section('title', 'Stock '.$warehouse->name)
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80"
      x-data="locationStockPurchasePrice(@js([
          'updateUrlTemplate' => str_replace(
              '999999001',
              '__PRODUCT__',
              route('stock.locations.purchase-price', [$warehouse, 999999001])
          ),
          'asOf' => $asOf->format('Y-m-d'),
          'locationId' => $selectedLocationId,
          'csrf' => csrf_token(),
      ]))">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><a href="{{ route('stock.locations.index') }}" class="hover:underline">Emplacements</a></p>
                <h1 class="text-2xl font-bold text-slate-900">Stock {{ $warehouse->name }}</h1>
                <p class="text-sm text-slate-600 mt-0.5">
                    @if($warehouse->isOnline())
                        Miroir historique du canal Shopify — n’est pas un dépôt de picking.
                        Le stock Shopify publié = somme des disponibles des dépôts physiques « Disponible pour Shopify ».
                    @elseif($warehouse->available_for_shopify)
                        Dépôt physique alimentant Shopify. Disponible Shopify = physique − réservé (ce dépôt + autres dépôts autorisés).
                    @else
                        Stock physique de ce dépôt uniquement — non additionné au canal Shopify.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stock.locations.export', [$warehouse, 'excel']) }}?as_of={{ $asOf->format('Y-m-d') }}@if($selectedLocationId ?? null)&warehouse_location_id={{ $selectedLocationId }}@endif" class="px-4 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700">Télécharger Excel</a>
                <a href="{{ route('stock.locations.export', [$warehouse, 'pdf']) }}?as_of={{ $asOf->format('Y-m-d') }}@if($selectedLocationId ?? null)&warehouse_location_id={{ $selectedLocationId }}@endif" class="px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">Télécharger PDF</a>
                @if($warehouse->isPhysical())
                    <a href="{{ route('stock.locations.count', $warehouse) }}" class="px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700">Inventaire physique</a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded text-green-700">{{ session('success') }}</div>
        @endif

        <div x-show="flash" x-cloak x-text="flash" class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded text-green-700"></div>
        <div x-show="error" x-cloak x-text="error" class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded text-red-700"></div>

        <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 bg-white border border-slate-200 rounded-xl p-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">État du stock au</label>
                <input type="date" name="as_of" value="{{ $asOf->format('Y-m-d') }}" class="rounded-lg border-slate-300 text-sm">
            </div>
            @if(($locations ?? collect())->isNotEmpty())
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Emplacement</label>
                <select name="warehouse_location_id" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Tous les emplacements</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(($selectedLocationId ?? null) == $loc->id)>{{ $loc->displayLabel() }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Afficher</button>
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5" id="location-stock-totals">
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">Références</p>
                <p class="text-2xl font-bold tabular-nums" data-total="references">{{ number_format($report['references']) }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">Quantité totale</p>
                <p class="text-2xl font-bold tabular-nums" data-total="quantity">{{ number_format($report['quantity']) }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">Valeur HT</p>
                <p class="text-2xl font-bold tabular-nums" data-total="value_ht">{{ number_format($report['value_ht'], 2) }} DH</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">TVA</p>
                <p class="text-2xl font-bold tabular-nums" data-total="value_vat">{{ number_format($report['value_vat'] ?? 0, 2) }} DH</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">Valeur TTC</p>
                <p class="text-2xl font-bold tabular-nums" data-total="value_ttc">{{ number_format($report['value_ttc'], 2) }} DH</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm" id="location-stock-table">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-3 text-left">SKU</th>
                            <th class="px-3 py-3 text-left">Produit</th>
                            <th class="px-3 py-3 text-left">Empl.</th>
                            <th class="px-3 py-3 text-left">Fournisseur</th>
                            <th class="px-3 py-3 text-right">Qté</th>
                            <th class="px-3 py-3 text-right">Rés.</th>
                            <th class="px-3 py-3 text-right">Dispo.</th>
                            <th class="px-3 py-3 text-right">PA HT</th>
                            <th class="px-3 py-3 text-right">PA TTC</th>
                            <th class="px-3 py-3 text-right">Val. HT</th>
                            <th class="px-3 py-3 text-right">Val. TTC</th>
                            <th class="px-3 py-3 text-right">PV HT</th>
                            <th class="px-3 py-3 text-right">PV TTC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['rows'] as $row)
                            <tr data-product-id="{{ $row->product_id }}"
                                data-location="{{ $row->location ?? '—' }}"
                                data-quantity="{{ $row->quantity }}">
                                <td class="px-3 py-3 font-mono text-xs">{{ $row->sku }}</td>
                                <td class="px-3 py-3 font-medium">{{ $row->name }}</td>
                                <td class="px-3 py-3">{{ $row->location ?? '—' }}</td>
                                <td class="px-3 py-3">{{ $row->supplier ?: '—' }}</td>
                                <td class="px-3 py-3 text-right font-semibold" data-col="quantity">{{ $row->quantity }}</td>
                                <td class="px-3 py-3 text-right">{{ $row->reserved ?? 0 }}</td>
                                <td class="px-3 py-3 text-right">{{ $row->available ?? $row->quantity }}</td>
                                <td class="px-3 py-3 text-right tabular-nums" data-col="price_ht">
                                    {{ ($row->has_purchase_cost ?? false) ? number_format((float) $row->price_ht, 2) : 'Non renseigné' }}
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums" data-col="price_ttc">
                                    <div x-show="editingId !== {{ (int) $row->product_id }}">
                                        @if($row->has_purchase_cost ?? false)
                                            <button type="button"
                                                    class="tabular-nums text-slate-900 hover:text-[#0a5d8a] hover:underline"
                                                    @click="startEdit({{ (int) $row->product_id }}, {{ number_format((float) $row->price_ttc, 2, '.', '') }})"
                                                    title="Modifier le prix d’achat (fiche produit)">
                                                {{ number_format((float) $row->price_ttc, 2) }}
                                            </button>
                                        @else
                                            <div class="inline-flex flex-col items-end gap-1">
                                                <span class="text-slate-500">Non renseigné</span>
                                                <button type="button"
                                                        class="text-xs font-semibold text-[#0a5d8a] hover:underline"
                                                        @click="startEdit({{ (int) $row->product_id }}, '')">
                                                    Ajouter le prix d’achat
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="inline-flex flex-col items-end gap-1" x-show="editingId === {{ (int) $row->product_id }}" x-cloak>
                                        <div class="flex items-center gap-1 justify-end">
                                            <input type="text"
                                                   inputmode="decimal"
                                                   x-model="draft"
                                                   @keydown.enter.prevent="save()"
                                                   @keydown.escape.prevent="cancel()"
                                                   class="w-24 rounded border border-slate-300 px-2 py-1 text-right text-sm"
                                                   placeholder="70,00"
                                                   :disabled="saving">
                                            <span class="text-xs text-slate-500 whitespace-nowrap">DH TTC</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                    class="text-xs font-semibold text-emerald-700 hover:underline disabled:opacity-50"
                                                    @click="save()"
                                                    :disabled="saving">
                                                Valider
                                            </button>
                                            <button type="button"
                                                    class="text-xs text-slate-500 hover:underline"
                                                    @click="cancel()"
                                                    :disabled="saving">
                                                Annuler
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums" data-col="value_ht">
                                    {{ ($row->has_purchase_cost ?? false) ? number_format($row->value_ht, 2) : 'Non renseigné' }}
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums" data-col="value_ttc">
                                    {{ ($row->has_purchase_cost ?? false) ? number_format($row->value_ttc, 2) : 'Non renseigné' }}
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($row->sale_price_ht ?? 0, 2) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($row->sale_price_ttc ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="px-4 py-10 text-center text-slate-500">Aucun produit en stock dans ce dépôt à cette date.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td class="px-3 py-3" colspan="4">TOTAL · <span data-total="references">{{ $report['references'] }}</span> réf.</td>
                            <td class="px-3 py-3 text-right" data-total="quantity">{{ number_format($report['quantity']) }}</td>
                            <td colspan="4"></td>
                            <td class="px-3 py-3 text-right" data-total="value_ht">{{ number_format($report['value_ht'], 2) }}</td>
                            <td class="px-3 py-3 text-right" data-total="value_ttc">{{ number_format($report['value_ttc'], 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function locationStockPurchasePrice(config) {
    return {
        editingId: null,
        draft: '',
        saving: false,
        flash: '',
        error: '',
        startEdit(productId, current) {
            this.error = '';
            this.flash = '';
            this.editingId = productId;
            this.draft = current === '' || current == null
                ? ''
                : String(current).replace('.', ',');
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('input[type="text"][placeholder="70,00"]');
                for (const input of inputs) {
                    if (input.offsetParent !== null) {
                        input.focus();
                        input.select();
                        break;
                    }
                }
            });
        },
        cancel() {
            this.editingId = null;
            this.draft = '';
            this.saving = false;
        },
        parsePrice(raw) {
            if (raw == null) return null;
            let s = String(raw).trim();
            if (!s) return null;
            s = s.replace(/\s/g, '')
                .replace(/DH/gi, '')
                .replace(/TTC/gi, '')
                .replace(/[^\d,.\-]/g, '');
            if (s.indexOf(',') >= 0 && s.indexOf('.') >= 0) {
                s = s.replace(/\./g, '').replace(',', '.');
            } else {
                s = s.replace(',', '.');
            }
            const n = Number(s);
            return Number.isFinite(n) ? n : null;
        },
        formatMoney(value) {
            return Number(value).toLocaleString('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        updateUrl(productId) {
            return config.updateUrlTemplate.replace('__PRODUCT__', String(productId));
        },
        async save() {
            if (this.saving || !this.editingId) return;
            const price = this.parsePrice(this.draft);
            if (price === null || price < 0) {
                this.error = 'Saisissez un prix d’achat TTC valide (ex. 70,00).';
                return;
            }

            this.saving = true;
            this.error = '';
            this.flash = '';

            try {
                const body = {
                    last_purchase_price: price,
                    as_of: config.asOf,
                };
                if (config.locationId) {
                    body.warehouse_location_id = config.locationId;
                }

                const response = await fetch(this.updateUrl(this.editingId), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Enregistrement impossible.');
                }

                this.applyProductUpdate(data);
                this.flash = data.message || 'Prix d’achat enregistré.';
                this.cancel();
                // Recharge pour réafficher le formulaire Alpine d’édition sur la ligne.
                window.setTimeout(() => window.location.reload(), 400);
            } catch (e) {
                this.error = e.message || 'Enregistrement impossible.';
                this.saving = false;
            }
        },
        applyProductUpdate(data) {
            const productId = String(data.product_id);
            const rows = Array.isArray(data.rows) ? data.rows : [];

            document.querySelectorAll('tr[data-product-id="' + productId + '"]').forEach((tr) => {
                const location = tr.getAttribute('data-location') || '—';
                const match = rows.find((r) => String(r.location || '—') === location) || rows[0];
                const priceHt = match ? match.price_ht : data.price_ht;
                const priceTtc = match ? match.price_ttc : data.price_ttc;
                const valueHt = match ? match.value_ht : null;
                const valueTtc = match ? match.value_ttc : null;

                const htCell = tr.querySelector('[data-col="price_ht"]');
                const ttcCell = tr.querySelector('[data-col="price_ttc"]');
                const valHtCell = tr.querySelector('[data-col="value_ht"]');
                const valTtcCell = tr.querySelector('[data-col="value_ttc"]');

                if (htCell) htCell.textContent = this.formatMoney(priceHt);
                if (valHtCell && valueHt != null) valHtCell.textContent = this.formatMoney(valueHt);
                if (valTtcCell && valueTtc != null) valTtcCell.textContent = this.formatMoney(valueTtc);

                if (ttcCell) {
                    ttcCell.innerHTML = '';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'tabular-nums text-slate-900 hover:text-[#0a5d8a] hover:underline';
                    btn.title = 'Modifier le prix d’achat (fiche produit)';
                    btn.textContent = this.formatMoney(priceTtc);
                    btn.addEventListener('click', () => this.startEdit(Number(productId), Number(priceTtc).toFixed(2)));
                    ttcCell.appendChild(btn);
                }
            });

            if (data.totals) {
                const setTotal = (key, suffix) => {
                    document.querySelectorAll('[data-total="' + key + '"]').forEach((el) => {
                        const raw = data.totals[key];
                        if (key === 'references' || key === 'quantity') {
                            el.textContent = Number(raw).toLocaleString('fr-FR');
                        } else {
                            el.textContent = this.formatMoney(raw) + (suffix || '');
                        }
                    });
                };
                setTotal('references');
                setTotal('quantity');
                setTotal('value_ht', ' DH');
                setTotal('value_ttc', ' DH');
                setTotal('value_vat', ' DH');
            }
        },
    };
}
</script>
@endsection
