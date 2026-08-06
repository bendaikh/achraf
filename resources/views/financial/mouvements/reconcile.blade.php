@extends('layouts.with-sidebar')

@section('title', 'Rapprochement bancaire')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière / Mouvements</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">Rapprochement bancaire</h2>
                <p class="text-sm text-slate-500 mt-1">Pointer les mouvements banque ({{ $dateFrom }} → {{ $dateTo }}).</p>
            </div>
            <a href="{{ route('financial.mouvements.index', request()->only(['date_from','date_to'])) }}" class="text-sm font-medium text-[#0a5d8a] hover:underline">← Retour au journal</a>
        </div>
        @include('financial.partials.finance-tabs')
    </header>

    <div class="p-8 space-y-6">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Solde banque</p>
                <p class="text-xl font-bold">{{ number_format($treasury['banque'], 2) }} DH</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">À pointer</p>
                <p class="text-xl font-bold">{{ $unpointed->count() }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Disponible</p>
                <p class="text-xl font-bold">{{ number_format($treasury['total'], 2) }} DH</p>
            </div>
        </div>

        <form method="POST" action="{{ route('financial.mouvements.point-bulk') }}">
            @csrf
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Mouvements banque non pointés</h3>
                    <button type="submit" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Pointer la sélection</button>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left"><input type="checkbox" onclick="document.querySelectorAll('.mvt-check').forEach(c => c.checked = this.checked)"></th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Date</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Réf.</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Libellé</th>
                            <th class="px-4 py-3 text-right text-xs uppercase text-gray-500">Entrée</th>
                            <th class="px-4 py-3 text-right text-xs uppercase text-gray-500">Sortie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($unpointed as $m)
                            <tr>
                                <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $m->id }}" class="mvt-check"></td>
                                <td class="px-4 py-3">{{ $m->movement_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $m->reference }}</td>
                                <td class="px-4 py-3">{{ $m->label }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600">{{ (float) $m->amount_in > 0 ? number_format((float) $m->amount_in, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right text-red-600">{{ (float) $m->amount_out > 0 ? number_format((float) $m->amount_out, 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Tous les mouvements banque sont pointés sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</main>
@endsection
