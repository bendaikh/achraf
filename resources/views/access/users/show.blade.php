@extends('layouts.with-sidebar')

@section('title', $user->name)
@section('sidebar_page_title', 'Administration')

@section('main')
@php
    $tabs = [
        'compte' => 'Compte',
        'acces' => 'Rôle & accès',
        'perimetre' => 'Périmètre',
        'collaborateur' => 'Collaborateur',
    ];
@endphp
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-start">
            <div>
                <a href="{{ route('access.users.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Utilisateurs</a>
                <h1 class="text-3xl font-bold text-gray-900 mt-2">{{ $user->name }}</h1>
                <p class="text-gray-500 mt-1">{{ $user->email }} · {{ $user->primaryRole?->name ?? 'Sans rôle' }}</p>
            </div>
            <a href="{{ route('access.users.edit', $user) }}" class="px-4 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Modifier</a>
        </div>

        @include('access.partials.flash')

        <div class="mb-6 flex flex-wrap gap-2 border-b border-gray-200">
            @foreach($tabs as $key => $label)
                <a href="{{ route('access.users.show', ['user' => $user, 'tab' => $key]) }}"
                   class="px-4 py-2 text-sm font-medium border-b-2 {{ $tab === $key ? 'border-[#0a5d8a] text-[#0a5d8a]' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            @if($tab === 'compte')
                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">Statut</dt><dd class="font-medium">{{ $user->statusLabel() }}</dd></div>
                    <div><dt class="text-gray-500">Dernière connexion</dt><dd>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Activé le</dt><dd>{{ $user->activated_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">2FA</dt><dd>{{ $user->two_factor_enabled ? 'Activé' : 'Non (phase ultérieure)' }}</dd></div>
                </dl>
            @elseif($tab === 'acces')
                <p class="text-sm text-gray-600 mb-4">Rôle modèle : <strong>{{ $user->primaryRole?->name ?? '—' }}</strong>. Permissions effectives (rôle + personnalisation) :</p>
                <div class="flex flex-wrap gap-2">
                    @forelse($effectiveKeys as $key)
                        <span class="inline-flex px-2 py-1 rounded bg-gray-100 text-xs text-gray-700">{{ $key }}</span>
                    @empty
                        <span class="text-gray-500 text-sm">Aucune permission.</span>
                    @endforelse
                </div>
            @elseif($tab === 'perimetre')
                <p class="text-sm mb-3">Périmètre : <strong>{{ $user->dataScopeLabel() }}</strong></p>
                @if($user->warehouses->isNotEmpty())
                    <p class="text-xs text-gray-500 mb-2">Dépôts :</p>
                    <ul class="list-disc pl-5 text-sm text-gray-800">
                        @foreach($user->warehouses as $warehouse)
                            <li>{{ $warehouse->name }}</li>
                        @endforeach
                    </ul>
                @endif
            @elseif($tab === 'collaborateur')
                @if($user->collaborator)
                    <p class="text-sm text-gray-700 mb-2">{{ $user->collaborator->fullName() }} · {{ $user->collaborator->typeLabel() }}</p>
                    <a href="{{ route('access.collaborators.show', $user->collaborator) }}" class="text-blue-600 hover:text-blue-900 font-medium text-sm">Ouvrir la fiche collaborateur →</a>
                @else
                    <p class="text-sm text-gray-500">Aucun collaborateur lié.</p>
                @endif
            @endif
        </div>
    </div>
</main>
@endsection
