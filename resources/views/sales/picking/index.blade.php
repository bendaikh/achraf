@extends('layouts.with-sidebar')

@section('title', 'Préparation des commandes / Picking')
@section('sidebar_page_title', 'Ventes')

@section('main')
<style>[x-cloak]{display:none!important}</style>
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-16">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-800 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="mb-4 p-3 rounded-lg bg-amber-50 text-amber-900 text-sm">{{ session('warning') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 text-sm">{{ session('error') }}</div>
        @endif

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Préparation des commandes / Picking</h1>
            <p class="text-sm text-slate-600 mt-1">
                Seules les quantités réservées sur le stock physique ({{ $belvedereName ?? 'Magasin Belvédère' }} / dépôts) apparaissent ici.
                Le stock Shopify en ligne n’est jamais utilisé. Manques → À approvisionner. Seule <strong>Valider sortie</strong> diminue le stock.
                Cochez les lignes produit disponibles (réservées) à sortir : les lignes non cochées ou non disponibles restent en attente / besoin d’achat (sortie partielle possible).
            </p>
            @unless($pickingEnabled)
                <p class="mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    Picking désactivé (Paramètres → Stock). Le workflow historique (sortie à la préparation commande) reste actif.
                </p>
            @else
                @if($pickingActivatedAt)
                    <p class="mt-2 text-sm text-slate-600">
                        File active depuis le {{ $pickingActivatedAt->format('d/m/Y H:i') }} —
                        les commandes créées/importées avant cette date restent dans l’historique commandes, hors picking.
                    </p>
                @endif
            @endunless
        </div>

        <form method="GET" class="mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 bg-white border border-slate-200 rounded-xl p-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Recherche (n° commande, client…)" class="rounded-lg border-slate-300 text-sm">
            <select name="source" class="rounded-lg border-slate-300 text-sm">
                <option value="">Tous les canaux</option>
                @foreach(['shopify' => 'Shopify', 'jumia' => 'Jumia', 'libromart' => 'Libromart'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border-slate-300 text-sm">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border-slate-300 text-sm">
            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Filtrer</button>
        </form>

        <form id="picking-actions" method="POST">
            @csrf
            <div class="mb-3 flex flex-wrap gap-2">
                <button type="submit" formaction="{{ route('sales.picking.pdf') }}" formtarget="_blank"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold">
                    Générer la préparation (PDF)
                </button>
                <button type="submit" formaction="{{ route('sales.picking.validate-exit') }}"
                        onclick="return confirm('Valider la sortie physique des lignes cochées ? Seules les quantités réservées des lignes cochées sont sorties : cette action diminue le stock de leur dépôt / emplacement.')"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold">
                    Valider sortie
                </button>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2"><input type="checkbox" title="Tout sélectionner" onclick="pickingSelectAll(this.checked)"></th>
                            <th class="px-3 py-2 w-8"></th>
                            <th class="px-3 py-2">Référence</th>
                            <th class="px-3 py-2">Canal</th>
                            <th class="px-3 py-2">Client</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Réservé</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    @forelse($orders as $order)
                        @php
                            $lines = $orderLines[$order->id] ?? [];
                            $reservedTotal = (int) ($reservationCounts[$order->id] ?? 0);
                        @endphp
                        <tbody x-data="{ open: {{ count($lines) > 1 ? 'true' : 'false' }} }" class="border-t border-slate-100">
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2">
                                    <input type="checkbox" class="pick-order" name="order_ids[]" value="{{ $order->id }}" data-order="{{ $order->id }}"
                                           title="Sélectionner toutes les lignes disponibles de la commande"
                                           onclick="pickingToggleOrder({{ $order->id }}, this.checked)">
                                </td>
                                <td class="px-1 py-2">
                                    <button type="button" @click="open = !open"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                            :aria-expanded="open.toString()"
                                            title="Afficher les produits">
                                        <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </td>
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ $order->ticket_number }}</td>
                                <td class="px-3 py-2">{{ $order->source ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $order->client?->name ?: '—' }}</td>
                                <td class="px-3 py-2">{{ optional($order->sold_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2 font-semibold tabular-nums">{{ $reservedTotal }}</td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ route('orders.show', $order) }}" class="text-[#0a5d8a] text-xs font-semibold hover:underline">Voir</a>
                                </td>
                            </tr>
                            <tr x-show="open" x-cloak>
                                <td colspan="8" class="px-3 py-3 bg-slate-50/70">
                                    @if(count($lines) === 0)
                                        <p class="text-xs text-slate-500">Aucun produit stocké sur cette commande.</p>
                                    @else
                                        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                            <table class="min-w-full text-xs">
                                                <thead class="bg-slate-100 text-left text-[11px] uppercase text-slate-500">
                                                    <tr>
                                                        <th class="px-3 py-2 w-8"></th>
                                                        <th class="px-3 py-2">Produit</th>
                                                        <th class="px-3 py-2">SKU</th>
                                                        <th class="px-3 py-2">Qté commandée</th>
                                                        <th class="px-3 py-2">Stock {{ $belvedereName ?? 'MAGASIN BELVEDERE' }}</th>
                                                        <th class="px-3 py-2">Emplacement</th>
                                                        <th class="px-3 py-2">Qté réservée</th>
                                                        <th class="px-3 py-2">Manquant</th>
                                                        <th class="px-3 py-2">Statut</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach($lines as $line)
                                                        @php
                                                            $lineStatus = $line['status'] ?? 'pending';
                                                            $canShip = (bool) ($line['can_ship'] ?? false);
                                                        @endphp
                                                        <tr class="{{ $canShip ? '' : 'bg-slate-50/60' }}">
                                                            <td class="px-3 py-2">
                                                                <input type="checkbox" class="pick-line" name="line_ids[]" value="{{ $line['item_id'] }}"
                                                                       data-order="{{ $order->id }}"
                                                                       @disabled(! $canShip)
                                                                       title="{{ $canShip ? 'Sortir cette ligne (quantité réservée)' : 'Non disponible : reste en attente / besoin d’achat' }}"
                                                                       onchange="pickingSyncOrder({{ $order->id }})">
                                                            </td>
                                                            <td class="px-3 py-2 font-medium text-slate-900">{{ $line['product'] }}</td>
                                                            <td class="px-3 py-2 font-mono text-slate-700">{{ $line['sku'] }}</td>
                                                            <td class="px-3 py-2 tabular-nums">Cmd {{ $line['ordered'] }}</td>
                                                            <td class="px-3 py-2 tabular-nums">Belvédère {{ $line['belvedere_stock'] }}</td>
                                                            <td class="px-3 py-2 font-mono">{{ $line['location'] }}</td>
                                                            <td class="px-3 py-2 tabular-nums text-emerald-800">Réservé {{ $line['reserved'] }}</td>
                                                            <td class="px-3 py-2 tabular-nums {{ $line['missing'] > 0 ? 'text-amber-800 font-semibold' : 'text-slate-600' }}">
                                                                Manquant {{ $line['missing'] }}
                                                            </td>
                                                            <td class="px-3 py-2">
                                                                @if($lineStatus === 'shipped')
                                                                    <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-700">Sortie validée</span>
                                                                @elseif($lineStatus === 'ready')
                                                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Disponible</span>
                                                                @elseif($lineStatus === 'partial')
                                                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Partiel ({{ $line['reserved'] }} dispo)</span>
                                                                @else
                                                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-800">En attente / Besoin d’achat</span>
                                                                @endif
                                                                @if(($line['shipped'] ?? 0) > 0 && $lineStatus !== 'shipped')
                                                                    <span class="block mt-1 text-[11px] text-slate-500">Déjà sorti {{ $line['shipped'] }}</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody>
                            <tr>
                                <td colspan="8" class="px-3 py-10 text-center text-slate-500">Aucune commande en attente de sortie.</td>
                            </tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        </form>

        <div class="mt-4">{{ $orders->links() }}</div>
    </div>
</main>
<script>
    function pickingLines(orderId) {
        return Array.from(document.querySelectorAll('.pick-line[data-order="' + orderId + '"]')).filter(c => !c.disabled);
    }
    function pickingToggleOrder(orderId, checked) {
        pickingLines(orderId).forEach(c => c.checked = checked);
        pickingSyncOrder(orderId);
    }
    function pickingSyncOrder(orderId) {
        const box = document.querySelector('.pick-order[data-order="' + orderId + '"]');
        if (!box) return;
        const lines = pickingLines(orderId);
        const checked = lines.filter(c => c.checked).length;
        box.checked = lines.length > 0 && checked === lines.length;
        box.indeterminate = checked > 0 && checked < lines.length;
    }
    function pickingSelectAll(checked) {
        document.querySelectorAll('.pick-order').forEach(c => pickingToggleOrder(c.dataset.order, checked));
    }
</script>
@endsection
