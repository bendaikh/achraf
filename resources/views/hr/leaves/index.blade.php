@extends('layouts.with-sidebar')
@section('title', 'Congés & Absences')
@section('sidebar_page_title', 'RH')
@section('main')
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Congés & Absences</h1>
    @include('hr.partials.flash')
    <form method="POST" action="{{ route('hr.leaves.store') }}" class="bg-white rounded-xl border p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-3">
        @csrf
        <select name="employee_id" class="px-3 py-2 border rounded-lg text-sm" required>
            <option value="">Salarié</option>
            @foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>@endforeach
        </select>
        <select name="leave_type_id" class="px-3 py-2 border rounded-lg text-sm" required>
            @foreach($leaveTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
        </select>
        <input type="date" name="start_date" class="px-3 py-2 border rounded-lg text-sm" required>
        <input type="date" name="end_date" class="px-3 py-2 border rounded-lg text-sm" required>
        <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Nouvelle demande</button>
    </form>
    <x-table-list-toolbar table-id="hr-leaves" />
    <div class="bg-white rounded-lg shadow overflow-x-auto mb-8">
        <table data-lm-table="hr-leaves" class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <x-lm-col key="salarie" tag="th" class="px-4 py-3 text-left">Salarié</x-lm-col>
                    <x-lm-col key="type" tag="th" class="px-4 py-3 text-left">Type</x-lm-col>
                    <x-lm-col key="debut" tag="th" class="px-4 py-3 text-left">Début</x-lm-col>
                    <x-lm-col key="fin" tag="th" class="px-4 py-3 text-left">Fin</x-lm-col>
                    <x-lm-col key="jours" tag="th" class="px-4 py-3 text-left">Jours</x-lm-col>
                    <x-lm-col key="statut" tag="th" class="px-4 py-3 text-left">Statut</x-lm-col>
                    <x-lm-col key="actions" tag="th" class="px-4 py-3 text-left">Actions</x-lm-col>
                </tr>
            </thead>
            <tbody>
                @forelse($leaves as $leave)
                    <tr class="border-t">
                        <x-lm-col key="salarie" class="px-4 py-3">{{ $leave->employee?->fullName() }}</x-lm-col>
                        <x-lm-col key="type" class="px-4 py-3">{{ $leave->leaveType?->name }}</x-lm-col>
                        <x-lm-col key="debut" class="px-4 py-3">{{ $leave->start_date?->format('d/m/Y') }}</x-lm-col>
                        <x-lm-col key="fin" class="px-4 py-3">{{ $leave->end_date?->format('d/m/Y') }}</x-lm-col>
                        <x-lm-col key="jours" class="px-4 py-3">{{ $leave->days }}</x-lm-col>
                        <x-lm-col key="statut" class="px-4 py-3">{{ $leave->statusLabel() }}</x-lm-col>
                        <x-lm-col key="actions" class="px-4 py-3">
                            @if($leave->status === 'pending')
                                <form method="POST" action="{{ route('hr.leaves.review', $leave) }}" class="inline">@csrf<input type="hidden" name="status" value="approved"><button class="text-emerald-700 text-xs font-semibold">Valider</button></form>
                                <form method="POST" action="{{ route('hr.leaves.review', $leave) }}" class="inline ml-2">@csrf<input type="hidden" name="status" value="rejected"><button class="text-red-700 text-xs font-semibold">Refuser</button></form>
                            @endif
                        </x-lm-col>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucune demande.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $leaves->links() }}</div>
    </div>
    <h2 class="text-xl font-semibold mb-3">Absences</h2>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left">Salarié</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Période</th><th class="px-4 py-3 text-left">Impact paie</th>
            </tr></thead>
            <tbody>
                @foreach($absences as $absence)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $absence->employee?->fullName() }}</td>
                        <td class="px-4 py-3">{{ $absence->typeLabel() }}</td>
                        <td class="px-4 py-3">{{ $absence->start_date?->format('d/m/Y') }} → {{ $absence->end_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $absence->impacts_payroll ? 'Oui' : 'Non' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</main>
@endsection
