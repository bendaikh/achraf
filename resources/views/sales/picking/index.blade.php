@extends('layouts.with-sidebar')

@section('title', 'Préparation des commandes / Picking')
@section('sidebar_page_title', 'Ventes')

@section('main')
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
                Générer un picking ne sort aucun stock. Seule l’action <strong>Valider sortie</strong> diminue le stock physique.
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
                        onclick="return confirm('Valider la sortie physique des commandes sélectionnées ? Cette action diminue le stock.')"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold">
                    Valider sortie
                </button>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2"><input type="checkbox" onclick="document.querySelectorAll('.pick-order').forEach(c => c.checked = this.checked)"></th>
                            <th class="px-3 py-2">Référence</th>
                            <th class="px-3 py-2">Canal</th>
                            <th class="px-3 py-2">Client</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Réservé</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($orders as $order)
                            <tr>
                                <td class="px-3 py-2">
                                    <input type="checkbox" class="pick-order" name="order_ids[]" value="{{ $order->id }}">
                                </td>
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ $order->ticket_number }}</td>
                                <td class="px-3 py-2">{{ $order->source ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $order->client?->name ?: '—' }}</td>
                                <td class="px-3 py-2">{{ optional($order->sold_at)->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2">{{ (int) ($reservationCounts[$order->id] ?? 0) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ route('orders.show', $order) }}" class="text-[#0a5d8a] text-xs font-semibold hover:underline">Voir</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-10 text-center text-slate-500">Aucune commande en attente de sortie.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="mt-4">{{ $orders->links() }}</div>
    </div>
</main>
@endsection
