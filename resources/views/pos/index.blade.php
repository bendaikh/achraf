@extends('layouts.app')

@section('title', 'Point de vente')

@section('content')
<div
    class="bg-slate-900 flex text-slate-100"
    :class="posFullView ? 'fixed inset-0 z-50 min-h-screen' : 'min-h-screen relative'"
    x-data="posRegister(@js($productsForJs))"
    @keydown.escape.window="onGlobalEscape()"
    x-cloak
>
    <aside x-show="!posFullView" x-transition.opacity.duration.200ms class="w-64 bg-white shadow-lg fixed h-full overflow-y-auto z-20 border-r border-slate-200">
        @include('layouts.sidebar')
    </aside>

    <main class="flex-1 flex flex-col min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 transition-[margin] duration-200" :class="posFullView ? 'ml-0 w-full' : 'ml-64'">
        <button type="button" x-show="posFullView" x-transition @click="posFullView = false" class="fixed top-3 right-3 z-[60] inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800/95 text-white text-sm font-semibold border border-white/15 shadow-lg hover:bg-slate-700 backdrop-blur">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
            Quitter l’aperçu
        </button>

        <header class="shrink-0 border-b border-white/10 bg-slate-900/80 backdrop-blur px-6 py-4 flex flex-wrap items-center justify-between gap-4" :class="posFullView ? 'pr-44' : ''">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Point de vente</h1>
                <p class="text-sm text-emerald-200/80 mt-0.5">
                    <span x-show="!posFullView">Caisse · encaissement · tickets</span>
                    <span x-show="posFullView" class="text-emerald-300/90">Aperçu plein écran — catalogue et panier sur toute la largeur</span>
                </p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" x-show="!posFullView" @click="posFullView = true" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600/90 text-white text-sm font-semibold hover:bg-emerald-500 border border-emerald-400/30 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    Aperçu plein écran
                </button>
                <a href="{{ route('pos.sales.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 text-white text-sm font-medium hover:bg-white/15 border border-white/10 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Historique
                </a>
            </div>
        </header>

        @if(session('error'))
            <div class="mx-6 mt-4 rounded-lg bg-red-500/20 border border-red-400/40 px-4 py-3 text-sm text-red-100">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('pos.checkout') }}" class="flex-1 flex flex-col lg:flex-row min-h-0" @submit="if (cart.length === 0) { $event.preventDefault(); alert('Panier vide.'); }">
            @csrf
            <input type="hidden" name="client_id" :value="clientId || ''">

            <section class="flex-1 flex flex-col min-h-0 p-4 lg:p-6 border-b lg:border-b-0 lg:border-r border-white/10">
                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    <div class="flex-1 relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" x-model="searchQuery" @input.debounce.300ms="runSearch()" placeholder="Rechercher un produit (nom, réf.)…" class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-800/80 border border-white/10 text-white placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition">
                    </div>
                    <div class="sm:w-52 flex gap-2">
                        <input type="text" x-model="barcode" @keydown.enter.prevent="scanBarcode()" placeholder="Code-barres" class="flex-1 px-3 py-3 rounded-xl bg-slate-800/80 border border-white/10 text-white placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 outline-none text-sm">
                        <button type="button" @click="scanBarcode()" class="px-4 py-3 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-500 transition">OK</button>
                    </div>
                </div>

                <div x-show="searchResults.length > 0 && searchQuery.trim()" x-transition class="mb-4 rounded-xl border border-emerald-500/30 bg-slate-800/60 divide-y divide-white/5 max-h-48 overflow-y-auto">
                    <template x-for="p in searchResults" :key="p.id">
                        <button type="button" @click="addProduct(p); searchQuery = ''; searchResults = []" class="w-full text-left px-4 py-3 hover:bg-emerald-600/20 flex justify-between gap-2">
                            <span class="font-medium text-white" x-text="p.name"></span>
                            <span class="text-emerald-300 text-sm whitespace-nowrap"><span x-text="formatMoney(p.sale_price)"></span> DH</span>
                        </button>
                    </template>
                </div>

                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Catalogue</h2>
                <div
                    class="flex-1 overflow-y-auto grid gap-3 pb-4"
                    :class="posFullView ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4' : 'grid-cols-2 md:grid-cols-3 xl:grid-cols-4'"
                >
                    @foreach($products as $p)
                        @php
                            $pj = $productsForJs->firstWhere('id', $p->id);
                        @endphp
                        @if($pj)
                        <button type="button" @click='addProduct(@json($pj))' class="group text-left rounded-xl border border-white/10 bg-slate-800/40 p-4 hover:border-emerald-400/50 hover:bg-slate-800/80 transition">
                            <p class="font-medium text-white line-clamp-2 group-hover:text-emerald-200">{{ $p->name }}</p>
                            <p class="text-xs text-slate-500 mt-1 font-mono">{{ $p->ref }}</p>
                            <p class="text-lg font-bold text-emerald-400 mt-2">{{ number_format((float)($p->sale_price ?? 0), 2) }} <span class="text-sm font-normal text-slate-400">DH</span></p>
                        </button>
                        @endif
                    @endforeach
                </div>
            </section>

            <aside class="w-full shrink-0 flex flex-col bg-slate-950/50 backdrop-blur border-t lg:border-t-0 lg:border-l border-white/10" :class="posFullView ? 'lg:w-[min(32rem,38vw)] xl:w-[min(36rem,34vw)]' : 'lg:w-[420px]'">
                <div class="p-4 border-b border-white/10">
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Client (optionnel)</label>
                    <select x-model="clientId" class="w-full rounded-lg bg-slate-800 border border-white/10 text-white text-sm py-2.5 px-3 focus:ring-2 focus:ring-emerald-500 outline-none">
                        <option value="">Client comptoir</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-2 min-h-[200px]">
                    <template x-if="cart.length === 0">
                        <p class="text-center text-slate-500 text-sm py-12">Ajoutez des produits au panier</p>
                    </template>
                    <template x-for="(line, idx) in cart" :key="line.key">
                        <div class="rounded-lg bg-slate-800/80 border border-white/5 p-3 space-y-2">
                            <div class="flex justify-between gap-2">
                                <span class="font-medium text-white text-sm leading-tight" x-text="line.name"></span>
                                <button type="button" @click="removeLine(idx)" class="text-slate-500 hover:text-red-400 p-1" aria-label="Retirer">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <label class="text-slate-500 col-span-1">Qté</label>
                                <label class="text-slate-500 col-span-1">P.U.</label>
                                <label class="text-slate-500 col-span-1">Rem.</label>
                                <input type="number" min="1" class="col-span-1 rounded bg-slate-900 border border-white/10 text-white px-2 py-1" x-model.number="line.quantity">
                                <input type="number" step="0.01" min="0" class="col-span-1 rounded bg-slate-900 border border-white/10 text-white px-2 py-1" x-model.number="line.unit_price">
                                <input type="number" step="0.01" min="0" class="col-span-1 rounded bg-slate-900 border border-white/10 text-white px-2 py-1" x-model.number="line.discount">
                            </div>
                            <p class="text-right text-emerald-300 text-sm font-semibold">Ligne : <span x-text="formatMoney(lineTotal(line))"></span> DH</p>
                        </div>
                    </template>
                </div>

                <template x-for="(line, idx) in cart" :key="'h-'+line.key">
                    <div class="hidden">
                        <input type="hidden" :name="'items['+idx+'][product_id]'" :value="line.product_id">
                        <input type="hidden" :name="'items['+idx+'][quantity]'" :value="line.quantity">
                        <input type="hidden" :name="'items['+idx+'][unit_price]'" :value="line.unit_price">
                        <input type="hidden" :name="'items['+idx+'][tax_rate]'" :value="line.tax_rate">
                        <input type="hidden" :name="'items['+idx+'][discount]'" :value="line.discount">
                    </div>
                </template>

                <div class="p-4 border-t border-white/10 space-y-3 bg-slate-900/90">
                    <div class="flex justify-between text-sm text-slate-400">
                        <span>Total HT (lignes)</span>
                        <span x-text="formatMoney(subtotalHt())"></span>
                    </div>
                    <div class="flex justify-between text-sm text-slate-400">
                        <span>TVA</span>
                        <span x-text="formatMoney(taxTotal())"></span>
                    </div>
                    <div class="flex justify-between text-sm text-slate-400">
                        <span>Remise globale (DH)</span>
                        <input type="number" step="0.01" min="0" name="global_discount" x-model.number="globalDiscount" class="w-24 rounded bg-slate-800 border border-white/10 text-white text-right px-2 py-1 text-sm">
                    </div>
                    <div class="flex justify-between text-lg font-bold text-white pt-2 border-t border-white/10">
                        <span>Total TTC</span>
                        <span class="text-emerald-400"><span x-text="formatMoney(totalTtc())"></span> DH</span>
                    </div>
                    <button type="button" @click="openPayment()" :disabled="cart.length === 0" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-bold text-base shadow-lg shadow-emerald-900/40 hover:from-emerald-400 hover:to-teal-500 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        Paiement
                    </button>
                </div>
            </aside>

            <div x-show="paymentOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" style="display: none;">
                <div @click.away="paymentOpen = false" class="w-full max-w-md rounded-2xl bg-slate-900 border border-white/10 shadow-2xl p-6 space-y-5">
                    <h3 class="text-xl font-bold text-white">Encaissement</h3>
                    <p class="text-sm text-slate-400">Total à payer : <span class="text-emerald-400 font-bold text-lg" x-text="formatMoney(totalTtc()) + ' DH'"></span></p>

                    <div class="space-y-2">
                        <span class="text-xs font-semibold text-slate-500 uppercase">Mode de paiement</span>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($paymentMethods as $value => $label)
                                <label class="flex items-center gap-2 rounded-lg border px-3 py-2 cursor-pointer transition" :class="paymentMethod === '{{ $value }}' ? 'border-emerald-500 bg-emerald-500/10' : 'border-white/10 bg-slate-800/50'">
                                    <input type="radio" name="payment_method" value="{{ $value }}" x-model="paymentMethod" class="text-emerald-500 focus:ring-emerald-500">
                                    <span class="text-sm text-white">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <template x-if="paymentMethod === 'cash'">
                        <div class="space-y-2">
                            <label class="text-xs font-semibold text-slate-500 uppercase">Montant reçu (DH)</label>
                            <input type="number" step="0.01" min="0" name="amount_received" x-model="amountReceived" class="w-full rounded-lg bg-slate-800 border border-white/10 text-white px-4 py-3 text-lg font-mono focus:ring-2 focus:ring-emerald-500 outline-none">
                            <p class="text-sm text-slate-400">Monnaie : <span class="text-emerald-300 font-semibold" x-text="formatMoney(changeDue()) + ' DH'"></span></p>
                        </div>
                    </template>
                    <template x-if="paymentMethod !== 'cash'">
                        <input type="hidden" name="amount_received" :value="totalTtc()">
                    </template>

                    <div>
                        <label class="text-xs font-semibold text-slate-500 uppercase">Note (optionnel)</label>
                        <textarea name="notes" rows="2" x-model="notes" class="mt-1 w-full rounded-lg bg-slate-800 border border-white/10 text-white text-sm px-3 py-2 focus:ring-2 focus:ring-emerald-500 outline-none resize-none" placeholder="Réf. chèque, commentaire…"></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="paymentOpen = false" class="flex-1 py-3 rounded-xl border border-white/20 text-slate-300 font-medium hover:bg-white/5">Annuler</button>
                        <button type="submit" @click="if (paymentMethod === 'cash' && parseFloat(amountReceived) < totalTtc()) { $event.preventDefault(); alert('Montant insuffisant.'); }" class="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-500">Valider la vente</button>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<style>[x-cloak]{display:none!important}</style>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function posRegister(initialCatalog) {
    return {
        catalog: initialCatalog,
        posFullView: false,
        cart: [],
        searchQuery: '',
        searchResults: [],
        barcode: '',
        clientId: '',
        paymentOpen: false,
        paymentMethod: 'cash',
        amountReceived: '',
        globalDiscount: 0,
        notes: '',
        addProduct(p) {
            const existing = this.cart.find(c => c.product_id === p.id);
            if (existing) {
                existing.quantity += 1;
                return;
            }
            this.cart.push({
                key: crypto.randomUUID(),
                product_id: p.id,
                name: p.name,
                ref: p.ref,
                unit_price: Number(p.sale_price) || 0,
                tax_rate: Number(p.tax_rate) || 20,
                quantity: 1,
                discount: 0,
            });
        },
        removeLine(i) { this.cart.splice(i, 1); },
        lineBase(line) {
            const q = Number(line.quantity) || 0;
            const u = Number(line.unit_price) || 0;
            const d = Number(line.discount) || 0;
            return Math.max(0, q * u - d);
        },
        lineTax(line) {
            const tr = Number(line.tax_rate) || 0;
            return this.lineBase(line) * (tr / 100);
        },
        lineTotal(line) {
            return this.lineBase(line) + this.lineTax(line);
        },
        subtotalHt() {
            return this.cart.reduce((s, l) => s + this.lineBase(l), 0);
        },
        taxTotal() {
            return this.cart.reduce((s, l) => s + this.lineTax(l), 0);
        },
        totalTtc() {
            const gd = Number(this.globalDiscount) || 0;
            return Math.max(0, this.subtotalHt() + this.taxTotal() - gd);
        },
        formatMoney(n) {
            return (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
        },
        changeDue() {
            const rec = parseFloat(this.amountReceived);
            if (isNaN(rec)) return 0;
            return Math.max(0, rec - this.totalTtc());
        },
        onGlobalEscape() {
            if (this.paymentOpen) {
                this.paymentOpen = false;
                return;
            }
            if (this.posFullView) {
                this.posFullView = false;
            }
        },
        openPayment() {
            this.paymentOpen = true;
            this.amountReceived = this.formatMoney(this.totalTtc());
        },
        async runSearch() {
            const q = this.searchQuery.trim();
            if (!q) { this.searchResults = []; return; }
            try {
                const r = await fetch('{{ route('pos.products.search') }}?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                this.searchResults = data.products || [];
            } catch (e) { this.searchResults = []; }
        },
        async scanBarcode() {
            const b = this.barcode.trim();
            if (!b) return;
            try {
                const r = await fetch('{{ route('pos.products.search') }}?barcode=' + encodeURIComponent(b), { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                if (data.products && data.products.length) {
                    this.addProduct(data.products[0]);
                }
            } catch (e) {}
            this.barcode = '';
        },
    };
}
</script>
@endsection
