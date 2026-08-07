@extends('layouts.with-sidebar')

@section('title', 'Mouvements financiers')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">Journal des mouvements</h2>
                <p class="text-sm text-slate-500 mt-1">Tous les flux issus des ventes, achats, POS et dépenses — une seule source de vérité.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('financial.mouvements.sync') }}">
                    @csrf
                    <button type="submit" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">Sync</button>
                </form>
                <a href="{{ route('financial.mouvements.reconcile', request()->only(['date_from','date_to'])) }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">Rapprocher</a>
                <a href="{{ route('financial.mouvements.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">Excel</a>
                <a href="{{ route('financial.mouvements.create') }}" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#0a5d8a] hover:bg-[#084a6e]">+ Nouveau</a>
            </div>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-5 bg-slate-50/80">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="rounded-xl border border-[#b6d7ea] bg-[#e8f4fa] p-4">
                <p class="text-[11px] font-semibold uppercase text-[#0a5d8a]">Solde banque</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-[#084a6e]">{{ number_format($treasury['banque'], 2) }} DH</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                <p class="text-[11px] font-semibold uppercase text-violet-700">Solde caisse</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-violet-900">{{ number_format($treasury['caisse'], 2) }} DH</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-[11px] font-semibold uppercase text-emerald-700">Entrées</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-emerald-900">{{ number_format($treasury['entrees'], 2) }} DH</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-[11px] font-semibold uppercase text-rose-700">Sorties</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-rose-900">{{ number_format($treasury['sorties'], 2) }} DH</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 col-span-2 lg:col-span-1">
                <p class="text-[11px] font-semibold uppercase text-amber-800">Disponible</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-amber-950">{{ number_format($treasury['total'], 2) }} DH</p>
            </div>
        </div>

        <form method="GET" action="{{ route('financial.mouvements.index') }}"
              class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm grid grid-cols-2 md:grid-cols-3 xl:grid-cols-7 gap-3 items-end">
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">De</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">À</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">Type</label>
                <select name="type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="all">Tous</option>
                    @foreach($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">Origine</label>
                <select name="origin" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="all">Toutes</option>
                    @foreach($originLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['origin'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">Compte</label>
                <select name="account" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="all">Tous</option>
                    @foreach($accountLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['account'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">Recherche</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Réf, libellé…" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium hover:bg-[#084a6e]">Filtrer</button>
        </form>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Référence</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Origine</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Libellé</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Compte</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Entrée</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Sortie</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Solde</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Statut</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $row)
                            @php $m = $row['movement']; @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-800">{{ $m->movement_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $m->reference }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $originLabels[$m->origin] ?? $m->origin }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $m->type === 'entree' ? 'bg-emerald-100 text-emerald-800' : ($m->type === 'sortie' ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800') }}">
                                        {{ $typeLabels[$m->type] ?? $m->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 max-w-[220px] truncate text-slate-800" title="{{ $m->label }}">{{ $m->label }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $accountLabels[$m->account] ?? $m->account }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums text-emerald-600">{{ (float) $m->amount_in > 0 ? number_format((float) $m->amount_in, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums text-rose-600">{{ (float) $m->amount_out > 0 ? number_format((float) $m->amount_out, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-bold tabular-nums text-slate-900">{{ number_format((float) $row['solde'], 2) }}</td>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ $m->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                                        {{ $statusLabels[$m->status] ?? $m->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @if($m->justificatif_path)
                                            <a href="{{ asset('storage/'.$m->justificatif_path) }}" target="_blank" class="text-xs text-slate-500 hover:underline">Pièce</a>
                                        @endif
                                        @if(!$m->isLocked() && $m->status !== 'pointe')
                                            <form method="POST" action="{{ route('financial.mouvements.point', $m) }}">
                                                @csrf
                                                <button type="submit" class="text-xs font-medium text-[#0a5d8a] hover:underline">Pointer</button>
                                            </form>
                                        @endif
                                        @if($m->isEditable())
                                            <a href="{{ route('financial.mouvements.edit', $m) }}" class="text-xs font-medium text-emerald-700 hover:underline">Modifier</a>
                                        @endif
                                        @if($m->isDeletable())
                                            <form method="POST" action="{{ route('financial.mouvements.destroy', $m) }}" onsubmit="return confirm('Supprimer ce mouvement ?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-medium text-rose-600 hover:underline">Suppr.</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-14 text-center text-slate-500">
                                    Aucun mouvement sur la période.
                                    <form method="POST" action="{{ route('financial.mouvements.sync') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-[#0a5d8a] font-medium hover:underline">Synchroniser l’historique</button>
                                    </form>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($lastPage > 1)
                <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-600">
                    <span>{{ $total }} mouvement(s) — page {{ $page }}/{{ $lastPage }}</span>
                    <div class="flex gap-2">
                        @if($page > 1)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}" class="px-3 py-1 border rounded-lg hover:bg-slate-50">Précédent</a>
                        @endif
                        @if($page < $lastPage)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}" class="px-3 py-1 border rounded-lg hover:bg-slate-50">Suivant</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</main>
@endsection
