{{-- Shared chrome for Gestion financière subsection pages --}}
@php
    $statusLabels = [
        'paid' => 'Payé',
        'partial' => 'Partiellement payé',
        'unpaid' => 'À payer',
    ];
    $statusClasses = [
        'paid' => 'bg-emerald-100 text-emerald-800',
        'partial' => 'bg-amber-100 text-amber-800',
        'unpaid' => 'bg-red-100 text-red-800',
    ];
@endphp

<header class="bg-white shadow-sm border-b border-gray-200">
    <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Gestion financière</h2>
            <p class="text-sm text-gray-600 mt-1">Vue centralisée des revenus, dépenses, paiements, TVA et trésorerie.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                    onclick="document.getElementById('finance-explanation')?.classList.toggle('hidden')"
                    class="inline-flex items-center justify-center px-4 py-2 border border-[#c9a227] rounded-lg text-sm font-medium text-[#8a6d1b] bg-white hover:bg-amber-50 whitespace-nowrap">
                Voir les explications
            </button>
            <form method="GET" action="{{ route($filterRoute) }}" class="inline-flex">
                <select name="month" onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-800">
                    @foreach($monthOptions as $opt)
                        <option value="{{ $opt['value'] }}" @selected($selectedMonth === $opt['value'])>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </form>
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium text-white bg-[#0a5d8a] hover:bg-[#084a6e] whitespace-nowrap">
                    + Nouvelle opération
                </button>
                <div x-show="open" @click.outside="open = false" x-cloak
                     class="absolute right-0 mt-2 w-56 rounded-lg border border-gray-200 bg-white shadow-lg z-20 py-1">
                    <a href="{{ route('invoices.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Facture client</a>
                    <a href="{{ route('supplier-invoices.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Facture fournisseur</a>
                    <a href="{{ route('expenses-with-invoice.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Dépense avec facture</a>
                    <a href="{{ route('expenses-without-invoice.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Dépense sans facture</a>
                    <a href="{{ route('sales.payments.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Paiement client</a>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="p-4 sm:p-6 lg:p-8 space-y-6">
    <div id="finance-explanation" class="hidden rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        {{ $explanation ?? 'Cette section affiche des données réelles calculées depuis vos factures, paiements, dépenses et ventes POS.' }}
    </div>

    {{-- Section header + filters --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold tracking-wide uppercase text-[#c9a227]">GESTION FINANCIÈRE / {{ $breadcrumb }}</p>
                <h3 class="text-xl font-bold text-gray-900 mt-1">{{ $sectionTitle }}</h3>
                <p class="text-sm text-gray-600 mt-1">{{ $sectionDescription }}</p>
            </div>
            <form method="GET" action="{{ route($filterRoute) }}" class="flex flex-wrap items-end gap-2 shrink-0">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">De</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">À</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium hover:bg-[#084a6e]">Filtrer</button>
            </form>
        </div>
    </div>

    {{-- Status cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Source des données</p>
            <p class="text-sm font-semibold text-gray-900">{{ $dataSource }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">État de la période</p>
            <p class="text-sm font-semibold {{ $health['status'] === 'ok' ? 'text-emerald-600' : 'text-emerald-600' }}">{{ $health['status_label'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Anomalies détectées</p>
            <p class="text-sm font-semibold {{ $health['anomalies_count'] > 0 ? 'text-orange-600' : 'text-gray-900' }}">
                {{ $health['anomalies_count'] > 0 ? $health['anomalies_count'].' élément'.($health['anomalies_count'] > 1 ? 's' : '') : 'Aucun' }}
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Dernière mise à jour</p>
            <p class="text-sm font-semibold text-gray-900">{{ $health['last_updated_label'] }}</p>
        </div>
    </div>

    {{-- Info + Actions --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h4 class="text-base font-semibold text-gray-900">Informations du module</h4>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($infoItems as $i => $item)
                    <div class="flex items-center gap-3 px-5 py-3.5 {{ ($item['highlight'] ?? false) ? 'bg-amber-50' : '' }}">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-50 text-xs font-semibold text-[#8a6d1b]">
                            {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ $item['label'] }}</p>
                            @if(!empty($item['value']))
                                <p class="text-xs text-gray-500 mt-0.5">{{ $item['value'] }}</p>
                            @endif
                        </div>
                        @if(!empty($item['url']))
                            <a href="{{ $item['url'] }}" class="text-xs font-medium text-[#0a5d8a] hover:underline whitespace-nowrap">Voir le détail ></a>
                        @else
                            <span class="text-xs text-gray-400 whitespace-nowrap">—</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col gap-3">
            <h4 class="text-base font-semibold text-gray-900 mb-1">Actions disponibles</h4>
            @if(!empty($primaryAction))
                <a href="{{ $primaryAction['url'] }}"
                   class="inline-flex items-center justify-center w-full px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-[#0a5d8a] hover:bg-[#084a6e]">
                    {{ $primaryAction['label'] }}
                </a>
            @endif
            @foreach($secondaryActions ?? [] as $action)
                <a href="{{ $action['url'] }}"
                   class="inline-flex items-center justify-center w-full px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                    {{ $action['label'] }}
                </a>
            @endforeach
            @if(!empty($linkAction))
                <a href="{{ $linkAction['url'] }}" class="text-sm text-gray-500 hover:text-gray-800 text-center mt-1">{{ $linkAction['label'] }}</a>
            @endif
            <div class="mt-auto rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-3 text-xs text-emerald-800">
                <span class="font-semibold">Traçabilité activée.</span> Toute validation ou modification conserve l'utilisateur, la date et le motif.
            </div>
        </div>
    </div>

    @isset($declarationRows)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <h4 class="text-base font-semibold text-gray-900">Synthèse de déclaration</h4>
                <a href="{{ route('financial.export', request()->only(['date_from', 'date_to'])) }}"
                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Exporter CSV
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Indicateur</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Détail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($declarationRows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-800">{{ $row['indicateur'] }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">
                                    @if(is_numeric($row['montant']))
                                        {{ number_format((float) $row['montant'], 2) }} DH
                                    @else
                                        {{ $row['montant'] }}
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-500 hidden md:table-cell">{{ $row['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endisset

    {{-- Recent operations --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <h4 class="text-base font-semibold text-gray-900">Dernières opérations</h4>
            <a href="{{ route('financial.export', request()->only(['date_from', 'date_to'])) }}"
               class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Exporter Excel
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Libellé</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
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
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-900">{{ $row['date'] ?? '—' }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-700">{{ $row['reference'] ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-gray-700">
                                @if(!empty($row['kind']))
                                    <span class="text-xs text-gray-400 mr-1">{{ $row['kind'] }}</span>
                                @endif
                                {{ $row['party'] ?? ($row['label'] ?? '—') }}
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-sm text-right font-semibold {{ ($row['direction'] ?? '') === 'in' ? 'text-emerald-600' : (($row['direction'] ?? '') === 'out' ? 'text-red-600' : 'text-gray-900') }}">
                                @if(($row['direction'] ?? null) === 'in')+@elseif(($row['direction'] ?? null) === 'out')−@endif{{ number_format((float) ($row['total'] ?? 0), 2) }} DH
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$row['status'] ?? ''] ?? 'bg-gray-100 text-gray-700' }}">
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
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">Aucune opération sur la période sélectionnée</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
