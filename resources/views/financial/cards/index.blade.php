@extends('layouts.with-sidebar')

@section('title', 'Cartes bancaires')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">Cartes bancaires</h2>
                <p class="text-sm text-slate-500 mt-1">Cartes liées aux dotations et aux paiements de dépenses.</p>
            </div>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 bg-slate-50/80 min-h-[60vh]">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-base font-semibold text-slate-900 mb-4">Nouvelle carte</h3>
                <form method="POST" action="{{ route('financial.cards.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Libellé *</label>
                        <input type="text" name="label" value="{{ old('label') }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Compte *</label>
                        <select name="account" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white" required>
                            @foreach($accountLabels as $key => $label)
                                <option value="{{ $key }}" @selected(old('account', 'banque') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">4 derniers chiffres</label>
                        <input type="text" name="last_four" maxlength="4" pattern="[0-9]{4}" value="{{ old('last_four') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded text-[#0a5d8a]">
                        Active
                    </label>
                    <button type="submit" class="w-full px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium hover:bg-[#084a6e]">Créer</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-base font-semibold text-slate-900">Cartes enregistrées</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs uppercase text-slate-500">Carte</th>
                                <th class="px-5 py-3 text-left text-xs uppercase text-slate-500">Compte</th>
                                <th class="px-5 py-3 text-left text-xs uppercase text-slate-500">Dotations</th>
                                <th class="px-5 py-3 text-left text-xs uppercase text-slate-500">Statut</th>
                                <th class="px-5 py-3 text-right text-xs uppercase text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($cards as $card)
                                <tr>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-900">{{ $card->displayLabel() }}</p>
                                        @if($card->notes)<p class="text-xs text-slate-500 mt-0.5">{{ $card->notes }}</p>@endif
                                    </td>
                                    <td class="px-5 py-3">{{ $accountLabels[$card->account] ?? $card->account }}</td>
                                    <td class="px-5 py-3">{{ $card->endowments->pluck('label')->join(', ') ?: '—' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $card->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $card->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <details class="inline-block text-left">
                                            <summary class="cursor-pointer text-[#0a5d8a] font-medium text-xs">Modifier</summary>
                                            <form method="POST" action="{{ route('financial.cards.update', $card) }}" class="mt-2 p-3 border rounded-lg bg-slate-50 space-y-2 w-64">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="label" value="{{ $card->label }}" required class="w-full px-2 py-1.5 border rounded text-sm">
                                                <select name="account" class="w-full px-2 py-1.5 border rounded text-sm bg-white">
                                                    @foreach($accountLabels as $key => $label)
                                                        <option value="{{ $key }}" @selected($card->account === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="last_four" value="{{ $card->last_four }}" maxlength="4" placeholder="••••" class="w-full px-2 py-1.5 border rounded text-sm">
                                                <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="is_active" value="1" @checked($card->is_active)> Active</label>
                                                <button type="submit" class="w-full px-2 py-1.5 bg-[#0a5d8a] text-white rounded text-xs">Enregistrer</button>
                                            </form>
                                        </details>
                                        <form method="POST" action="{{ route('financial.cards.destroy', $card) }}" class="inline" onsubmit="return confirm('Supprimer cette carte ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-rose-600 font-medium ml-2">Suppr.</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Aucune carte.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
