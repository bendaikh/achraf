@extends('layouts.with-sidebar')

@section('title', 'Retours clients / Scan retours')
@section('sidebar_page_title', 'Ventes')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-16">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-800 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 text-sm">{{ session('error') }}</div>
        @endif

        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Retours clients / Scan retours</h1>
                <p class="text-sm text-slate-600 mt-1">
                    Un statut transporteur « Retourné » ne remet jamais automatiquement le stock.
                    Workflow : Scan → Contrôle → Brouillon → <strong>Valider retour</strong>.
                </p>
            </div>
            @if($enabled)
                <a href="{{ route('sales.returns.scan') }}" class="px-4 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Nouveau scan</a>
            @endif
        </div>

        @unless($enabled)
            <div class="mb-6 p-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-sm">
                Fonctionnalité désactivée. Activez-la dans <strong>Paramètres → Stock &amp; Logistique → Activer Retours clients / Scan retours</strong>.
                Le workflow historique (avoirs / remboursements) reste disponible.
            </div>
        @endunless

        <form method="GET" class="mb-4 flex flex-wrap gap-2 bg-white border border-slate-200 rounded-xl p-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Référence, tracking, commande…" class="flex-1 min-w-[12rem] rounded-lg border-slate-300 text-sm">
            <select name="status" class="rounded-lg border-slate-300 text-sm">
                <option value="">Tous statuts</option>
                @foreach(['draft' => 'Brouillon', 'validated' => 'Validé', 'cancelled' => 'Annulé'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold">Filtrer</button>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Référence</th>
                        <th class="px-3 py-2">Commande</th>
                        <th class="px-3 py-2">Client</th>
                        <th class="px-3 py-2">Canal</th>
                        <th class="px-3 py-2">Statut</th>
                        <th class="px-3 py-2">Avoir</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($returns as $ret)
                        <tr>
                            <td class="px-3 py-2 font-semibold">{{ $ret->reference }}</td>
                            <td class="px-3 py-2">{{ $ret->order?->ticket_number ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $ret->client?->name ?: '—' }}</td>
                            <td class="px-3 py-2">{{ $ret->source ?: '—' }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $badge = match($ret->status) {
                                        'validated' => 'bg-emerald-100 text-emerald-800',
                                        'cancelled' => 'bg-slate-100 text-slate-600',
                                        default => 'bg-amber-100 text-amber-900',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">{{ $ret->status }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $ret->creditNote?->credit_note_number ?: '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                @if($enabled)
                                    <a href="{{ route('sales.returns.show', $ret) }}" class="text-[#0a5d8a] text-xs font-semibold hover:underline">Ouvrir</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-10 text-center text-slate-500">Aucun retour enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $returns->links() }}</div>
    </div>
</main>
@endsection
