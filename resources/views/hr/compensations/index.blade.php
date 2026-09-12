@extends('layouts.with-sidebar')
@section('title', 'Primes & Indemnités')
@section('sidebar_page_title', 'RH')
@section('main')
@php $fmt = fn ($n) => number_format((float) $n, 2, ',', ' '); @endphp
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Primes & Indemnités</h1>
    <p class="text-gray-500 mb-6">Éléments récurrents repris automatiquement en paie jusqu’à suspension ou arrêt.</p>
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
        <input type="date" name="end_date" class="px-3 py-2 border rounded-lg text-sm" placeholder="Fin (optionnel)">
        <button class="md:col-span-6 px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Ajouter</button>
    </form>

    <div class="bg-white rounded-lg shadow overflow-x-auto mb-8">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left">Salarié</th>
                <th class="px-4 py-3 text-left">Élément</th>
                <th class="px-4 py-3 text-left">Montant</th>
                <th class="px-4 py-3 text-left">Récurrence</th>
                <th class="px-4 py-3 text-left">Période</th>
                <th class="px-4 py-3 text-left">Statut</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($items as $item)
                    @include('hr.compensations._item-rows', ['item' => $item, 'fmt' => $fmt])
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucun élément.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $items->links() }}</div>
    </div>

    <h2 class="text-xl font-semibold mb-3">Retenues & avances</h2>
    <form method="POST" action="{{ route('hr.adjustments.store') }}" class="bg-white rounded-xl border p-4 mb-4 grid grid-cols-1 md:grid-cols-6 gap-3">
        @csrf
        <select name="employee_id" class="px-3 py-2 border rounded-lg text-sm" required>
            <option value="">Salarié</option>
            @foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>@endforeach
        </select>
        <select name="type" class="px-3 py-2 border rounded-lg text-sm">
            @foreach(\App\Models\PayrollAdjustment::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select>
        <input type="number" step="0.01" name="amount" class="px-3 py-2 border rounded-lg text-sm" placeholder="Montant initial" required>
        <input type="number" step="0.01" name="monthly_amount" class="px-3 py-2 border rounded-lg text-sm" placeholder="Retenue mensuelle (ex. 500)">
        <input type="number" name="period_year" value="{{ now()->year }}" class="px-3 py-2 border rounded-lg text-sm">
        <input type="number" name="period_month" value="{{ now()->month }}" min="1" max="12" class="px-3 py-2 border rounded-lg text-sm">
        <input type="date" name="start_date" value="{{ now()->startOfMonth()->toDateString() }}" class="px-3 py-2 border rounded-lg text-sm">
        <input type="text" name="reason" class="md:col-span-2 px-3 py-2 border rounded-lg text-sm" placeholder="Motif">
        <button class="md:col-span-3 px-4 py-2 bg-white border rounded-lg text-sm">Enregistrer retenue / avance</button>
    </form>

    <ul class="bg-white rounded-xl border divide-y text-sm">
        @forelse($adjustments as $adj)
            <li class="px-4 py-3 flex flex-wrap justify-between gap-3" x-data="{ edit: false }">
                <div>
                    <p class="font-medium">{{ $adj->employee?->fullName() }} — {{ $adj->typeLabel() }}</p>
                    <p class="text-gray-500">
                        Initial {{ $fmt($adj->amount) }} MAD
                        @if($adj->monthly_amount) · {{ $fmt($adj->monthly_amount) }} MAD/mois @endif
                        · Retenu {{ $fmt($adj->recoveredTotal()) }} · Solde {{ $fmt($adj->remaining_amount ?? $adj->amount) }} MAD
                        · Début {{ $adj->start_date?->format('d/m/Y') ?: sprintf('%02d/%d', $adj->period_month, $adj->period_year) }}
                        @if($adj->end_date) · Fin {{ $adj->end_date->format('d/m/Y') }} @endif
                        · {{ $adj->statusLabel() }}
                    </p>
                    @if($adj->reason)<p class="text-gray-400">{{ $adj->reason }}</p>@endif
                </div>
                <button type="button" @click="edit = !edit" class="text-[#0a5d8a] text-sm">Gérer</button>
                <div x-show="edit" x-cloak class="w-full">
                    <form method="POST" action="{{ route('hr.adjustments.update', $adj) }}" class="grid grid-cols-1 md:grid-cols-4 gap-2">
                        @csrf
                        @method('PUT')
                        <input type="number" step="0.01" name="monthly_amount" value="{{ $adj->monthly_amount }}" class="px-3 py-2 border rounded-lg text-sm" placeholder="Montant mensuel">
                        <select name="status" class="px-3 py-2 border rounded-lg text-sm">
                            @foreach(\App\Models\PayrollAdjustment::STATUSES as $k => $l)
                                <option value="{{ $k }}" @selected(($adj->status ?? 'actif') === $k)>{{ $l }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="end_date" value="{{ $adj->end_date?->format('Y-m-d') }}" class="px-3 py-2 border rounded-lg text-sm">
                        <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Mettre à jour</button>
                    </form>
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-gray-500">Aucune retenue / avance.</li>
        @endforelse
    </ul>
</main>
@endsection
