@extends('layouts.with-sidebar')
@section('title', request()->boolean('avenant') ? 'Avenant' : 'Modifier le contrat')
@section('sidebar_page_title', 'RH')
@section('main')
@php
    $isAvenant = request()->boolean('avenant');
    $input = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm';
    $action = $isAvenant ? route('hr.contracts.amend', $contract) : route('hr.contracts.update', $contract);
@endphp
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8 max-w-3xl">
    <div class="mb-6">
        <a href="{{ route('hr.contracts.show', $contract) }}" class="text-sm text-gray-500 hover:text-[#c9920f]">← Retour</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">
            {{ $isAvenant ? 'Avenant / nouvelle version' : 'Modifier le contrat' }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ $contract->employee?->fullName() }}
            @if($isAvenant)
                — l’ancien contrat reste inchangé dans l’historique.
            @else
                — correction de saisie (tracée). N’utilisez pas ceci pour une vraie évolution : créez un avenant.
            @endif
        </p>
    </div>

    @include('hr.partials.flash')

    <form method="POST" action="{{ $action }}" class="bg-white rounded-xl border p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        @csrf
        @unless($isAvenant)
            @method('PUT')
        @endunless

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Type</label>
            <select name="type" class="{{ $input }}" required>
                @foreach(\App\Models\EmployeeContract::TYPES as $k => $l)
                    <option value="{{ $k }}" @selected(old('type', $contract->type) === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ $isAvenant ? 'Date d’effet' : 'Date de début' }}</label>
            <input type="date" name="start_date" value="{{ old('start_date', $isAvenant ? now()->toDateString() : $contract->start_date?->format('Y-m-d')) }}" class="{{ $input }}" required>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date de fin</label>
            <input type="date" name="end_date" value="{{ old('end_date', $contract->end_date?->format('Y-m-d')) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Fonction</label>
            <input type="text" name="job_title" value="{{ old('job_title', $contract->job_title) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Lieu de travail</label>
            <input type="text" name="workplace" value="{{ old('workplace', $contract->workplace) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Service</label>
            <input type="text" name="department_name" value="{{ old('department_name', $contract->department_name ?: $contract->employee?->department?->name) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Salaire brut contractuel</label>
            <input type="number" step="0.01" name="salary" value="{{ old('salary', $contract->salary) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Début période d’essai</label>
            <input type="date" name="trial_start_date" value="{{ old('trial_start_date', $contract->trial_start_date?->format('Y-m-d')) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Fin période d’essai</label>
            <input type="date" name="trial_end_date" value="{{ old('trial_end_date', $contract->trial_end_date?->format('Y-m-d')) }}" class="{{ $input }}">
        </div>
        @unless($isAvenant)
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Statut</label>
                <select name="status" class="{{ $input }}" required>
                    @foreach(\App\Models\EmployeeContract::STATUSES as $k => $l)
                        <option value="{{ $k }}" @selected(old('status', $contract->status) === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">Motif de la correction</label>
                <input type="text" name="change_reason" value="{{ old('change_reason', 'Correction de saisie') }}" class="{{ $input }}" required>
            </div>
        @endunless
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-500 mb-1">Notes</label>
            <textarea name="notes" class="{{ $input }}" rows="2">{{ old('notes', $contract->notes) }}</textarea>
        </div>
        <div class="md:col-span-2 flex justify-end gap-2">
            <a href="{{ route('hr.contracts.show', $contract) }}" class="px-4 py-2 border rounded-lg text-sm">Annuler</a>
            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">
                {{ $isAvenant ? 'Créer l’avenant' : 'Enregistrer la correction' }}
            </button>
        </div>
    </form>
</main>
@endsection
