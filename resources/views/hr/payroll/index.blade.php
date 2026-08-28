@extends('layouts.with-sidebar')
@section('title', 'Rémunération / Paie')
@section('sidebar_page_title', 'RH')
@section('main')
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Rémunération / Paie</h1>
            <p class="text-gray-500 mt-1">Brouillon → Calculée → Vérifiée → Validée → Payée. Une paie validée est protégée.</p>
        </div>
        <a href="{{ route('hr.payroll.simulate') }}" class="px-4 py-2 border rounded-lg text-sm">Simulation brut / net</a>
    </div>
    @include('hr.partials.flash')
    <form method="POST" action="{{ route('hr.payroll.prepare') }}" class="bg-white rounded-xl border p-4 mb-6 flex flex-wrap gap-3 items-end">
        @csrf
        <div><label class="block text-xs text-gray-500 mb-1">Mois</label><input type="number" name="month" min="1" max="12" value="{{ $month }}" class="px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="block text-xs text-gray-500 mb-1">Année</label><input type="number" name="year" value="{{ $year }}" class="px-3 py-2 border rounded-lg text-sm"></div>
        <button class="px-4 py-2 bg-[#fdb819] text-white rounded-lg font-semibold">Préparer le mois</button>
    </form>
    <x-table-list-toolbar table-id="hr-payroll" />
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table data-lm-table="hr-payroll" class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left lm-col lm-col-salarie column-salarie" data-lm-col="salarie">Période</th><th class="px-4 py-3 text-left lm-col lm-col-periode column-periode" data-lm-col="periode">Statut</th><th class="px-4 py-3 text-left lm-col lm-col-brut column-brut" data-lm-col="brut">Bulletins</th><th class="px-4 py-3 text-left lm-col lm-col-net column-net" data-lm-col="net"></th>
            </tr></thead>
            <tbody>
                @forelse($runs as $run)
                    <tr class="border-t">
                        <td class="px-4 py-3 font-medium lm-col lm-col-salarie column-salarie" data-lm-col="salarie">{{ $run->periodLabel() }}</td>
                        <td class="px-4 py-3 lm-col lm-col-periode column-periode" data-lm-col="periode">{{ $run->statusLabel() }}</td>
                        <td class="px-4 py-3 lm-col lm-col-brut column-brut" data-lm-col="brut">{{ $run->slips_count }}</td>
                        <td class="px-4 py-3 lm-col lm-col-net column-net" data-lm-col="net"><a class="text-[#0a5d8a] font-medium" href="{{ route('hr.payroll.show', $run) }}">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Aucune paie.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $runs->links() }}</div>
    </div>
</main>
@endsection
