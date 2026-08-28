@extends('layouts.with-sidebar')
@section('title', 'Rapports RH')
@section('sidebar_page_title', 'RH')
@section('main')
@php
    $employees = \App\Models\Employee::query()->orderBy('last_name')->get();
    $departments = \App\Models\HrDepartment::query()->orderBy('name')->get();
@endphp
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8 max-w-5xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Rapports RH</h1>
    <p class="text-gray-500 mb-6">Exports Excel et PDF filtrables par période, salarié, service, fonction et statut.</p>
    @include('hr.partials.flash')
    <form method="GET" action="{{ route('hr.reports.export') }}" class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="md:col-span-3">
            <label class="text-xs text-gray-500">Rapport</label>
            <select name="type" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                @foreach($types as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="text-xs text-gray-500">Mois</label><input type="number" name="month" min="1" max="12" value="{{ now()->month }}" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div><label class="text-xs text-gray-500">Année</label><input type="number" name="year" value="{{ now()->year }}" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
        <div>
            <label class="text-xs text-gray-500">Salarié</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Tous</option>
                @foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->fullName() }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500">Service</label>
            <select name="department_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Tous</option>
                @foreach($departments as $dep)<option value="{{ $dep->id }}">{{ $dep->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500">Statut</label>
            <select name="status" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Tous</option>
                @foreach(\App\Models\Employee::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500">Format</label>
            <select name="format" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="xlsx">Excel</option>
                <option value="pdf">PDF</option>
            </select>
        </div>
        <div class="md:col-span-3">
            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Télécharger</button>
        </div>
    </form>
</main>
@endsection
