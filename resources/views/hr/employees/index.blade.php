@extends('layouts.with-sidebar')

@section('title', 'Salariés')
@section('sidebar_page_title', 'RH')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Salariés</h1>
                <p class="text-gray-500 mt-1">Dossiers RH, historique antérieur compris. Un salarié n’est pas un compte utilisateur Libromart.</p>
            </div>
            <a href="{{ route('hr.employees.create') }}" class="inline-flex items-center justify-center px-6 py-3 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Ajouter un salarié</a>
        </div>

        <x-crm-import-panel
            label="Salariés"
            :template-route="route('hr.employees.import.template')"
            :import-route="route('hr.employees.import')"
        />

        @include('hr.partials.flash')

        <x-table-filters :action="route('hr.employees.index')" search-placeholder="Matricule, nom, CIN, fonction..." :date-from="false" :date-to="false" grid-cols="md:grid-cols-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach(\App\Models\Employee::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Service</label>
                <select name="department_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach($departments as $dep)
                        <option value="{{ $dep->id }}" @selected((string) request('department_id') === (string) $dep->id)>{{ $dep->name }}</option>
                    @endforeach
                </select>
            </div>
        </x-table-filters>

        <x-table-bulk-bar export-type="employees" item-label="salarié(s)" :can-delete="false" />

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <x-table-checkbox-header export-type="employees" />
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matricule</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salarié</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fonction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entrée</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($employees as $employee)
                            <tr class="hover:bg-gray-50">
                                <x-table-checkbox-cell export-type="employees" :id="$employee->id" />
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $employee->matricule }}</td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $employee->fullName() }}</div>
                                    <div class="text-xs text-gray-500">{{ $employee->email ?? $employee->phone ?? '—' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $employee->job_title ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $employee->department?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $employee->hire_date?->format('d/m/Y') }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $employee->statusBadgeClass() }}">{{ $employee->statusLabel() }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('hr.employees.show', $employee) }}" class="text-blue-600 hover:text-blue-900 text-sm font-medium">Ouvrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">Aucun salarié.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $employees->links() }}</div>
        </div>
    </div>
</main>
@endsection
