@extends('layouts.with-sidebar')
@section('title', 'Historique RH')
@section('sidebar_page_title', 'RH')
@section('main')
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Historique RH</h1>
    <x-table-filters :action="route('hr.history.index')" search-placeholder="Événement, salarié..." grid-cols="md:grid-cols-5">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Salarié</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Tous</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" @selected((string) request('employee_id') === (string) $emp->id)>{{ $emp->fullName() }}</option>
                @endforeach
            </select>
        </div>
    </x-table-filters>
    <div class="bg-white rounded-xl border divide-y">
        @forelse($events as $event)
            <div class="px-5 py-4">
                <p class="text-xs text-gray-500">{{ $event->created_at?->format('d/m/Y H:i') }} · {{ $event->employee?->matricule }} {{ $event->employee?->fullName() }} · {{ $event->user?->name }}</p>
                <p class="font-medium">{{ $event->title }}</p>
                @if($event->description)<p class="text-sm text-gray-600">{{ $event->description }}</p>@endif
            </div>
        @empty
            <p class="px-5 py-8 text-center text-gray-500">Aucun événement.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $events->links() }}</div>

    <h2 class="text-xl font-semibold mt-10 mb-3">Journal d’audit (avant / après)</h2>
    <div class="bg-white rounded-xl border divide-y text-sm">
        @forelse($audits as $log)
            <div class="px-5 py-3">
                <p class="text-xs text-gray-500">{{ $log->created_at?->format('d/m/Y H:i') }} · {{ $log->user?->name }} · {{ $log->action }} {{ $log->field }}</p>
                <p>{{ $log->old_value }} → {{ $log->new_value }} @if($log->reason)<span class="text-gray-500">({{ $log->reason }})</span>@endif</p>
            </div>
        @empty
            <p class="px-5 py-6 text-gray-500">Aucune trace d’audit.</p>
        @endforelse
    </div>
</main>
@endsection
