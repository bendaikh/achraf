@extends('layouts.with-sidebar')
@section('title', 'Simulation de paie')
@section('sidebar_page_title', 'RH')
@section('main')
@php $fmt = fn ($n) => number_format((float) $n, 2, ',', ' '); @endphp
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8 max-w-3xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Simulation salaire Brut / Net</h1>
    <form method="GET" class="bg-white rounded-xl border p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-3">
        <select name="employee_id" class="px-3 py-2 border rounded-lg text-sm" required>
            <option value="">Salarié</option>
            @foreach($employees as $emp)
                <option value="{{ $emp->id }}" @selected((string) ($employee?->id) === (string) $emp->id)>{{ $emp->fullName() }}</option>
            @endforeach
        </select>
        <input type="number" name="month" min="1" max="12" value="{{ $month }}" class="px-3 py-2 border rounded-lg text-sm">
        <input type="number" name="year" value="{{ $year }}" class="px-3 py-2 border rounded-lg text-sm">
        <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Simuler</button>
    </form>
    @if($simulation)
        <div class="bg-white rounded-xl border divide-y text-sm">
            @foreach([
                'Salaire de base' => $simulation['base_salary'],
                'Prime' => $simulation['primes'],
                'Indemnités' => $simulation['indemnites'],
                'Heures supplémentaires' => $simulation['overtime_amount'],
                'Absence / retenue' => $simulation['absence_deduction'] + $simulation['retenues'],
                'Avances' => $simulation['avances'],
                'Salaire brut' => $simulation['gross'],
                'CNSS salariale' => $simulation['employee_cnss'],
                'AMO salariale' => $simulation['employee_amo'],
                'IR' => $simulation['income_tax'],
                'Net à payer' => $simulation['net'],
                'Charges patronales' => $simulation['employer_contributions'],
                'Coût total employeur' => $simulation['employer_cost'],
            ] as $label => $amount)
                <div class="px-5 py-3 flex justify-between {{ str_contains($label, 'Net') || str_contains($label, 'Coût') ? 'font-semibold' : '' }}">
                    <span>{{ $label }}</span><span>{{ $fmt($amount) }} MAD</span>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 mt-3">Règles : {{ $simulation['rule_set_name'] }} (à partir du {{ \Carbon\Carbon::parse($simulation['effective_from'])->format('d/m/Y') }}). Les taux ne sont pas figés dans le code.</p>
    @endif
</main>
@endsection
