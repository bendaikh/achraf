@extends('layouts.with-sidebar')
@section('title', 'Contrat — '.$contract->employee?->fullName())
@section('sidebar_page_title', 'RH')
@section('main')
@php
    $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
    $tab = request('tab', 'detail');
@endphp
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8" x-data="{ tab: '{{ $tab }}' }">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('hr.contracts.index') }}" class="hover:text-[#c9920f]">Contrats</a>
                <span>/</span>
                <span class="text-gray-900">{{ $contract->kindLabel() }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $contract->employee?->fullName() }} — {{ $contract->typeLabel() }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $contract->kindLabel() }} · {{ $contract->statusLabel() }} · Début {{ $contract->start_date?->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('hr.contracts.edit', $contract) }}" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Modifier</a>
            <a href="{{ route('hr.contracts.edit', $contract) }}?avenant=1" class="px-4 py-2 border border-gray-300 bg-white rounded-lg text-sm font-semibold">Avenant</a>
        </div>
    </div>

    @include('hr.partials.flash')

    <nav class="flex gap-1 bg-white border rounded-xl p-1 mb-6 max-w-md">
        <button type="button" @click="tab = 'detail'" :class="tab === 'detail' ? 'bg-[#0a5d8a] text-white' : 'text-gray-600'" class="flex-1 px-3 py-2 rounded-lg text-sm font-medium">Détail</button>
        <button type="button" @click="tab = 'historique'" :class="tab === 'historique' ? 'bg-[#0a5d8a] text-white' : 'text-gray-600'" class="flex-1 px-3 py-2 rounded-lg text-sm font-medium">Historique</button>
    </nav>

    <div x-show="tab === 'detail'" class="bg-white rounded-xl border p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <p><span class="text-gray-500">Salarié</span><br><strong>
            <a class="text-[#0a5d8a]" href="{{ route('hr.employees.show', [$contract->employee, 'tab' => 'contrat']) }}">{{ $contract->employee?->fullName() }}</a>
        </strong></p>
        <p><span class="text-gray-500">Type</span><br><strong>{{ $contract->typeLabel() }}</strong></p>
        <p><span class="text-gray-500">Date de début</span><br><strong>{{ $contract->start_date?->format('d/m/Y') }}</strong></p>
        <p><span class="text-gray-500">Date de fin</span><br><strong>{{ $contract->end_date?->format('d/m/Y') ?: '—' }}</strong></p>
        <p><span class="text-gray-500">Période d’essai</span><br><strong>
            {{ $contract->trial_start_date?->format('d/m/Y') ?: '—' }} → {{ $contract->trial_end_date?->format('d/m/Y') ?: '—' }}
        </strong></p>
        <p><span class="text-gray-500">Fonction</span><br><strong>{{ $contract->job_title ?: '—' }}</strong></p>
        <p><span class="text-gray-500">Lieu de travail</span><br><strong>{{ $contract->workplace ?: '—' }}</strong></p>
        <p><span class="text-gray-500">Service (copie à la création)</span><br><strong>{{ $contract->department_name ?: '—' }}</strong></p>
        <p><span class="text-gray-500">Salaire brut contractuel</span><br><strong>{{ $contract->salary ? $fmt($contract->salary).' MAD' : '—' }}</strong></p>
        <p><span class="text-gray-500">Statut</span><br><strong>{{ $contract->statusLabel() }}</strong></p>
        @if($contract->previousContract)
            <p class="md:col-span-2"><span class="text-gray-500">Version précédente</span><br>
                <a class="text-[#0a5d8a]" href="{{ route('hr.contracts.show', $contract->previousContract) }}">
                    {{ $contract->previousContract->typeLabel() }} du {{ $contract->previousContract->start_date?->format('d/m/Y') }}
                </a>
            </p>
        @endif
        @if($contract->notes)
            <p class="md:col-span-2"><span class="text-gray-500">Notes</span><br>{{ $contract->notes }}</p>
        @endif
        <p class="md:col-span-2 text-xs text-gray-500">Ces informations sont une copie figée : une modification future de la fiche salarié ne change pas ce contrat.</p>
    </div>

    <div x-show="tab === 'historique'" x-cloak class="bg-white rounded-xl border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Utilisateur</th>
                    <th class="px-4 py-3 text-left">Champ</th>
                    <th class="px-4 py-3 text-left">Ancienne valeur</th>
                    <th class="px-4 py-3 text-left">Nouvelle valeur</th>
                    <th class="px-4 py-3 text-left">Motif</th>
                </tr>
            </thead>
            <tbody>
                @forelse($audits as $log)
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $log->field ?: $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->old_value ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $log->new_value ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $log->reason ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucune modification tracée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</main>
@endsection
