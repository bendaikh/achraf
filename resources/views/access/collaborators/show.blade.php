@extends('layouts.with-sidebar')

@section('title', $collaborator->fullName())
@section('sidebar_page_title', 'Administration')

@section('main')
@php
    $tabs = [
        'profil' => 'Profil',
        'compte' => 'Compte',
        'commercial' => 'Commercial',
        'rh' => 'RH',
    ];
    if (! $collaborator->hasHrFile()) {
        unset($tabs['rh']);
    }
@endphp
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-start">
            <div>
                <a href="{{ route('access.collaborators.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Collaborateurs</a>
                <h1 class="text-3xl font-bold text-gray-900 mt-2">{{ $collaborator->fullName() }}</h1>
                <p class="text-gray-500 mt-1">{{ $collaborator->typeLabel() }}{{ $collaborator->job_title ? ' · '.$collaborator->job_title : '' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('access.collaborators.edit', $collaborator) }}" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-gray-800">Modifier</a>
                @unless($collaborator->user)
                    <a href="{{ route('access.users.create', ['collaborator_id' => $collaborator->id]) }}" class="px-4 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Créer un compte</a>
                @endunless
            </div>
        </div>

        @include('access.partials.flash')

        <div class="mb-6 flex flex-wrap gap-2 border-b border-gray-200">
            @foreach($tabs as $key => $label)
                <a href="{{ route('access.collaborators.show', ['collaborator' => $collaborator, 'tab' => $key]) }}"
                   class="px-4 py-2 text-sm font-medium border-b-2 {{ $tab === $key ? 'border-[#0a5d8a] text-[#0a5d8a]' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if($tab === 'profil')
            <div class="bg-white rounded-lg shadow p-6 grid gap-4 sm:grid-cols-2">
                <div><div class="text-xs text-gray-500">Type</div><div class="font-medium">{{ $collaborator->typeLabel() }}</div></div>
                <div><div class="text-xs text-gray-500">Statut</div><div><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $collaborator->statusBadgeClass() }}">{{ $collaborator->statusLabel() }}</span></div></div>
                <div><div class="text-xs text-gray-500">Email</div><div>{{ $collaborator->email ?? '—' }}</div></div>
                <div><div class="text-xs text-gray-500">Téléphone</div><div>{{ $collaborator->phone ?? '—' }}</div></div>
                <div><div class="text-xs text-gray-500">Fonction</div><div>{{ $collaborator->job_title ?? '—' }}</div></div>
                <div><div class="text-xs text-gray-500">Département</div><div>{{ $collaborator->department ?? '—' }}</div></div>
                <div><div class="text-xs text-gray-500">Équipe</div><div>{{ $collaborator->team ?? '—' }}</div></div>
                <div><div class="text-xs text-gray-500">Responsable</div><div>{{ $collaborator->manager?->fullName() ?? '—' }}</div></div>
                <div><div class="text-xs text-gray-500">Début</div><div>{{ $collaborator->start_date?->format('d/m/Y') ?? '—' }}</div></div>
                <div><div class="text-xs text-gray-500">Fin</div><div>{{ $collaborator->end_date?->format('d/m/Y') ?? '—' }}</div></div>
                @if($collaborator->notes)
                    <div class="sm:col-span-2"><div class="text-xs text-gray-500">Notes</div><div class="whitespace-pre-wrap">{{ $collaborator->notes }}</div></div>
                @endif
            </div>
        @elseif($tab === 'compte')
            <div class="bg-white rounded-lg shadow p-6">
                @if($collaborator->user)
                    <p class="text-gray-700 mb-4">Compte Libromart lié.</p>
                    <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                        <div><dt class="text-gray-500">Identifiant</dt><dd class="font-medium">{{ $collaborator->user->email }}</dd></div>
                        <div><dt class="text-gray-500">Rôle</dt><dd class="font-medium">{{ $collaborator->user->primaryRole?->name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Statut</dt><dd>{{ $collaborator->user->statusLabel() }}</dd></div>
                        <div><dt class="text-gray-500">Périmètre</dt><dd>{{ $collaborator->user->dataScopeLabel() }}</dd></div>
                        <div><dt class="text-gray-500">Dernière connexion</dt><dd>{{ $collaborator->user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    </dl>
                    <a href="{{ route('access.users.show', $collaborator->user) }}" class="inline-flex mt-4 text-blue-600 hover:text-blue-900 font-medium">Ouvrir le compte →</a>
                @else
                    <p class="text-gray-600 mb-4">Aucun accès logiciel. Un collaborateur peut exister sans compte Libromart.</p>
                    <a href="{{ route('access.users.create', ['collaborator_id' => $collaborator->id]) }}" class="inline-flex px-4 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Créer un compte utilisateur</a>
                @endif
            </div>
        @elseif($tab === 'commercial')
            <div class="bg-white rounded-lg shadow p-6 space-y-3">
                <p class="text-sm text-gray-700">Commercial : <strong>{{ $collaborator->is_commercial ? 'Oui' : 'Non' }}</strong></p>
                <p class="text-sm text-gray-500">Même moteur commercial pour salarié et freelance. Le commercial attribué suit Devis → BC → BL → Facture → Paiement → Commission.</p>
                @if($collaborator->is_commercial)
                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="{{ route('access.dashboard.commercial', ['collaborator_id' => $collaborator->id]) }}" class="text-blue-600 hover:text-blue-900 text-sm font-medium">Tableau de bord →</a>
                        <a href="{{ route('access.commissions.index', ['collaborator_id' => $collaborator->id]) }}" class="text-blue-600 hover:text-blue-900 text-sm font-medium">Commissions →</a>
                    </div>
                @endif
            </div>
        @elseif($tab === 'rh' && $collaborator->employee)
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-700 mb-3">Fiche RH associée (aucune duplication) :</p>
                <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">Matricule</dt><dd class="font-medium">{{ $collaborator->employee->matricule }}</dd></div>
                    <div><dt class="text-gray-500">Statut RH</dt><dd>{{ $collaborator->employee->statusLabel() }}</dd></div>
                    <div><dt class="text-gray-500">Service</dt><dd>{{ $collaborator->employee->department?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Entrée</dt><dd>{{ $collaborator->employee->hire_date?->format('d/m/Y') ?? '—' }}</dd></div>
                </dl>
                <a href="{{ route('hr.employees.show', $collaborator->employee) }}" class="inline-flex mt-4 text-blue-600 hover:text-blue-900 font-medium">Ouvrir la fiche salarié RH →</a>
            </div>
        @endif
    </div>
</main>
@endsection
