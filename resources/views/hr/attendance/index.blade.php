@extends('layouts.with-sidebar')
@section('title', 'Présences & Pointage')
@section('sidebar_page_title', 'RH')
@section('main')
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Présences & Pointage</h1>
    <p class="text-gray-500 mb-6">Saisie manuelle aujourd’hui, architecture prête pour une pointeuse (ID externe sur la fiche salarié).</p>
    @include('hr.partials.flash')
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach(['today' => 'Aujourd\'hui', 'week' => 'Cette semaine', 'month' => 'Ce mois', 'custom' => 'Période'] as $key => $label)
            <a href="{{ route('hr.attendance.index', ['preset' => $key]) }}" class="px-3 py-1.5 rounded-lg text-sm {{ ($preset ?? '') === $key ? 'bg-[#0a5d8a] text-white' : 'bg-white border' }}">{{ $label }}</a>
        @endforeach
    </div>
    <form method="POST" action="{{ route('hr.attendance.store') }}" class="bg-white rounded-xl border p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
        @csrf
        <select name="employee_id" class="px-3 py-2 border rounded-lg text-sm" required>
            <option value="">Salarié</option>
            @foreach($employees as $emp)
                <option value="{{ $emp->id }}">{{ $emp->matricule }} — {{ $emp->fullName() }}</option>
            @endforeach
        </select>
        <input type="date" name="work_date" value="{{ now()->toDateString() }}" class="px-3 py-2 border rounded-lg text-sm" required>
        <input type="time" name="clock_in" class="px-3 py-2 border rounded-lg text-sm">
        <input type="time" name="clock_out" class="px-3 py-2 border rounded-lg text-sm">
        <select name="status" class="px-3 py-2 border rounded-lg text-sm">
            @foreach(\App\Models\AttendanceRecord::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select>
        <input type="text" name="correction_reason" class="px-3 py-2 border rounded-lg text-sm" placeholder="Motif si correction">
        <button class="md:col-span-6 px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer</button>
    </form>
    <x-table-filters :action="route('hr.attendance.index')" search-placeholder="Salarié..." grid-cols="md:grid-cols-5">
        <input type="hidden" name="preset" value="custom">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
            <select name="status" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Tous</option>
                @foreach(\App\Models\AttendanceRecord::STATUSES as $k => $l)
                    <option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
    </x-table-filters>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Salarié</th><th class="px-4 py-3 text-left">Entrée</th><th class="px-4 py-3 text-left">Sortie</th><th class="px-4 py-3 text-left">Durée</th><th class="px-4 py-3 text-left">Retard</th><th class="px-4 py-3 text-left">HS</th><th class="px-4 py-3 text-left">Statut</th><th class="px-4 py-3 text-left">Source</th>
            </tr></thead>
            <tbody>
                @forelse($records as $row)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $row->work_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $row->employee?->fullName() }}</td>
                        <td class="px-4 py-3">{{ $row->clock_in ? substr($row->clock_in,0,5) : '—' }}</td>
                        <td class="px-4 py-3">{{ $row->clock_out ? substr($row->clock_out,0,5) : '—' }}</td>
                        <td class="px-4 py-3">{{ $row->workedHoursLabel() }}</td>
                        <td class="px-4 py-3">{{ $row->late_minutes }} min</td>
                        <td class="px-4 py-3">{{ $row->overtime_minutes }} min</td>
                        <td class="px-4 py-3">{{ $row->statusLabel() }}</td>
                        <td class="px-4 py-3">{{ $row->source }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">Aucun pointage.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $records->links() }}</div>
    </div>
</main>
@endsection
