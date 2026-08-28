@php
    $progress = $receiptProgress ?? collect();
    $receptions = $linkedReceptions ?? collect();
    $statusLabel = $receptionStatus ?? null;
    $receiveRoute = $receiveRoute ?? null;
    $canReceive = $canReceive ?? false;
    $documentLabel = $documentLabel ?? 'document';
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Suivi de réception</h3>
            <p class="text-sm text-gray-600 mt-1">Même moteur de réception pour tout le cycle achat — une seule entrée physique via le BR.</p>
            @if($statusLabel)
                @php
                    $badgeClass = match(true) {
                        str_contains(strtolower($statusLabel), 'partiel') => 'bg-amber-100 text-amber-800',
                        str_contains(strtolower($statusLabel), 'réceptionné') && !str_contains(strtolower($statusLabel), 'non') => 'bg-emerald-100 text-emerald-800',
                        default => 'bg-gray-100 text-gray-700',
                    };
                @endphp
                <span class="mt-2 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $badgeClass }}">{{ $statusLabel }}</span>
            @endif
        </div>
        @if($canReceive && $receiveRoute)
            <a href="{{ $receiveRoute }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition duration-150 text-sm font-medium">
                📦 Réceptionner
            </a>
        @endif
    </div>

    @if($progress->isNotEmpty())
        <div class="overflow-x-auto mb-6">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produit / SKU</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qté {{ $documentLabel }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Réceptionné</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Reste</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Dépôt</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($progress as $row)
                        <tr>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900">{{ $row['designation'] ?? '—' }}</div>
                                @if(!empty($row['ref']))
                                    <div class="text-xs text-gray-500">{{ $row['ref'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ $row['document_qty'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['received'] }}</td>
                            <td class="px-3 py-2 text-right font-semibold {{ $row['remaining'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $row['remaining'] }}</td>
                            <td class="px-3 py-2">{{ $row['warehouse'] ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $lineBadge = match($row['status'] ?? '') {
                                        'receptionne' => 'bg-emerald-100 text-emerald-800',
                                        'partiel' => 'bg-amber-100 text-amber-800',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $lineBadge }}">{{ $row['status_label'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($receptions->isNotEmpty())
        <div>
            <h4 class="text-sm font-semibold text-gray-900 mb-2">Historique des réceptions (BR)</h4>
            <ul class="space-y-2 text-sm">
                @foreach($receptions as $reception)
                    <li class="flex flex-wrap items-center gap-2 p-3 bg-gray-50 rounded-lg">
                        <a href="{{ route('receptions.show', $reception) }}" class="font-medium text-[#0a5d8a] hover:underline">{{ $reception->reception_number }}</a>
                        <span class="text-gray-500">{{ $reception->reception_date?->format('d/m/Y') }}</span>
                        @if($reception->warehouse)
                            <span class="text-gray-600">· {{ $reception->warehouse->name }}</span>
                        @endif
                        @if($reception->stock_applied_at)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">Validé</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif(!$canReceive && $progress->isNotEmpty())
        <p class="text-sm text-gray-500">Aucune réception enregistrée pour ce {{ $documentLabel }}.</p>
    @endif
</div>
