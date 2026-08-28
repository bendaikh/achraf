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
    <div class="bg-white rounded-lg shadow overflow-x-auto mb-8">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left">Salarié</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Période</th><th class="px-4 py-3 text-left">Jours</th><th class="px-4 py-3 text-left">Statut</th><th class="px-4 py-3 text-left">Action</th>
            </tr></thead>
            <tbody>
                @forelse($leaves as $leave)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $leave->employee?->fullName() }}</td>
                        <td class="px-4 py-3">{{ $leave->leaveType?->name }}</td>
                        <td class="px-4 py-3">{{ $leave->start_date?->format('d/m/Y') }} → {{ $leave->end_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $leave->days }}</td>
                        <td class="px-4 py-3">{{ $leave->statusLabel() }}</td>
                        <td class="px-4 py-3">
                            @if($leave->status === 'pending')
                                <form method="POST" action="{{ route('hr.leaves.review', $leave) }}" class="inline">@csrf<input type="hidden" name="status" value="approved"><button class="text-emerald-700 text-xs font-semibold">Valider</button></form>
                                <form method="POST" action="{{ route('hr.leaves.review', $leave) }}" class="inline ml-2">@csrf<input type="hidden" name="status" value="rejected"><button class="text-red-700 text-xs font-semibold">Refuser</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucune demande.</td></tr>
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
