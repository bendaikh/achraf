@extends('layouts.with-sidebar')

@section('title', 'Relevé — '.$import->original_filename)
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Relevé bancaire</p>
                <h2 class="text-xl font-bold text-slate-900 mt-0.5">{{ $import->original_filename }}</h2>
                <p class="text-sm text-slate-500 mt-1">
                    {{ $accountLabels[$import->account] ?? $import->account }} · {{ $import->lines_count }} ligne(s)
                    · {{ $import->created_at?->format('d/m/Y H:i') }}
                </p>
            </div>
            <a href="{{ route('financial.bank-imports.index') }}" class="text-sm text-[#0a5d8a] hover:underline">← Imports</a>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-4 bg-slate-50/80">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[900px]">
                    <thead class="bg-slate-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Date</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Libellé</th>
                            <th class="px-4 py-3 text-right text-xs uppercase text-slate-500">Débit</th>
                            <th class="px-4 py-3 text-right text-xs uppercase text-slate-500">Crédit</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Statut</th>
                            <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Rapprochement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($import->lines as $line)
                            <tr class="{{ $line->status === 'matched' ? 'bg-emerald-50/40' : ($line->status === 'suggested' ? 'bg-amber-50/50' : '') }}">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $line->operation_date?->format('d/m/Y') ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-900">{{ $line->label ?: '—' }}</p>
                                    @if($line->reference)<p class="text-xs font-mono text-slate-500">{{ $line->reference }}</p>@endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-rose-700">{{ (float) $line->debit > 0 ? number_format((float) $line->debit, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-700">{{ (float) $line->credit > 0 ? number_format((float) $line->credit, 2) : '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                                        {{ $statusLabels[$line->status] ?? $line->status }}
                                    </span>
                                    @if($line->movement)
                                        <p class="text-[11px] text-slate-500 mt-1 font-mono">{{ $line->movement->reference }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($line->status === 'matched')
                                        <span class="text-xs text-emerald-700 font-medium">OK</span>
                                    @else
                                        <div class="flex flex-col gap-2">
                                            @if($line->status === 'suggested' && $line->financial_movement_id)
                                                <form method="POST" action="{{ route('financial.bank-imports.lines.confirm', $line) }}">
                                                    @csrf
                                                    <button type="submit" class="text-xs px-3 py-1.5 bg-amber-600 text-white rounded-lg">Confirmer suggestion</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('financial.bank-imports.lines.match', $line) }}" class="flex flex-wrap gap-1 items-center">
                                                @csrf
                                                <select name="financial_movement_id" required class="text-xs px-2 py-1.5 border rounded-lg bg-white max-w-[220px]">
                                                    <option value="">Mouvement…</option>
                                                    @foreach($candidateMovements as $m)
                                                        <option value="{{ $m->id }}" @selected($line->financial_movement_id === $m->id)>
                                                            {{ $m->movement_date?->format('d/m') }} · {{ $m->reference }} ·
                                                            {{ (float) $m->amount_in > 0 ? '+'.number_format((float)$m->amount_in,2) : '-'.number_format((float)$m->amount_out,2) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="text-xs px-2 py-1.5 bg-[#0a5d8a] text-white rounded-lg">Lier</button>
                                            </form>
                                            <form method="POST" action="{{ route('financial.bank-imports.lines.ignore', $line) }}">
                                                @csrf
                                                <button type="submit" class="text-xs text-slate-500 hover:text-rose-600">Ignorer</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
@endsection
