@extends('layouts.with-sidebar')

@section('title', 'Bascule Finance & soldes')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière</p>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">Bascule & soldes d’ouverture</h2>
            <p class="text-sm text-slate-500 mt-1">Cutoff officiel : <strong>{{ \Carbon\Carbon::parse($cutoff)->format('d/m/Y') }}</strong>. Les soldes d’ouverture ne comptent pas dans le CA.</p>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 bg-slate-50/80">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @foreach($balances as $account => $row)
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $accountLabels[$account] ?? $account }}</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($row['current'], 2) }} <span class="text-sm font-medium text-slate-500">MAD</span></p>
                    <p class="mt-1 text-xs text-slate-500">Ouverture cutoff : {{ number_format($row['opening'], 2) }} MAD</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-5">
                <div>
                    <h3 class="font-semibold text-slate-900">Date de bascule</h3>
                    <form method="POST" action="{{ route('financial.cutoff.update') }}" class="mt-3 flex flex-wrap gap-2 items-end">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Cutoff</label>
                            <input type="date" name="finance_cutoff_date" value="{{ $cutoff }}" required class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer</button>
                    </form>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <h3 class="font-semibold text-slate-900">Soldes d’ouverture (à la bascule)</h3>
                    <form method="POST" action="{{ route('financial.cutoff.openings') }}" class="mt-3 space-y-3">
                        @csrf
                        @foreach($accountLabels as $key => $label)
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">{{ $label }}</label>
                                <input type="number" step="0.01" name="balances[{{ $key }}]" value="{{ old('balances.'.$key, $balances[$key]['opening'] ?? 0) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            </div>
                        @endforeach
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Notes</label>
                            <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></textarea>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer les soldes</button>
                    </form>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <h3 class="font-semibold text-slate-900">Ajustement trésorerie</h3>
                    <p class="text-xs text-slate-500 mt-1">Ni vente ni dépense — correction de solde uniquement.</p>
                    <form method="POST" action="{{ route('financial.cutoff.adjust') }}" class="mt-3 space-y-3">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Date</label>
                                <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Compte</label>
                                <select name="account" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                                    @foreach($accountLabels as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Sens</label>
                                <select name="direction" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                                    <option value="+">+ Entrée</option>
                                    <option value="-">− Sortie</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Montant</label>
                                <input type="number" step="0.01" min="0.01" name="amount" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Motif *</label>
                            <input type="text" name="motif" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-800 hover:bg-slate-50">Créer l’ajustement</button>
                    </form>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <h3 class="font-semibold text-slate-900">Clôturer une période</h3>
                    <form method="POST" action="{{ route('financial.cutoff.periods.close') }}" class="mt-3 space-y-3">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Du</label>
                                <input type="date" name="period_start" required class="w-full px-3 py-2 border rounded-lg text-sm" value="{{ now()->startOfMonth()->toDateString() }}">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Au</label>
                                <input type="date" name="period_end" required class="w-full px-3 py-2 border rounded-lg text-sm" value="{{ now()->endOfMonth()->toDateString() }}">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Motif *</label>
                            <textarea name="close_reason" rows="2" required minlength="5" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm">Clôturer</button>
                    </form>

                    @if($periods->isNotEmpty())
                        <ul class="mt-4 divide-y border-t border-slate-100">
                            @foreach($periods as $period)
                                <li class="py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $period->period_start->format('d/m/Y') }} → {{ $period->period_end->format('d/m/Y') }}</p>
                                        <p class="text-xs text-slate-500">{{ $period->isClosed() ? 'Clôturée' : 'Ouverte' }}@if($period->close_reason) — {{ \Illuminate\Support\Str::limit($period->close_reason, 60) }}@endif</p>
                                    </div>
                                    @if($period->isClosed())
                                        <form method="POST" action="{{ route('financial.cutoff.periods.reopen', $period) }}" class="flex gap-2 items-end">
                                            @csrf
                                            <input type="text" name="reopen_reason" required minlength="5" placeholder="Motif réouverture" class="px-2 py-1.5 border rounded text-xs w-40">
                                            <button type="submit" class="px-3 py-1.5 text-xs font-medium text-[#0a5d8a] border border-[#0a5d8a]/30 rounded">Réouvrir</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
