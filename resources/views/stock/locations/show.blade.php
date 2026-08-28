@extends('layouts.with-sidebar')

@section('title', 'Stock '.$warehouse->name)
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><a href="{{ route('stock.locations.index') }}" class="hover:underline">Emplacements</a></p>
                <h1 class="text-2xl font-bold text-slate-900">Stock {{ $warehouse->name }}</h1>
                <p class="text-sm text-slate-600 mt-0.5">
                    @if($warehouse->isOnline())
                        Stock synchronisé avec Shopify (indépendant des dépôts physiques).
                    @else
                        Stock physique de ce dépôt uniquement — non additionné au stock Shopify.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stock.locations.export', [$warehouse, 'excel']) }}?as_of={{ $asOf->format('Y-m-d') }}@if($selectedLocationId ?? null)&warehouse_location_id={{ $selectedLocationId }}@endif" class="px-4 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700">Télécharger Excel</a>
                <a href="{{ route('stock.locations.export', [$warehouse, 'pdf']) }}?as_of={{ $asOf->format('Y-m-d') }}@if($selectedLocationId ?? null)&warehouse_location_id={{ $selectedLocationId }}@endif" class="px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">Télécharger PDF</a>
                @if($warehouse->isPhysical())
                    <a href="{{ route('stock.locations.count', $warehouse) }}" class="px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700">Inventaire physique</a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded text-green-700">{{ session('success') }}</div>
        @endif

        <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 bg-white border border-slate-200 rounded-xl p-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">État du stock au</label>
                <input type="date" name="as_of" value="{{ $asOf->format('Y-m-d') }}" class="rounded-lg border-slate-300 text-sm">
            </div>
            @if(($locations ?? collect())->isNotEmpty())
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Emplacement</label>
                <select name="warehouse_location_id" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Tous les emplacements</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(($selectedLocationId ?? null) == $loc->id)>{{ $loc->displayLabel() }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Afficher</button>
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">Références</p>
                <p class="text-2xl font-bold tabular-nums">{{ number_format($report['references']) }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">Quantité totale</p>
                <p class="text-2xl font-bold tabular-nums">{{ number_format($report['quantity']) }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">Valeur HT</p>
                <p class="text-2xl font-bold tabular-nums">{{ number_format($report['value_ht'], 2) }} DH</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">TVA</p>
                <p class="text-2xl font-bold tabular-nums">{{ number_format($report['value_vat'] ?? 0, 2) }} DH</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-xs text-slate-500">Valeur TTC</p>
                <p class="text-2xl font-bold tabular-nums">{{ number_format($report['value_ttc'], 2) }} DH</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-3 text-left">SKU</th>
                            <th class="px-3 py-3 text-left">Produit</th>
                            <th class="px-3 py-3 text-left">Empl.</th>
                            <th class="px-3 py-3 text-left">Fournisseur</th>
                            <th class="px-3 py-3 text-right">Qté</th>
                            <th class="px-3 py-3 text-right">Rés.</th>
                            <th class="px-3 py-3 text-right">Dispo.</th>
                            <th class="px-3 py-3 text-right">PA HT</th>
                            <th class="px-3 py-3 text-right">PA TTC</th>
                            <th class="px-3 py-3 text-right">Val. HT</th>
                            <th class="px-3 py-3 text-right">Val. TTC</th>
                            <th class="px-3 py-3 text-right">PV HT</th>
                            <th class="px-3 py-3 text-right">PV TTC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['rows'] as $row)
                            <tr>
                                <td class="px-3 py-3 font-mono text-xs">{{ $row->sku }}</td>
                                <td class="px-3 py-3 font-medium">{{ $row->name }}</td>
                                <td class="px-3 py-3">{{ $row->location ?? '—' }}</td>
                                <td class="px-3 py-3">{{ $row->supplier ?: '—' }}</td>
                                <td class="px-3 py-3 text-right font-semibold">{{ $row->quantity }}</td>
                                <td class="px-3 py-3 text-right">{{ $row->reserved ?? 0 }}</td>
                                <td class="px-3 py-3 text-right">{{ $row->available ?? $row->quantity }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($row->price_ht, 2) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($row->price_ttc, 2) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($row->value_ht, 2) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($row->value_ttc, 2) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($row->sale_price_ht ?? 0, 2) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($row->sale_price_ttc ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="px-4 py-10 text-center text-slate-500">Aucun produit en stock dans ce dépôt à cette date.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td class="px-3 py-3" colspan="4">TOTAL · {{ $report['references'] }} réf.</td>
                            <td class="px-3 py-3 text-right">{{ number_format($report['quantity']) }}</td>
                            <td colspan="4"></td>
                            <td class="px-3 py-3 text-right">{{ number_format($report['value_ht'], 2) }}</td>
                            <td class="px-3 py-3 text-right">{{ number_format($report['value_ttc'], 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</main>
@endsection
