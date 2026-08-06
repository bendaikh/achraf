{{-- Shared chrome for Gestion financière subsection pages --}}
@php
    $statusLabels = [
        'paid' => 'Payé',
        'partial' => 'Partiellement payé',
        'unpaid' => 'À payer',
        'valide' => 'Validé',
        'pointe' => 'Pointé',
        'cloture' => 'Clôturé',
    ];
    $statusClasses = [
        'paid' => 'bg-emerald-100 text-emerald-800',
        'partial' => 'bg-amber-100 text-amber-800',
        'unpaid' => 'bg-red-100 text-red-800',
        'valide' => 'bg-emerald-100 text-emerald-800',
        'pointe' => 'bg-sky-100 text-sky-800',
        'cloture' => 'bg-slate-200 text-slate-700',
    ];
    $toneMap = [
        'emerald' => ['card' => 'bg-emerald-50 border-emerald-200', 'label' => 'text-emerald-700', 'value' => 'text-emerald-900'],
        'sky' => ['card' => 'bg-sky-50 border-sky-200', 'label' => 'text-sky-700', 'value' => 'text-sky-900'],
        'amber' => ['card' => 'bg-amber-50 border-amber-200', 'label' => 'text-amber-800', 'value' => 'text-amber-950'],
        'rose' => ['card' => 'bg-rose-50 border-rose-200', 'label' => 'text-rose-700', 'value' => 'text-rose-900'],
        'violet' => ['card' => 'bg-violet-50 border-violet-200', 'label' => 'text-violet-700', 'value' => 'text-violet-900'],
        'slate' => ['card' => 'bg-slate-50 border-slate-200', 'label' => 'text-slate-600', 'value' => 'text-slate-900'],
        'blue' => ['card' => 'bg-[#e8f4fa] border-[#b6d7ea]', 'label' => 'text-[#0a5d8a]', 'value' => 'text-[#084a6e]'],
    ];
@endphp

<header class="bg-white border-b border-slate-200">
    <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière</p>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">{{ $sectionTitle }}</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-2xl">{{ $sectionDescription }}</p>
        </div>
        <form method="GET" action="{{ route($filterRoute) }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">De</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
            </div>
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">À</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
            </div>
            <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium hover:bg-[#084a6e]">Filtrer</button>
        </form>
    </div>
    @include('financial.partials.finance-tabs')
</header>

<div class="p-4 sm:p-6 lg:p-8 space-y-6 bg-slate-50/80 min-h-[60vh]">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div id="finance-explanation" class="{{ ($showExplanation ?? false) ? '' : 'hidden' }} rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        {{ $explanation ?? '' }}
    </div>

    {{-- KPI strip — main numbers, like the mockup --}}
    @if(!empty($kpiCards))
        @php
            $kpiCount = count($kpiCards);
            $kpiGrid = match (true) {
                $kpiCount <= 2 => 'grid-cols-2',
                $kpiCount === 3 => 'grid-cols-2 lg:grid-cols-3',
                $kpiCount === 4 => 'grid-cols-2 lg:grid-cols-4',
                default => 'grid-cols-2 md:grid-cols-3 xl:grid-cols-5',
            };
        @endphp
        <div class="grid {{ $kpiGrid }} gap-3">
            @foreach($kpiCards as $kpi)
                @php $tone = $toneMap[$kpi['tone'] ?? 'slate'] ?? $toneMap['slate']; @endphp
                <div class="rounded-xl border {{ $tone['card'] }} p-4 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide {{ $tone['label'] }}">{{ $kpi['label'] }}</p>
                    <p class="mt-2 text-xl sm:text-2xl font-bold tabular-nums {{ $tone['value'] }}">{{ $kpi['value'] }}</p>
                    @if(!empty($kpi['hint']))
                        <p class="mt-1 text-[11px] text-slate-500">{{ $kpi['hint'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Body: détail + actions --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Détail du module</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $dataSource }}</p>
                </div>
                <button type="button"
                        onclick="document.getElementById('finance-explanation')?.classList.toggle('hidden')"
                        class="text-xs font-medium text-[#0a5d8a] hover:underline whitespace-nowrap">
                    Comment ça marche ?
                </button>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($infoItems as $i => $item)
                    <div class="flex items-center gap-3 px-5 py-3.5 {{ ($item['highlight'] ?? false) ? 'bg-amber-50/70' : '' }}">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-600">
                            {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ $item['label'] }}</p>
                            @if(!empty($item['value']))
                                <p class="text-sm font-semibold text-slate-700 mt-0.5 tabular-nums">{{ $item['value'] }}</p>
                            @endif
                        </div>
                        @if(!empty($item['url']))
                            <a href="{{ $item['url'] }}" class="text-xs font-semibold text-[#0a5d8a] hover:underline whitespace-nowrap">Ouvrir →</a>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex flex-wrap gap-4 text-xs text-slate-500">
                <span>État : <strong class="{{ $health['status'] === 'ok' ? 'text-emerald-600' : 'text-amber-600' }}">{{ $health['status_label'] }}</strong></span>
                <span>Anomalies : <strong class="{{ $health['anomalies_count'] > 0 ? 'text-orange-600' : 'text-slate-700' }}">{{ $health['anomalies_count'] ?: 'Aucune' }}</strong></span>
                <span>MAJ : <strong class="text-slate-700">{{ $health['last_updated_label'] }}</strong></span>
            </div>
        </div>

        <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col gap-2.5">
            <h3 class="text-base font-semibold text-slate-900 mb-1">Actions rapides</h3>
            @if(!empty($primaryAction))
                @include('financial.partials.action-button', ['action' => $primaryAction, 'primary' => true])
            @endif
            @foreach($secondaryActions ?? [] as $action)
                @include('financial.partials.action-button', ['action' => $action, 'primary' => false])
            @endforeach
            @if(!empty($linkAction))
                @include('financial.partials.action-button', ['action' => $linkAction, 'primary' => false, 'linkStyle' => true])
            @endif
            <p class="mt-auto pt-3 text-[11px] leading-relaxed text-slate-500 border-t border-slate-100">
                Les montants se mettent à jour seuls dès qu’une facture, un paiement ou une dépense est enregistré — pas de double saisie.
            </p>
        </div>
    </div>

    @if(!empty($vatControls))
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="text-base font-semibold text-slate-900">Contrôles TVA</h3>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse($vatControls as $control)
                    <li class="px-5 py-3 text-sm flex items-start gap-2">
                        <span class="mt-1.5 inline-block h-2 w-2 rounded-full {{ ($control['severity'] ?? '') === 'error' ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                        <span class="text-slate-800">{{ $control['label'] }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-sm text-emerald-700">Aucun problème détecté sur la période.</li>
                @endforelse
            </ul>
        </div>
    @endif

    @isset($declarationRows)
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-slate-900">Synthèse de déclaration</h3>
                <a href="{{ route('financial.export', request()->only(['date_from', 'date_to'])) }}"
                   class="inline-flex items-center px-3 py-1.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">
                    Exporter
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Indicateur</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-slate-500 uppercase">Montant</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase hidden md:table-cell">Détail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($declarationRows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 text-slate-800">{{ $row['indicateur'] }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900 whitespace-nowrap tabular-nums">
                                    @if(is_numeric($row['montant']))
                                        {{ number_format((float) $row['montant'], 2) }} DH
                                    @else
                                        {{ $row['montant'] }}
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500 hidden md:table-cell">{{ $row['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endisset

    {{-- Recent operations --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-slate-900">Dernières opérations</h3>
            <a href="{{ route('financial.mouvements.index', request()->only(['date_from', 'date_to'])) }}"
               class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-white bg-[#0a5d8a] hover:bg-[#084a6e]">
                Voir le journal →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Date</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Référence</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Libellé</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-slate-500 uppercase">Montant</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Statut</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-slate-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php
                        $rows = $historyRows ?? null;
                        if ($rows === null && isset($recentTransactions)) {
                            $rows = collect($recentTransactions)->map(fn ($t) => [
                                'date' => $t['date_formatted'] ?? ($t['date']?->format('d/m/Y') ?? '—'),
                                'reference' => $t['reference'] ?? '—',
                                'party' => ($t['label'] ?? '').(($t['party'] ?? '') !== '' && ($t['party'] ?? '') !== '—' ? ' · '.$t['party'] : ''),
                                'total' => $t['amount'] ?? 0,
                                'status' => $t['status'] ?? 'paid',
                                'url' => $t['url'] ?? null,
                                'direction' => $t['direction'] ?? null,
                            ])->all();
                        }
                        $rows = $rows ?? [];
                    @endphp
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 whitespace-nowrap text-sm text-slate-900">{{ $row['date'] ?? '—' }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-sm font-mono text-xs text-slate-600">{{ $row['reference'] ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-slate-700">
                                @if(!empty($row['kind']))
                                    <span class="text-[10px] uppercase tracking-wide text-slate-400 mr-1">{{ $row['kind'] }}</span>
                                @endif
                                {{ $row['party'] ?? ($row['label'] ?? '—') }}
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-sm text-right font-semibold tabular-nums {{ ($row['direction'] ?? '') === 'in' ? 'text-emerald-600' : (($row['direction'] ?? '') === 'out' ? 'text-rose-600' : 'text-slate-900') }}">
                                @if(($row['direction'] ?? null) === 'in')+@elseif(($row['direction'] ?? null) === 'out')−@endif{{ number_format((float) ($row['total'] ?? 0), 2) }} DH
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$row['status'] ?? ''] ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ $statusLabels[$row['status'] ?? ''] ?? ($row['status'] ?? '—') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-right text-sm">
                                @if(!empty($row['url']))
                                    <a href="{{ $row['url'] }}" class="text-[#0a5d8a] hover:underline font-medium">Ouvrir</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">Aucune opération sur la période sélectionnée</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(!empty($showPieceModal))
        <div id="piece-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Ajouter une pièce justificative</h3>
                <form method="POST" action="{{ route('financial.tva.pieces.store') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Date</label>
                        <input type="date" name="piece_date" value="{{ now()->toDateString() }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Libellé</label>
                        <input type="text" name="label" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Ex. Facture TVA…">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Fichier (PDF / image)</label>
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required class="w-full text-sm">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="document.getElementById('piece-modal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-sm">Annuler</button>
                        <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if(!empty($showCloseDayModal))
        <div id="close-day-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Clôturer la journée</h3>
                <form method="POST" action="{{ route('financial.tresorerie.close-day') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Journée à clôturer</label>
                        <input type="date" name="day" value="{{ now()->toDateString() }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <p class="text-xs text-slate-500">Tous les mouvements de cette journée seront verrouillés.</p>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="document.getElementById('close-day-modal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-sm">Annuler</button>
                        <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Clôturer</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if(!empty($showReopenModal))
        <div id="reopen-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Réouvrir la période</h3>
                <form method="POST" action="{{ route('financial.declarations.reopen') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Motif (obligatoire)</label>
                        <textarea name="reopen_reason" rows="3" required minlength="5" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Ex. Correction facture manquante…"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="document.getElementById('reopen-modal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-sm">Annuler</button>
                        <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Réouvrir</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
