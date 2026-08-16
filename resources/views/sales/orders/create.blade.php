@extends('layouts.with-sidebar')

@section('title', 'Créer une commande')
@section('sidebar_page_title', 'Créer une commande')

@section('main')
{{-- Tailwind is loaded from the Play CDN without the forms plugin, so form
     controls need explicit borders and padding on this screen. --}}
<style>
    #orderCreate input[type="text"],
    #orderCreate input[type="search"],
    #orderCreate input[type="number"],
    #orderCreate input[type="date"],
    #orderCreate input[type="datetime-local"],
    #orderCreate select,
    #orderCreate textarea {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        background-color: #fff;
        color: #111827;
    }
    #orderCreate input:disabled {
        background-color: #f3f4f6;
        color: #6b7280;
    }
    #orderCreate input:focus,
    #orderCreate select:focus,
    #orderCreate textarea:focus {
        outline: none;
        border-color: #fdb819;
        box-shadow: 0 0 0 3px rgba(253, 184, 25, 0.25);
    }
    #orderCreate input[type="search"]::-webkit-search-cancel-button {
        cursor: pointer;
    }
    #orderCreate .qty-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 2rem;
        width: 2rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #fff;
        font-weight: 600;
        color: #374151;
    }
    #orderCreate .qty-btn:hover { background: #f9fafb; }
</style>

<main id="orderCreate" class="flex-1 w-full min-w-0 bg-gray-50"
    x-data="orderForm({
        productSearchUrl: @js(route('orders.products.search')),
        oldItems: @js(old('items', []))
    })">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-20">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('orders.index') }}" class="text-gray-500 hover:text-gray-900" aria-label="Retour">←</a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Créer une commande</h1>
                    <p class="text-sm text-gray-600">Commande Libromart avec attribution et synchronisation Shopify</p>
                </div>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm">
                <span class="text-blue-700">Utilisateur connecté</span>
                <strong class="block text-blue-950">{{ $currentUser->name }}</strong>
            </div>
        </div>
    </header>

    <form method="POST" action="{{ route('orders.store') }}" class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto">
        @csrf
        <input type="hidden" name="creation_token" value="{{ old('creation_token', $creationToken) }}">

        @if($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <strong>La commande n’a pas pu être enregistrée.</strong>
                <ul class="mt-2 list-disc pl-5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-5">
            <div class="space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <section class="bg-white rounded-xl border border-blue-200 shadow-sm p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-800 mb-4">1. Client</h2>
                        <x-client-select-with-create
                            :selected-id="old('client_id')"
                            :selected-label="null"
                            select-id="order_client_id"
                        />
                        <div id="selectedClientSummary" class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
                            Recherchez et sélectionnez un client existant.
                        </div>
                    </section>

                    <section class="bg-white rounded-xl border border-indigo-200 shadow-sm p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-indigo-800 mb-4">2. Informations commande</h2>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-sm text-gray-700">Date
                                <input type="datetime-local" name="sold_at" value="{{ old('sold_at', now()->format('Y-m-d\TH:i')) }}" required
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </label>
                            <label class="text-sm text-gray-700">Statut
                                <select name="status" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    <option value="pending">En attente</option>
                                    <option value="completed">Confirmée</option>
                                    <option value="cancelled">Annulée</option>
                                </select>
                            </label>
                            <label class="text-sm text-gray-700">Canal
                                <select name="sales_channel" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    <option value="shopify">Shopify</option>
                                    <option value="manual">Libromart</option>
                                    <option value="phone">Téléphone</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="store">Magasin</option>
                                </select>
                            </label>
                            <label class="text-sm text-gray-700">Devise
                                <select name="currency" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    <option value="MAD">MAD — Dirham</option>
                                    <option value="EUR">EUR — Euro</option>
                                    <option value="USD">USD — Dollar</option>
                                </select>
                            </label>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">Source enregistrée : Libromart</p>
                    </section>

                    <section class="bg-white rounded-xl border border-red-200 shadow-sm p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-red-800 mb-4">3. Utilisateur / commercial assigné</h2>
                        <label class="text-sm text-gray-700">Créé par
                            <input value="{{ $currentUser->name }} ({{ $currentUser->email }})" disabled
                                class="mt-1 w-full rounded-lg border-gray-200 bg-gray-100 text-sm">
                        </label>
                        <label class="block mt-3 text-sm text-gray-700">Commercial attribué
                            @if($currentUser->isSuperAdmin())
                                <select name="assigned_user_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    @foreach($assignableUsers as $assignable)
                                        <option value="{{ $assignable->id }}" @selected(old('assigned_user_id', $currentUser->id) == $assignable->id)>
                                            {{ $assignable->name }} ({{ $assignable->email }})
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <input type="hidden" name="assigned_user_id" value="{{ $currentUser->id }}">
                                <input value="{{ $currentUser->name }}" disabled
                                    class="mt-1 w-full rounded-lg border-gray-200 bg-gray-100 text-sm">
                            @endif
                        </label>
                        <p class="mt-3 text-xs text-gray-500">Les identifiants du créateur et du commercial sont enregistrés séparément.</p>
                    </section>
                </div>

                <section class="bg-white rounded-xl border border-purple-200 shadow-sm p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-purple-800">4. Produits</h2>
                        <span class="text-xs text-gray-500">Prix issus du catalogue Libromart / Shopify</span>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <div class="relative flex-1 min-w-0" @click.outside="productResults = []">
                            <input type="search" x-ref="productSearch" x-model="productQuery"
                                @input.debounce.300ms="searchProducts()"
                                @focus="if (productResults.length === 0 && productQuery.trim() === '') browseCatalog()"
                                placeholder="Rechercher par désignation, SKU, variante ou code-barres…">
                            <span x-show="searching" x-cloak class="absolute right-9 top-2.5 text-xs text-gray-500">Recherche…</span>

                            <div x-show="productResults.length" x-cloak
                                class="absolute z-30 mt-1 w-full max-h-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl">
                                <template x-for="result in productResults" :key="result.key">
                                    <button type="button" @click="addProduct(result)"
                                        class="w-full text-left px-4 py-3 hover:bg-purple-50 border-b border-gray-100 last:border-0">
                                        <span class="font-medium text-gray-900" x-text="result.name"></span>
                                        <span x-show="result.variant" class="text-gray-600" x-text="' — ' + result.variant"></span>
                                        <span class="block text-xs text-gray-500">
                                            <span x-text="result.sku || 'Sans SKU'"></span>
                                            · <span x-text="money(result.price)"></span>
                                            · Stock <span x-text="result.stock ?? '—'"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>

                            <p x-show="noResults" x-cloak class="mt-2 text-sm text-gray-500">
                                Aucun produit trouvé pour cette recherche.
                            </p>
                        </div>

                        <button type="button" @click="browseCatalog()"
                            class="shrink-0 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Parcourir
                        </button>
                        <button type="button" @click="focusSearch()"
                            class="shrink-0 px-4 py-2 rounded-lg bg-purple-600 text-sm font-medium text-white hover:bg-purple-700">
                            + Ajouter un produit
                        </button>
                    </div>

                    <div x-show="!items.length" x-cloak
                        class="mt-4 rounded-lg border-2 border-dashed border-gray-200 px-4 py-10 text-center">
                        <p class="text-sm text-gray-600">Aucun produit dans cette commande.</p>
                        <button type="button" @click="browseCatalog()"
                            class="mt-3 px-4 py-2 rounded-lg bg-purple-600 text-sm font-medium text-white hover:bg-purple-700">
                            + Ajouter un produit
                        </button>
                    </div>

                    <div class="mt-4 overflow-x-auto" x-show="items.length" x-cloak>
                        <table class="min-w-[900px] w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Produit / variante</th>
                                    <th class="px-3 py-2 text-left">SKU</th>
                                    <th class="px-3 py-2 text-right">Prix TTC</th>
                                    <th class="px-3 py-2 text-center">Quantité</th>
                                    <th class="px-3 py-2 text-right">Remise ligne</th>
                                    <th class="px-3 py-2 text-right">TVA</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(item, index) in items" :key="item.key">
                                    <tr>
                                        <td class="px-3 py-3">
                                            <input type="hidden" :name="`items[${index}][product_id]`" :value="item.product_id">
                                            <input type="hidden" :name="`items[${index}][variant_id]`" :value="item.variant_id || ''">
                                            <strong class="block text-gray-900" x-text="item.name"></strong>
                                            <span class="text-xs text-gray-500" x-text="item.variant || 'Variante par défaut'"></span>
                                        </td>
                                        <td class="px-3 py-3 font-mono text-xs" x-text="item.sku || '—'"></td>
                                        <td class="px-3 py-3 text-right" x-text="money(item.price)"></td>
                                        <td class="px-3 py-3">
                                            <div class="flex justify-center items-center gap-1">
                                                <button type="button" @click="item.quantity = Math.max(1, item.quantity - 1)" class="qty-btn">−</button>
                                                <input type="number" min="1" max="10000" x-model.number="item.quantity"
                                                    :name="`items[${index}][quantity]`" class="!w-16 text-center">
                                                <button type="button" @click="item.quantity++" class="qty-btn">+</button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <input type="number" min="0" step="0.01" x-model.number="item.discount"
                                                :name="`items[${index}][discount]`" class="!w-24 text-right">
                                        </td>
                                        <td class="px-3 py-3 text-right" x-text="item.tax_rate + '%'"></td>
                                        <td class="px-3 py-3 text-right font-semibold" x-text="money(lineTotal(item))"></td>
                                        <td class="px-3 py-3 text-right">
                                            <button type="button" @click="items.splice(index, 1)" class="text-red-600 hover:text-red-800">Supprimer</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <section class="bg-white rounded-xl border border-green-200 p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-green-800 mb-4">5. Paiement</h2>
                        <label class="block text-sm">Statut
                            <select name="payment_status" class="mt-1 w-full rounded-lg border-gray-300">
                                <option value="pending">Non payé</option>
                                <option value="partially_paid">Partiellement payé</option>
                                <option value="paid">Payé</option>
                                <option value="refunded">Remboursé</option>
                            </select>
                        </label>
                        <label class="block mt-3 text-sm">Mode de paiement
                            <select name="payment_method" class="mt-1 w-full rounded-lg border-gray-300">
                                <option value="cash">Espèces / à la livraison</option>
                                <option value="card">Carte bancaire</option>
                                <option value="transfer">Virement bancaire</option>
                                <option value="cheque">Chèque</option>
                            </select>
                        </label>
                    </section>

                    <section class="bg-white rounded-xl border border-blue-200 p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-800 mb-4">6. Livraison</h2>
                        <input name="shipping_address" value="{{ old('shipping_address') }}" placeholder="Adresse de livraison" class="w-full rounded-lg border-gray-300 text-sm">
                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <input name="shipping_city" value="{{ old('shipping_city') }}" placeholder="Ville" class="rounded-lg border-gray-300 text-sm">
                            <input name="shipping_postal_code" value="{{ old('shipping_postal_code') }}" placeholder="Code postal" class="rounded-lg border-gray-300 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <input name="shipping_country" value="{{ old('shipping_country', 'Maroc') }}" placeholder="Pays" class="rounded-lg border-gray-300 text-sm">
                            <input name="shipping_amount" x-model.number="shipping" type="number" min="0" step="0.01" placeholder="Frais" class="rounded-lg border-gray-300 text-sm">
                        </div>
                        <input name="shipping_method" value="{{ old('shipping_method') }}" placeholder="Mode de livraison" class="mt-2 w-full rounded-lg border-gray-300 text-sm">
                        <textarea name="delivery_note" rows="2" placeholder="Note : appeler le client avant livraison" class="mt-2 w-full rounded-lg border-gray-300 text-sm">{{ old('delivery_note') }}</textarea>
                    </section>

                    <section class="bg-white rounded-xl border border-amber-200 p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-amber-800 mb-4">7. Remise</h2>
                        <div class="grid grid-cols-2 gap-2">
                            <select name="discount_type" x-model="discountType" class="rounded-lg border-gray-300 text-sm">
                                <option value="amount">Montant</option>
                                <option value="percent">Pourcentage</option>
                            </select>
                            <input name="discount_value" x-model.number="discountValue" type="number" min="0" step="0.01" class="rounded-lg border-gray-300 text-sm">
                        </div>
                        <input name="discount_reason" value="{{ old('discount_reason') }}" placeholder="Motif de la remise" class="mt-3 w-full rounded-lg border-gray-300 text-sm">
                    </section>
                </div>

                <section class="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 mb-4">8. Notes et tags</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <textarea name="internal_note" rows="3" placeholder="Note interne (non visible par le client)" class="w-full rounded-lg border-gray-300">{{ old('internal_note') }}</textarea>
                        <input name="tags" value="{{ old('tags') }}" placeholder="VIP, À rappeler, WhatsApp…" class="w-full rounded-lg border-gray-300 self-start">
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 xl:sticky xl:top-24">
                    <h2 class="font-bold text-gray-900 mb-5">Récapitulatif</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-gray-600">Sous-total HT</span><strong x-text="money(subtotalHt)"></strong></div>
                        <div class="flex justify-between"><span class="text-gray-600">TVA</span><strong x-text="money(taxTotal)"></strong></div>
                        <div class="flex justify-between text-amber-700"><span>Remise</span><strong x-text="'− ' + money(globalDiscount)"></strong></div>
                        <div class="flex justify-between"><span class="text-gray-600">Livraison</span><strong x-text="money(shippingNumber)"></strong></div>
                        <div class="border-t pt-4 flex justify-between text-xl"><span class="font-bold">TOTAL</span><strong class="text-blue-700" x-text="money(grandTotal)"></strong></div>
                    </div>

                    <div class="mt-6 rounded-lg border p-3 text-sm"
                        :class="@js($shopifyIntegration?->enabled) ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'">
                        <strong>Shopify</strong>
                        <p class="mt-1 text-xs text-gray-600">
                            {{ $shopifyIntegration?->enabled ? 'Intégration active. La commande peut être synchronisée.' : 'Intégration non configurée ou désactivée.' }}
                        </p>
                    </div>

                    <div class="mt-6 space-y-2">
                        <button type="submit" name="submit_action" value="save" :disabled="!items.length"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white font-medium text-gray-800 hover:bg-gray-50 disabled:opacity-50">
                            Enregistrer dans Libromart
                        </button>
                        <button type="submit" name="submit_action" value="sync" :disabled="!items.length"
                            class="w-full px-4 py-3 rounded-lg bg-green-600 font-medium text-white hover:bg-green-700 disabled:opacity-50">
                            Enregistrer et synchroniser Shopify
                        </button>
                        <a href="{{ route('orders.index') }}" class="block text-center px-4 py-2 text-sm text-gray-600">Annuler</a>
                    </div>
                    <p class="mt-4 text-xs text-gray-500">En cas d’erreur Shopify, la commande restera enregistrée et pourra être resynchronisée.</p>
                </section>
            </aside>
        </div>
    </form>
</main>

<script>
function orderForm(config) {
    return {
        items: Array.isArray(config.oldItems) ? config.oldItems : [],
        productQuery: '',
        productResults: [],
        searching: false,
        noResults: false,
        discountType: @js(old('discount_type', 'amount')),
        discountValue: Number(@js(old('discount_value', 0))) || 0,
        shipping: Number(@js(old('shipping_amount', 0))) || 0,
        async searchProducts() {
            const term = this.productQuery.trim();
            if (term.length < 2) {
                this.productResults = [];
                this.noResults = false;
                return;
            }
            await this.fetchProducts({ q: term });
        },
        async browseCatalog() {
            await this.fetchProducts({ q: this.productQuery.trim(), browse: 1 });
            this.focusSearch();
        },
        focusSearch() {
            this.$refs.productSearch?.focus();
        },
        async fetchProducts(params) {
            this.searching = true;
            this.noResults = false;
            try {
                const query = new URLSearchParams(params).toString();
                const response = await fetch(config.productSearchUrl + '?' + query, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                const payload = response.ok ? await response.json() : {};
                this.productResults = payload.products || [];
                this.noResults = this.productResults.length === 0;
            } catch (error) {
                this.productResults = [];
                this.noResults = true;
            } finally {
                this.searching = false;
            }
        },
        addProduct(product) {
            const existing = this.items.find(item => item.key === product.key);
            if (existing) existing.quantity++;
            else this.items.push({ ...product, quantity: 1, discount: 0 });
            this.productQuery = '';
            this.productResults = [];
            this.noResults = false;
        },
        lineTotal(item) {
            return Math.max(0, (Number(item.price) * Number(item.quantity || 0)) - Number(item.discount || 0));
        },
        get subtotalHt() {
            return this.items.reduce((sum, item) => sum + this.lineTotal(item) / (1 + Number(item.tax_rate || 0) / 100), 0);
        },
        get taxTotal() {
            return this.items.reduce((sum, item) => {
                const ht = this.lineTotal(item) / (1 + Number(item.tax_rate || 0) / 100);
                return sum + this.lineTotal(item) - ht;
            }, 0);
        },
        get beforeDiscount() { return this.subtotalHt + this.taxTotal; },
        get globalDiscount() {
            return this.discountType === 'percent'
                ? Math.min(this.beforeDiscount, this.beforeDiscount * Math.min(100, Number(this.discountValue || 0)) / 100)
                : Math.min(this.beforeDiscount, Number(this.discountValue || 0));
        },
        get shippingNumber() { return Math.max(0, Number(this.shipping || 0)); },
        get grandTotal() { return Math.max(0, this.beforeDiscount - this.globalDiscount + this.shippingNumber); },
        money(value) {
            return new Intl.NumberFormat('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0)) + ' MAD';
        }
    };
}

SoftNav.whenReady(function () {
    const select = $('#order_client_id');
    select.on('select2:select change', function (event) {
        const data = event.params?.data || select.select2('data')[0] || {};
        const summary = document.getElementById('selectedClientSummary');
        const parts = [data.name || data.text, data.phone, data.email, [data.address, data.city].filter(Boolean).join(', ')].filter(Boolean);
        summary.innerHTML = parts.map((part, index) =>
            index === 0 ? '<strong class="text-gray-900">' + escapeHtml(part) + '</strong>' : '<div>' + escapeHtml(part) + '</div>'
        ).join('');
    });
});

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value || '');
    return element.innerHTML;
}
</script>
@endsection
