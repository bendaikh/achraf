@extends('layouts.with-sidebar')
@section('title', 'Primes & Indemnités')
@section('sidebar_page_title', 'RH')
@section('main')
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Primes & Indemnités</h1>
    @include('hr.partials.flash')
    <form method="POST" action="{{ route('hr.compensations.store') }}" class="bg-white rounded-xl border p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
        @csrf
        <select name="employee_id" class="px-3 py-2 border rounded-lg text-sm" required>
            <option value="">Salarié</option>
            @foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>@endforeach
        </select>
        <select name="kind" class="px-3 py-2 border rounded-lg text-sm">
            @foreach(\App\Models\CompensationItem::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select>
        <select name="recurrence" class="px-3 py-2 border rounded-lg text-sm">
            @foreach(\App\Models\CompensationItem::RECURRENCES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select>
        <input type="number" step="0.01" name="amount" class="px-3 py-2 border rounded-lg text-sm" placeholder="Montant" required>
        <input type="date" name="start_date" class="px-3 py-2 border rounded-lg text-sm" required>
        <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Ajouter</button>
    </form>
    <x-table-list-toolbar table-id="hr-compensations" />
    <div class="bg-white rounded-lg shadow overflow-x-auto mb-8">
        <table data-lm-table="hr-compensations" class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left lm-col lm-col-salarie column-salarie" data-lm-col="salarie">Salarié</th><th class="px-4 py-3 text-left lm-col lm-col-type column-type" data-lm-col="type">Élément</th><th class="px-4 py-3 text-left lm-col lm-col-montant column-montant" data-lm-col="montant">Montant</th><th class="px-4 py-3 text-left lm-col lm-col-date column-date" data-lm-col="date">Récurrence</th><th class="px-4 py-3 text-left lm-col lm-col-statut column-statut" data-lm-col="statut">Période</th>
            </tr></thead>
            <tbody>
                @forelse($items as $item)
                    <tr class="border-t">
                        <td class="px-4 py-3 lm-col lm-col-salarie column-salarie" data-lm-col="salarie">{{ $item->employee?->fullName() }}</td>
                        <td class="px-4 py-3 lm-col lm-col-type column-type" data-lm-col="type">{{ $item->kindLabel() }}</td>
                        <td class="px-4 py-3 lm-col lm-col-montant column-montant" data-lm-col="montant">{{ number_format($item->amount, 2, ',', ' ') }} MAD</td>
                        <td class="px-4 py-3 lm-col lm-col-date column-date" data-lm-col="date">{{ \App\Models\CompensationItem::RECURRENCES[$item->recurrence] ?? $item->recurrence }}</td>
                        <td class="px-4 py-3 lm-col lm-col-statut column-statut" data-lm-col="statut">{{ $item->start_date?->format('d/m/Y') }}{{ $item->end_date ? ' → '.$item->end_date->format('d/m/Y') : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun élément.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $items->links() }}</div>
    </div>
    <h2 class="text-xl font-semibold mb-3">Retenues & avances</h2>
    <ul class="bg-white rounded-xl border divide-y text-sm">
        @foreach($adjustments as $adj)
            <li class="px-4 py-3">{{ $adj->employee?->fullName() }} — {{ $adj->typeLabel() }} — {{ number_format($adj->amount, 2, ',', ' ') }} MAD ({{ sprintf('%02d/%d', $adj->period_month, $adj->period_year) }}) {{ $adj->reason }}</li>
        @endforeach
    </ul>
</main>
@endsection
