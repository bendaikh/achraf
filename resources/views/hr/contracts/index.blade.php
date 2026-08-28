@extends('layouts.with-sidebar')
@section('title', 'Contrats')
@section('sidebar_page_title', 'RH')
@section('main')
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Contrats</h1>
    <p class="text-gray-500 mb-6">Historique des contrats, renouvellements et avenants. Les anciens contrats ne disparaissent jamais.</p>
    @include('hr.partials.flash')
    <x-table-filters :action="route('hr.contracts.index')" search-placeholder="Salarié, matricule, fonction..." :date-from="false" :date-to="false" grid-cols="md:grid-cols-5">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
            <select name="status" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Tous</option>
                @foreach(\App\Models\EmployeeContract::STATUSES as $k => $l)
                    <option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="type" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Tous</option>
                @foreach(\App\Models\EmployeeContract::TYPES as $k => $l)
                    <option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
    </x-table-filters>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left">Salarié</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Début</th><th class="px-4 py-3 text-left">Fin</th><th class="px-4 py-3 text-left">Essai</th><th class="px-4 py-3 text-left">Statut</th>
            </tr></thead>
            <tbody>
                @forelse($contracts as $contract)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3"><a class="text-[#0a5d8a] font-medium" href="{{ route('hr.employees.show', [$contract->employee, 'tab' => 'contrat']) }}">{{ $contract->employee?->matricule }} — {{ $contract->employee?->fullName() }}</a></td>
                        <td class="px-4 py-3">{{ $contract->typeLabel() }}</td>
                        <td class="px-4 py-3">{{ $contract->start_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $contract->end_date?->format('d/m/Y') ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $contract->trial_end_date?->format('d/m/Y') ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $contract->statusLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucun contrat.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $contracts->links() }}</div>
    </div>
</main>
@endsection
