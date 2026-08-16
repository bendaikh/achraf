{{-- Async dashboard panel — shell stays in layout; data loads via JSON. --}}
@php
    $dataUrl = $dataUrl ?? route('dashboard.data');
    $bootstrap = $bootstrap ?? null;

    // Menu « + Nouveau » : uniquement des routes existantes. Lorsqu'aucune action
    // de création dédiée n'existe, on pointe vers l'écran métier concerné.
    $createMenu = [
        [
            'label' => 'VENTES',
            'items' => [
                ['label' => 'Commande / POS', 'url' => route('pos.index')],
                ['label' => 'Devis', 'url' => route('quotes.create')],
                ['label' => 'BC client', 'url' => route('purchase-orders.create')],
                ['label' => 'BL client', 'url' => route('delivery-notes.create')],
                ['label' => 'Facture', 'url' => route('invoices.create')],
                ['label' => 'Avoir', 'url' => route('credit-notes.create')],
                ['label' => 'Paiement client', 'url' => route('sales.payments.index')],
            ],
        ],
        [
            'label' => 'ACHATS',
            'items' => [
                ['label' => 'Dépense avec facture', 'url' => route('expenses-with-invoice.create')],
                ['label' => 'Dépense sans facture', 'url' => route('expenses-without-invoice.create')],
                ['label' => 'BC fournisseur', 'url' => route('supplier-purchase-orders.create')],
                ['label' => 'BL fournisseur', 'url' => route('supplier-delivery-notes.create')],
                ['label' => 'Bon de réception', 'url' => route('receptions.create')],
                ['label' => 'Facture fournisseur', 'url' => route('supplier-invoices.create')],
                ['label' => 'Avoir fournisseur', 'url' => route('supplier-credit-notes.create')],
                ['label' => 'Paiement fournisseur', 'url' => route('purchases.payments.index')],
            ],
        ],
        [
            'label' => 'PRODUITS / STOCK',
            'items' => [
                ['label' => 'Ajouter un produit', 'url' => route('products.create')],
                ['label' => 'Mouvements de stock', 'url' => route('stock.movements.index')],
                ['label' => 'Inventaire', 'url' => route('stock.inventory.index')],
                ['label' => 'Transfert', 'url' => route('stock.transfer.create')],
                ['label' => 'Stock faible', 'url' => route('products.index', ['stock_status' => 'low_stock'])],
                ['label' => 'Ruptures', 'url' => route('products.index', ['stock_status' => 'out_of_stock'])],
            ],
        ],
        [
            'label' => 'CRM',
            'items' => [
                ['label' => 'Client', 'url' => route('clients.create')],
                ['label' => 'Fournisseur', 'url' => route('suppliers.create')],
            ],
        ],
        [
            'label' => 'FINANCE',
            'items' => [
                ['label' => 'Encaissement', 'url' => route('financial.mouvements.create', ['type' => 'entree'])],
                ['label' => 'Décaissement', 'url' => route('financial.mouvements.create', ['type' => 'sortie'])],
                ['label' => 'Trésorerie', 'url' => route('financial.tresorerie')],
                ['label' => 'Créances', 'url' => route('sales.payments.index', ['payment_status' => 'open'])],
                ['label' => 'Dettes', 'url' => route('purchases.payments.index', ['payment_status' => 'open'])],
                ['label' => 'Rapprochement', 'url' => route('financial.mouvements.reconcile')],
            ],
        ],
    ];

    $quickPeriods = [
        'month' => 'Ce mois',
        'previous_month' => 'Mois dernier',
        'quarter' => 'Trimestre',
        'year' => 'Année',
        'custom' => 'Personnalisée',
    ];
@endphp
<main
    class="flex-1 w-full min-w-0 bg-gray-50"
    x-data="dashboardPage(@js($dataUrl), @js($bootstrap))"
    x-init="init()"
    x-cloak
>
    {{-- 1. En-tête : période, dates, actualiser, + Nouveau --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-20">
        <div class="px-4 sm:px-6 py-3 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center justify-between gap-3 xl:contents">
                <div class="min-w-0 xl:order-1">
                    <p class="text-sm text-gray-600 truncate">
                        <span class="font-medium text-gray-800">Vue globale de l'activité</span>
                        <span class="text-gray-300 mx-1">•</span>
                        <span x-text="periodLabel"></span>
                        <span class="text-gray-300 mx-1">•</span>
                        <span x-text="todayLabel"></span>
                    </p>
                </div>

                <div class="relative shrink-0 xl:order-3" x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button
                        type="button"
                        @click="open = !open"
                        @click.outside="open = false"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-[#fdb819] text-[#083c58] rounded-lg text-xs font-bold hover:bg-[#e5a617] transition"
                        :aria-expanded="open"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Nouveau
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div
                        x-show="open"
                        x-transition.opacity
                        x-cloak
                        class="absolute right-0 mt-2 w-[min(92vw,58rem)] rounded-xl border border-gray-200 bg-white shadow-xl p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 z-30"
                    >
                        @foreach ($createMenu as $group)
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">{{ $group['label'] }}</p>
                                <ul class="space-y-0.5">
                                    @foreach ($group['items'] as $item)
                                        <li>
                                            <a href="{{ $item['url'] }}" class="block px-2 py-1.5 rounded-md text-xs text-gray-700 hover:bg-gray-100 hover:text-[#0a5d8a] transition">{{ $item['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-2 xl:order-2">
                <div class="flex flex-wrap items-center gap-1 rounded-lg bg-gray-100 p-1">
                    @foreach ($quickPeriods as $key => $label)
                        <button
                            type="button"
                            @click="selectPeriod('{{ $key }}')"
                            class="px-2.5 py-1 text-xs font-medium rounded-md transition"
                            :class="period === '{{ $key }}' ? 'bg-white text-[#0a5d8a] shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                <form @submit.prevent="applyFilter()" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Du</label>
                        <input type="date" x-model="dateFrom" @change="period = 'custom'" class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Au</label>
                        <input type="date" x-model="dateTo" @change="period = 'custom'" class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs">
                    </div>
                    <button
                        type="submit"
                        class="px-3 py-1.5 bg-[#0a5d8a] text-white rounded-lg text-xs font-semibold hover:bg-[#084a6e] disabled:opacity-60"
                        :disabled="loading"
                    >Filtrer</button>
                </form>

                <button
                    type="button"
                    @click="refresh()"
                    :disabled="loading"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                    title="Actualiser les données"
                >
                    <svg class="h-3.5 w-3.5" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.303 0a8.25 8.25 0 0013.803 3.7l3.181-3.182m0-11.667a8.25 8.25 0 00-13.803-3.7L2.985 9.348" />
                    </svg>
                    Actualiser
                </button>
            </div>
        </div>
    </header>

    <div class="p-4 sm:p-6 space-y-5 relative">
        <div
            x-show="loading"
            x-transition.opacity
            class="absolute inset-0 z-10 bg-gray-50/70 flex items-start justify-center pt-20"
        >
            <div class="flex items-center gap-3 rounded-xl bg-white border border-gray-200 shadow-sm px-4 py-3 text-sm text-gray-600">
                <svg class="h-5 w-5 animate-spin text-[#0a5d8a]" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Chargement des données…
            </div>
        </div>

        <div x-show="error" x-cloak class="bg-red-50 border-l-4 border-red-500 p-3 rounded-lg">
            <p class="text-sm text-red-700" x-text="error"></p>
        </div>

        {{-- 2. 8 KPI financiers cliquables --}}
        <section aria-label="Indicateurs financiers">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <template x-for="kpi in kpis" :key="kpi.key">
                    <a
                        :href="kpi.url"
                        class="group bg-white rounded-xl border border-gray-200 p-3.5 shadow-sm hover:shadow-md hover:border-[#0a5d8a]/40 transition flex flex-col justify-between"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 leading-tight" x-text="kpi.label"></p>
                            <span class="shrink-0 rounded-lg p-1.5" :class="toneBg(kpi.tone)">
                                <svg class="h-4 w-4" :class="toneText(kpi.tone)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(kpi.key)" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-2 text-lg xl:text-xl font-bold tabular-nums" :class="kpi.key === 'result' ? (kpi.value >= 0 ? 'text-emerald-600' : 'text-red-600') : 'text-gray-900'" x-text="kpi.format === 'count' ? number(kpi.value) : money(kpi.value)"></p>
                        <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                            <template x-if="kpi.variation !== null && kpi.variation !== undefined">
                                <span
                                    class="inline-flex items-center gap-0.5 text-[11px] font-semibold rounded-full px-1.5 py-0.5"
                                    :class="kpi.variation >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                                    x-text="signedPercent(kpi.variation)"
                                ></span>
                            </template>
                            <span class="text-[11px] text-gray-400 truncate" x-text="kpi.format === 'count' && kpi.secondary !== undefined ? `${kpi.hint} : ${money(kpi.secondary)}` : kpi.hint"></span>
                        </div>
                    </a>
                </template>
            </div>
            <p class="mt-1.5 text-[11px] text-gray-400">
                Variations comparées à la période précédente de même durée (<span x-text="previousPeriodLabel"></span>).
            </p>
        </section>

        {{-- 3. À traiter aujourd'hui --}}
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm" aria-label="À traiter aujourd'hui">
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    À traiter aujourd'hui
                </h3>
                <span class="text-[11px] text-gray-500"><span class="font-semibold text-gray-900" x-text="number(todo.total)"></span> élément(s)</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y divide-gray-100">
                <template x-for="item in (todo.items || [])" :key="item.key">
                    <a :href="item.url" class="p-3 hover:bg-gray-50 transition block">
                        <div class="flex items-baseline gap-2">
                            <span class="text-xl font-bold tabular-nums" :class="item.count > 0 ? toneText(item.tone) : 'text-gray-300'" x-text="number(item.count)"></span>
                            <template x-if="item.amount">
                                <span class="text-[11px] font-medium text-gray-500" x-text="money(item.amount)"></span>
                            </template>
                        </div>
                        <p class="text-[11px] text-gray-600 mt-0.5 leading-snug" x-text="item.label"></p>
                        <template x-if="item.note">
                            <p class="text-[10px] text-gray-400 mt-0.5 leading-snug" x-text="item.note"></p>
                        </template>
                    </a>
                </template>
            </div>
        </section>

        {{-- 4. Graphique financier + canaux + modes de paiement --}}
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3" aria-label="Analyse financière">
            <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h3 class="text-sm font-bold text-gray-900">Évolution financière</h3>
                    <div class="flex items-center gap-1 rounded-lg bg-gray-100 p-0.5">
                        <template x-for="option in chartPeriods" :key="option.value">
                            <button
                                type="button"
                                @click="selectChartPeriod(option.value)"
                                class="px-2 py-1 text-[11px] font-medium rounded-md transition"
                                :class="chartPeriod === option.value ? 'bg-white text-[#0a5d8a] shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                x-text="option.label"
                            ></button>
                        </template>
                    </div>
                </div>
                <div class="h-64 sm:h-72">
                    <canvas x-ref="financialCanvas"></canvas>
                </div>
            </div>

            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-bold text-gray-900">Canaux de vente</h3>
                        <span class="text-[11px] text-gray-500" x-text="money(channels.total)"></span>
                    </div>
                    <ul class="space-y-2">
                        <template x-if="!channelItems.length">
                            <li class="text-xs text-gray-400 py-2">Aucune vente sur la période</li>
                        </template>
                        <template x-for="channel in channelItems" :key="channel.key">
                            <li>
                                <a :href="channel.url" class="block group">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-medium text-gray-700 group-hover:text-[#0a5d8a]" x-text="channel.label"></span>
                                        <span class="tabular-nums text-gray-900 font-semibold" x-text="money(channel.amount)"></span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-full" :class="channelBar(channel.key)" :style="`width: ${Math.min(100, channel.share)}%`"></div>
                                        </div>
                                        <span class="text-[11px] text-gray-500 w-10 text-right tabular-nums" x-text="percent(channel.share)"></span>
                                    </div>
                                </a>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-bold text-gray-900">Modes de paiement</h3>
                        <span class="text-[11px] text-gray-500" x-text="money(paymentMethods.total)"></span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 items-center">
                        <div class="h-32 relative">
                            <canvas x-ref="paymentCanvas"></canvas>
                            <template x-if="!paymentItems.length">
                                <p class="absolute inset-0 flex items-center justify-center text-center text-[11px] text-gray-400">Aucun encaissement</p>
                            </template>
                        </div>
                        <ul class="space-y-1">
                            <template x-if="!paymentItems.length">
                                <li class="text-[11px] text-gray-400">Aucun mode de paiement sur la période</li>
                            </template>
                            <template x-for="method in paymentItems" :key="method.key">
                                <li class="flex items-center justify-between gap-1 text-[11px]">
                                    <span class="flex items-center gap-1.5 min-w-0">
                                        <span class="h-2 w-2 rounded-full shrink-0" :style="`background:${methodColor(method.key)}`"></span>
                                        <span class="text-gray-600 truncate" x-text="method.label"></span>
                                    </span>
                                    <span class="text-right shrink-0">
                                        <span class="font-semibold text-gray-900 tabular-nums" x-text="money(method.amount)"></span>
                                        <span class="text-gray-400 tabular-nums ml-1" x-text="percent(method.share)"></span>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>
                    <p class="mt-2 text-[10px] text-gray-400">Paiements de factures + ventes POS non facturées.</p>
                </div>
            </div>
        </section>

        {{-- 5. Activité commerciale --}}
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm" aria-label="Activité commerciale">
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-gray-900">Activité commerciale</h3>
                <a :href="activity.orders_url" class="text-[11px] font-medium text-[#0a5d8a] hover:underline">Voir toutes les commandes</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 divide-x divide-y xl:divide-y-0 divide-gray-100">
                <template x-for="item in (activity.items || [])" :key="item.key">
                    <a :href="item.url" class="px-3 py-2.5 hover:bg-gray-50 transition block">
                        <p class="text-lg font-bold text-gray-900 tabular-nums" x-text="item.format === 'money' ? money(item.value) : (item.format === 'percent' ? percent(item.value) : number(item.value))"></p>
                        <p class="text-[11px] text-gray-500 leading-snug" x-text="item.label"></p>
                    </a>
                </template>
            </div>
        </section>

        {{-- 6. Stock & Produits --}}
        <section class="grid grid-cols-1 gap-4 lg:grid-cols-3" aria-label="Stock et produits">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-900">Stock &amp; produits</h3>
                    <a :href="stock.urls?.stock" class="text-[11px] font-medium text-[#0a5d8a] hover:underline">Gérer</a>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <a :href="stock.urls?.all" class="rounded-lg border border-gray-100 p-2.5 hover:bg-gray-50 transition">
                        <p class="text-lg font-bold text-gray-900 tabular-nums" x-text="number(stock.total)"></p>
                        <p class="text-[11px] text-gray-500">Références</p>
                    </a>
                    <a :href="stock.urls?.in_stock" class="rounded-lg border border-gray-100 p-2.5 hover:bg-gray-50 transition">
                        <p class="text-lg font-bold text-emerald-600 tabular-nums" x-text="number(stock.in_stock)"></p>
                        <p class="text-[11px] text-gray-500">En stock</p>
                    </a>
                    <a :href="stock.urls?.stocked" class="rounded-lg border border-gray-100 p-2.5 hover:bg-gray-50 transition">
                        <p class="text-lg font-bold text-gray-900 tabular-nums" x-text="number(stock.stocked)"></p>
                        <p class="text-[11px] text-gray-500">Produits stockés</p>
                    </a>
                    <a :href="stock.urls?.non_stocked" class="rounded-lg border border-gray-100 p-2.5 hover:bg-gray-50 transition">
                        <p class="text-lg font-bold text-gray-900 tabular-nums" x-text="number(stock.non_stocked)"></p>
                        <p class="text-[11px] text-gray-500">Non stockés</p>
                    </a>
                    <a :href="stock.urls?.services" class="rounded-lg border border-gray-100 p-2.5 hover:bg-gray-50 transition">
                        <p class="text-lg font-bold text-gray-900 tabular-nums" x-text="number(stock.services)"></p>
                        <p class="text-[11px] text-gray-500">Services</p>
                    </a>
                    <a :href="stock.urls?.low_stock" class="rounded-lg border border-gray-100 p-2.5 hover:bg-gray-50 transition">
                        <p class="text-lg font-bold text-amber-600 tabular-nums" x-text="number(stock.low_stock)"></p>
                        <p class="text-[11px] text-gray-500">Stock faible</p>
                    </a>
                    <a :href="stock.urls?.out_of_stock" class="rounded-lg border border-gray-100 p-2.5 hover:bg-gray-50 transition">
                        <p class="text-lg font-bold text-red-600 tabular-nums" x-text="number(stock.out_of_stock)"></p>
                        <p class="text-[11px] text-gray-500">Rupture</p>
                    </a>
                </div>
                <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2 flex items-center justify-between">
                    <span class="text-[11px] text-gray-500">Valeur du stock (prix d'achat HT)</span>
                    <span class="text-sm font-bold text-gray-900 tabular-nums" x-text="money(stock.stock_value)"></span>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900">Produits à réapprovisionner</h3>
                    <a :href="stock.urls?.low_stock" class="text-[11px] font-medium text-[#0a5d8a] hover:underline">Voir tout</a>
                </div>
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">Produit</th>
                            <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">Réf.</th>
                            <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">Fournisseur</th>
                            <th class="px-4 py-2 text-right font-semibold uppercase tracking-wide">Dispo.</th>
                            <th class="px-4 py-2 text-right font-semibold uppercase tracking-wide">Seuil</th>
                            <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">État</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-if="!(stock.restock || []).length">
                            <tr><td colspan="6" class="px-4 py-5 text-center text-gray-400">Aucun produit sous le seuil d'alerte</td></tr>
                        </template>
                        <template x-for="product in (stock.restock || [])" :key="product.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">
                                    <a :href="product.url" class="font-medium text-[#0a5d8a] hover:underline" x-text="product.name"></a>
                                </td>
                                <td class="px-4 py-2 text-gray-500" x-text="product.ref || '—'"></td>
                                <td class="px-4 py-2 text-gray-500" x-text="product.primary_supplier || '—'"></td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold" :class="product.status === 'out_of_stock' ? 'text-red-600' : 'text-amber-600'" x-text="number(product.available)"></td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-500" x-text="number(product.threshold)"></td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-semibold" :class="product.status === 'out_of_stock' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'" x-text="product.status_label"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 7. Trésorerie --}}
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm" aria-label="Trésorerie">
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-gray-900">Trésorerie</h3>
                <a :href="treasury.urls?.overview" class="text-[11px] font-medium text-[#0a5d8a] hover:underline">Journal des mouvements</a>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-5 divide-x divide-y lg:divide-y-0 divide-gray-100">
                <a :href="treasury.urls?.caisse" class="px-4 py-3 hover:bg-gray-50 transition">
                    <p class="text-[11px] text-gray-500">Solde caisse</p>
                    <p class="text-base font-bold text-gray-900 tabular-nums" x-text="money(treasury.caisse)"></p>
                </a>
                <a :href="treasury.urls?.banque" class="px-4 py-3 hover:bg-gray-50 transition">
                    <p class="text-[11px] text-gray-500">Solde banque</p>
                    <p class="text-base font-bold text-gray-900 tabular-nums" x-text="money(treasury.banque)"></p>
                </a>
                <a :href="treasury.urls?.in" class="px-4 py-3 hover:bg-gray-50 transition">
                    <p class="text-[11px] text-gray-500">Entrées (période)</p>
                    <p class="text-base font-bold text-emerald-600 tabular-nums" x-text="money(treasury.in)"></p>
                </a>
                <a :href="treasury.urls?.out" class="px-4 py-3 hover:bg-gray-50 transition">
                    <p class="text-[11px] text-gray-500">Sorties (période)</p>
                    <p class="text-base font-bold text-red-600 tabular-nums" x-text="money(treasury.out)"></p>
                </a>
                <a :href="treasury.urls?.overview" class="px-4 py-3 hover:bg-gray-50 transition">
                    <p class="text-[11px] text-gray-500">Disponible</p>
                    <p class="text-base font-bold tabular-nums" :class="(treasury.total ?? 0) >= 0 ? 'text-[#0a5d8a]' : 'text-red-600'" x-text="money(treasury.total)"></p>
                </a>
            </div>
            <template x-if="treasury.available === false">
                <p class="px-4 py-2 text-[11px] text-gray-400 border-t border-gray-100">Journal des mouvements financiers indisponible : soldes à 0.</p>
            </template>
        </section>

        {{-- 8. Créances + dettes --}}
        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2" aria-label="Créances et dettes">
            <template x-for="block in balanceBlocks" :key="block.key">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900" x-text="block.title"></h3>
                            <p class="text-[11px] text-gray-500">
                                <span x-text="number(block.data.count)"></span> facture(s) ·
                                <span class="font-semibold" :class="block.tone" x-text="money(block.data.total)"></span> restant dû
                            </p>
                        </div>
                        <a :href="block.data.url" class="text-[11px] font-medium text-[#0a5d8a] hover:underline shrink-0">Voir tout</a>
                    </div>
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">N°</th>
                                <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide" x-text="block.partyLabel"></th>
                                <th class="px-4 py-2 text-right font-semibold uppercase tracking-wide">Restant</th>
                                <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">Échéance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-if="!(block.data.items || []).length">
                                <tr><td colspan="4" class="px-4 py-5 text-center text-gray-400" x-text="block.emptyLabel"></td></tr>
                            </template>
                            <template x-for="row in (block.data.items || [])" :key="block.key + row.number">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <a :href="row.url" class="font-medium text-[#0a5d8a] hover:underline" x-text="row.number"></a>
                                        <span class="ml-1 inline-flex px-1 py-0.5 rounded text-[10px] font-semibold" :class="row.status === 'partial' ? 'bg-sky-50 text-sky-700' : 'bg-gray-100 text-gray-600'" x-text="row.status === 'partial' ? 'Partiel' : 'Impayé'"></span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700 truncate max-w-[10rem]" x-text="row.party"></td>
                                    <td class="px-4 py-2 text-right tabular-nums font-semibold text-gray-900" x-text="money(row.remaining)"></td>
                                    <td class="px-4 py-2" :class="row.overdue ? 'text-red-600 font-medium' : 'text-gray-500'" x-text="row.due_date || '—'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </section>

        {{-- 9. Dernières opérations --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Dernières opérations">
            <template x-for="list in recentLists" :key="list.key">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900" x-text="list.title"></h3>
                        <a :href="list.data.url" class="text-[11px] font-medium text-[#0a5d8a] hover:underline">Voir tout</a>
                    </div>
                    <ul class="divide-y divide-gray-100 flex-1">
                        <template x-if="!(list.data.items || []).length">
                            <li class="px-4 py-5 text-center text-xs text-gray-400" x-text="list.emptyLabel"></li>
                        </template>
                        <template x-for="(row, index) in (list.data.items || [])" :key="list.key + index">
                            <li>
                                <a :href="row.url" class="flex items-center justify-between gap-2 px-4 py-2 hover:bg-gray-50 transition">
                                    <span class="min-w-0">
                                        <span class="block text-xs font-medium text-gray-900 truncate" x-text="row.reference || '—'"></span>
                                        <span class="block text-[11px] text-gray-500 truncate" x-text="row.party"></span>
                                    </span>
                                    <span class="text-right shrink-0">
                                        <span class="block text-xs font-semibold tabular-nums" :class="list.amountTone" x-text="money(row.amount)"></span>
                                        <span class="block text-[10px] text-gray-400" x-text="row.date || '—'"></span>
                                    </span>
                                </a>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </section>
    </div>
</main>
