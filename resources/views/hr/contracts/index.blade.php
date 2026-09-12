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
    <x-table-list-toolbar table-id="hr-contracts" />
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table data-lm-table="hr-contracts" class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left">Salarié</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-left">Début</th>
                <th class="px-4 py-3 text-left">Fin</th>
                <th class="px-4 py-3 text-left">Fonction</th>
                <th class="px-4 py-3 text-left">Salaire brut contractuel</th>
                <th class="px-4 py-3 text-left">Statut</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($contracts as $contract)
                    <tr class="border-t hover:bg-gray-50" x-data="{ open: false }">
                        <td class="px-4 py-3">
                            <a class="text-[#0a5d8a] font-medium" href="{{ route('hr.employees.show', [$contract->employee, 'tab' => 'contrat']) }}">
                                {{ $contract->employee?->matricule }} — {{ $contract->employee?->fullName() }}
                            </a>
                            @if($contract->is_amendment)
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Avenant</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $contract->typeLabel() }}</td>
                        <td class="px-4 py-3">{{ $contract->start_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $contract->end_date?->format('d/m/Y') ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $contract->job_title ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $contract->salary ? number_format((float) $contract->salary, 2, ',', ' ').' MAD' : '—' }}</td>
                        <td class="px-4 py-3">{{ $contract->statusLabel() }}</td>
                        <td class="px-4 py-3 text-right relative">
                            <button type="button" @click="open = !open" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" aria-label="Actions">⋯</button>
                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-4 z-20 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 text-left shadow-lg">
                                <a href="{{ route('hr.contracts.show', $contract) }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Voir</a>
                                <a href="{{ route('hr.contracts.edit', $contract) }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Modifier</a>
                                <a href="{{ route('hr.contracts.edit', $contract) }}?avenant=1" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Avenant / nouvelle version</a>
                                <a href="{{ route('hr.contracts.show', $contract) }}?tab=historique" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Historique</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Aucun contrat.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $contracts->links() }}</div>
    </div>
</main>
@endsection
