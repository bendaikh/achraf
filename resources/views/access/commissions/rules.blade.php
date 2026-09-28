@extends('layouts.with-sidebar')

@section('title', 'Règles de commission')
@section('sidebar_page_title', 'Gestion ventes')

@php
    $isEditing = $editing !== null;
    $formAction = $isEditing
        ? route('access.commissions.rules.update', $editing)
        : route('access.commissions.rules.store');
@endphp

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl">
        <div class="mb-6">
            <a href="{{ route('access.commissions.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Commissions</a>
            <h1 class="text-3xl font-bold text-gray-900 mt-2">Règles de commission</h1>
            <p class="text-gray-500 mt-1">Une règle par commercial, ou une règle par défaut pour tous. Jamais de cumul automatique sur une commande.</p>
        </div>

        @include('access.partials.flash')

        @if(session('archive_rule_id'))
            @php $archiveTarget = $rules->firstWhere('id', (int) session('archive_rule_id')); @endphp
            @if($archiveTarget && ! $archiveTarget->isArchived())
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 flex flex-wrap items-center justify-between gap-3">
                    <p>Cette règle a déjà servi pour des commissions. Vous pouvez l’archiver pour la retirer des nouvelles commissions tout en conservant l’historique.</p>
                    <form method="POST" action="{{ route('access.commissions.rules.archive', $archiveTarget) }}" class="inline" onsubmit="return confirm('Archiver cette règle ? L’historique des commissions sera conservé.');">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg font-semibold hover:bg-amber-800">
                            Archiver « {{ $archiveTarget->name }} »
                        </button>
                    </form>
                </div>
            @endif
        @endif

        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="flex items-center justify-between gap-4 mb-4">
                <h2 class="font-semibold text-gray-900">{{ $isEditing ? 'Modifier la règle' : 'Nouvelle règle' }}</h2>
                @if($isEditing)
                    <a href="{{ route('access.commissions.rules') }}" class="text-sm text-gray-600 hover:text-gray-900">Annuler</a>
                @endif
            </div>
            <form method="POST" action="{{ $formAction }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @if($isEditing)
                    @method('PUT')
                @endif
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Nom</label>
                    <input type="text" name="name" required value="{{ old('name', $editing?->name) }}" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Commercial concerné</label>
                    <select name="collaborator_id" class="w-full px-3 py-2 border rounded-lg">
                        <option value="" @selected(old('collaborator_id', $editing?->collaborator_id) === null || old('collaborator_id', $editing?->collaborator_id) === '')>Tous les commerciaux</option>
                        @foreach($collaborators as $commercial)
                            <option value="{{ $commercial->id }}" @selected((string) old('collaborator_id', $editing?->collaborator_id) === (string) $commercial->id)>
                                {{ $commercial->fullName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Règle par défaut</label>
                    <select name="is_default" class="w-full px-3 py-2 border rounded-lg">
                        <option value="0" @selected(! old('is_default', $editing?->is_default))>Non</option>
                        <option value="1" @selected((bool) old('is_default', $editing?->is_default))>Oui</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Utilisée si le commercial n’a pas de règle spécifique.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Type</label>
                    <select name="type" class="w-full px-3 py-2 border rounded-lg">
                        @foreach(\App\Models\CommissionRule::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $editing?->type ?? 'percent_ca') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Base</label>
                    <select name="base" class="w-full px-3 py-2 border rounded-lg">
                        @foreach(\App\Models\CommissionRule::BASES as $value => $label)
                            <option value="{{ $value }}" @selected(old('base', $editing?->base ?? 'ca_ht') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Taux %</label>
                    <input type="number" step="0.01" name="rate" value="{{ old('rate', $editing?->rate ?? 3) }}" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Montant fixe</label>
                    <input type="number" step="0.01" name="fixed_amount" value="{{ old('fixed_amount', $editing?->fixed_amount) }}" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Déclencheur</label>
                    <select name="trigger" class="w-full px-3 py-2 border rounded-lg">
                        @foreach(\App\Models\CommissionRule::TRIGGERS as $value => $label)
                            <option value="{{ $value }}" @selected(old('trigger', $editing?->trigger ?? 'delivered_paid') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Statut</label>
                    <select name="is_active" class="w-full px-3 py-2 border rounded-lg">
                        <option value="1" @selected((bool) old('is_active', $editing?->is_active ?? true))>Actif</option>
                        <option value="0" @selected(! (bool) old('is_active', $editing?->is_active ?? true))>Inactif</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="px-5 py-2 bg-[#fdb819] text-white rounded-lg font-semibold">
                        {{ $isEditing ? 'Enregistrer les modifications' : 'Créer' }}
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Nom</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Commercial</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Type / Base</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Taux / Fixe</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Déclencheur</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Défaut</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Actif</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rules as $rule)
                        <tr class="{{ $rule->isArchived() ? 'bg-gray-50 text-gray-500' : '' }} {{ $editing && $editing->id === $rule->id ? 'bg-amber-50' : '' }}">
                            <td class="px-4 py-3 text-sm font-medium">
                                {{ $rule->name }}
                                @if($rule->isArchived())
                                    <span class="ml-1 text-xs font-normal text-gray-400">(archivée)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $rule->commercialLabel() }}</td>
                            <td class="px-4 py-3 text-sm">{{ $rule->typeBaseLabel() }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($rule->type === 'fixed')
                                    {{ number_format((float) $rule->fixed_amount, 2, ',', ' ') }} DH
                                @else
                                    {{ rtrim(rtrim(number_format((float) $rule->rate, 2, ',', ' '), '0'), ',') }} %
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $rule->triggerLabel() }}</td>
                            <td class="px-4 py-3 text-sm">{{ $rule->is_default ? 'Oui' : 'Non' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $rule->is_active ? 'Oui' : 'Non' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if(! $rule->isArchived())
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('access.commissions.rules', ['edit' => $rule->id]) }}" class="text-blue-600 hover:text-blue-800 font-medium">Modifier</a>
                                        <form method="POST" action="{{ route('access.commissions.rules.toggle', $rule) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-gray-700 hover:text-gray-900 font-medium">
                                                {{ $rule->is_active ? 'Désactiver' : 'Activer' }}
                                            </button>
                                        </form>
                                        @if($rule->commissions_count > 0)
                                            <form method="POST" action="{{ route('access.commissions.rules.archive', $rule) }}" class="inline" onsubmit="return confirm('Archiver cette règle ? L’historique des commissions sera conservé.');">
                                                @csrf
                                                <button type="submit" class="text-amber-700 hover:text-amber-900 font-medium">Archiver</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('access.commissions.rules.destroy', $rule) }}" class="inline" onsubmit="return confirm(@json($rule->commissions_count > 0 ? 'Cette règle a déjà été utilisée. La suppression sera refusée — confirmer pour afficher les options d’archivage ?' : 'Supprimer définitivement cette règle ?'));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Supprimer</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Archivée — non proposée pour les nouvelles commissions</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">Aucune règle.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
