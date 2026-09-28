@extends('layouts.with-sidebar')

@section('title', 'Dotations')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">Dotations</h2>
                <p class="text-sm text-slate-500 mt-1">Enveloppes consommées uniquement au paiement réel (pas à la création de dépense).</p>
            </div>
            <a href="{{ route('financial.cards.index') }}" class="text-sm font-medium text-[#0a5d8a] hover:underline">Gérer les cartes →</a>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 bg-slate-50/80 min-h-[60vh]">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-base font-semibold text-slate-900 mb-4">Nouvelle dotation</h3>
                <form method="POST" action="{{ route('financial.endowments.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Libellé *</label>
                        <input type="text" name="label" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" value="{{ old('label') }}">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Type</label>
                        <input type="text" name="type" placeholder="carte / caisse / autre" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" value="{{ old('type') }}">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Montant initial (MAD) *</label>
                        <input type="number" step="0.01" min="0.01" name="initial_amount" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" value="{{ old('initial_amount') }}">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Début</label>
                            <input type="date" name="starts_on" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" value="{{ old('starts_on') }}">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Fin</label>
                            <input type="date" name="ends_on" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" value="{{ old('ends_on') }}">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Compte *</label>
                        <select name="bank_account" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white" required>
                            @foreach($accountLabels as $key => $label)
                                <option value="{{ $key }}" @selected(old('bank_account', 'banque') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Cartes liées</label>
                        <select name="bank_card_ids[]" multiple class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white min-h-[88px]">
                            @foreach($cards as $card)
                                <option value="{{ $card->id }}">{{ $card->displayLabel() }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">Ctrl/Cmd pour multi-sélection.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="rounded text-[#0a5d8a]"> Active</label>
                    <button type="submit" class="w-full px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Créer</button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-4">
                @forelse($endowments as $e)
                    <a href="{{ route('financial.endowments.show', $e) }}" class="block bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-[#0a5d8a]/40 transition">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-slate-900">{{ $e->label }}</h3>
                                <p class="text-xs text-slate-500 mt-1">
                                    {{ $accountLabels[$e->bank_account] ?? $e->bank_account }}
                                    @if($e->cards->isNotEmpty()) · {{ $e->cards->map->displayLabel()->join(', ') }} @endif
                                </p>
                            </div>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $e->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $e->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <p class="text-[11px] text-slate-500 uppercase">Initial</p>
                                <p class="font-semibold tabular-nums">{{ number_format((float) $e->initial_amount, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] text-slate-500 uppercase">Consommé</p>
                                <p class="font-semibold tabular-nums text-rose-700">{{ number_format((float) $e->consumed_amount, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] text-slate-500 uppercase">Reste</p>
                                <p class="font-semibold tabular-nums text-emerald-700">{{ number_format($e->remaining(), 2) }}</p>
                            </div>
                        </div>
                        <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-[#0a5d8a]" style="width: {{ min(100, $e->usedPercent()) }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500">{{ $e->usedPercent() }} % utilisé</p>
                    </a>
                @empty
                    <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-500">Aucune dotation.</div>
                @endforelse
            </div>
        </div>
    </div>
</main>
@endsection
