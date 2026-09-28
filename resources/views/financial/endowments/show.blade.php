@extends('layouts.with-sidebar')

@section('title', 'Dotation — '.$endowment->label)
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Dotations</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">{{ $endowment->label }}</h2>
                <p class="text-sm text-slate-500 mt-1">Reste {{ number_format($endowment->remaining(), 2) }} MAD · {{ $endowment->usedPercent() }} % utilisé</p>
            </div>
            <a href="{{ route('financial.endowments.index') }}" class="text-sm font-medium text-[#0a5d8a] hover:underline">← Liste</a>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 bg-slate-50/80">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="font-semibold text-slate-900 mb-4">Modifier</h3>
                <form method="POST" action="{{ route('financial.endowments.update', $endowment) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Libellé *</label>
                        <input type="text" name="label" value="{{ old('label', $endowment->label) }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Montant initial *</label>
                        <input type="number" step="0.01" name="initial_amount" value="{{ old('initial_amount', $endowment->initial_amount) }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Compte *</label>
                        <select name="bank_account" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                            @foreach($accountLabels as $key => $label)
                                <option value="{{ $key }}" @selected(old('bank_account', $endowment->bank_account) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Cartes</label>
                        <select name="bank_card_ids[]" multiple class="w-full px-3 py-2 border rounded-lg text-sm bg-white min-h-[88px]">
                            @foreach(\App\Models\BankCard::query()->orderBy('label')->get() as $card)
                                <option value="{{ $card->id }}" @selected($endowment->cards->contains('id', $card->id))>{{ $card->displayLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($endowment->is_active)> Active</label>
                    <button type="submit" class="w-full px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer</button>
                </form>
                <form method="POST" action="{{ route('financial.endowments.recalc', $endowment) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700">Recalculer consommé</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-900">Consommations (paiement réel)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Date</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Réf.</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Notes</th>
                                <th class="px-4 py-3 text-right text-xs uppercase text-slate-500">MAD</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($endowment->consumptions as $c)
                                <tr>
                                    <td class="px-4 py-3">{{ $c->consumed_on?->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $c->reference ?: '—' }}</td>
                                    <td class="px-4 py-3">{{ $c->notes ?: ($c->supplier_name ?: '—') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format((float) $c->amount_mad, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">Aucune consommation.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
