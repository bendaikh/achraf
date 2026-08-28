@extends('layouts.with-sidebar')
@section('title', 'Paramètres RH')
@section('sidebar_page_title', 'RH')
@section('main')
@php $input = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm'; @endphp
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8 max-w-5xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Paramètres RH</h1>
    <p class="text-gray-500 mb-6">Alertes, services, types de congés et versions de règles de paie (CNSS / AMO / IR) par date d’effet.</p>
    @include('hr.partials.flash')

    <section class="bg-white rounded-xl border p-5 mb-6">
        <h2 class="font-semibold mb-3">Délais d’alertes (jours)</h2>
        <form method="POST" action="{{ route('hr.settings.alerts') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            @csrf
            <div><label class="text-xs text-gray-500">Contrat</label><input type="number" name="contract" value="{{ $alerts['contract'] }}" class="{{ $input }}"></div>
            <div><label class="text-xs text-gray-500">Période d’essai</label><input type="number" name="trial" value="{{ $alerts['trial'] }}" class="{{ $input }}"></div>
            <div><label class="text-xs text-gray-500">Documents</label><input type="number" name="document" value="{{ $alerts['document'] }}" class="{{ $input }}"></div>
        </form>
    </section>

    <section class="bg-white rounded-xl border p-5 mb-6">
        <h2 class="font-semibold mb-3">Pointage, fonctions et lieux</h2>
        <form method="POST" action="{{ route('hr.settings.options') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <div><label class="text-xs text-gray-500">Seuil de retard (min)</label><input type="number" name="late_threshold" value="{{ $lateThreshold }}" class="{{ $input }}"></div>
            <div><label class="text-xs text-gray-500">Fonctions (une par ligne)</label><textarea name="job_titles" rows="4" class="{{ $input }}">{{ $jobTitles }}</textarea></div>
            <div><label class="text-xs text-gray-500">Lieux de travail (une par ligne)</label><textarea name="workplaces" rows="4" class="{{ $input }}">{{ $workplaces }}</textarea></div>
            <button class="md:col-span-3 px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer</button>
        </form>
    </section>

    <section class="bg-white rounded-xl border p-5 mb-6">
        <h2 class="font-semibold mb-3">Droits d’accès RH</h2>
        <p class="text-xs text-gray-500 mb-3">Sans restriction (case cochée), l’utilisateur conserve l’accès complet. Sinon, seuls les droits cochés s’appliquent. Un utilisateur sans « Voir salaires » ne consulte pas la paie.</p>
        @foreach($users as $user)
            <form method="POST" action="{{ route('hr.settings.permissions', $user) }}" class="border-t py-3 text-sm">
                @csrf
                <div class="flex flex-wrap items-center gap-3 mb-2">
                    <strong>{{ $user->name }}</strong>
                    <span class="text-gray-500">{{ $user->email }}</span>
                    <label class="flex items-center gap-1"><input type="checkbox" name="unrestricted" value="1" @checked($user->hr_permissions === null)> Accès RH complet</label>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach($permissionLabels as $key => $label)
                        <label class="flex items-center gap-1"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(is_array($user->hr_permissions) && in_array($key, $user->hr_permissions, true))> {{ $label }}</label>
                    @endforeach
                </div>
                <button class="mt-2 px-3 py-1 border rounded text-xs">Enregistrer</button>
            </form>
        @endforeach
    </section>

    <section class="bg-white rounded-xl border p-5 mb-6">
        <h2 class="font-semibold mb-3">Services / départements</h2>
        <form method="POST" action="{{ route('hr.settings.departments.store') }}" class="flex gap-3 mb-3">
            @csrf
            <input type="text" name="name" class="{{ $input }}" placeholder="Nom" required>
            <input type="text" name="code" class="{{ $input }}" placeholder="Code">
            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm shrink-0">Ajouter</button>
        </form>
        <ul class="text-sm text-gray-700 space-y-1">
            @foreach($departments as $dep)<li>{{ $dep->name }} {{ $dep->code ? '('.$dep->code.')' : '' }}</li>@endforeach
        </ul>
    </section>

    <section class="bg-white rounded-xl border p-5 mb-6">
        <h2 class="font-semibold mb-3">Types de congés</h2>
        <form method="POST" action="{{ route('hr.settings.leave-types.store') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-3">
            @csrf
            <input type="text" name="name" class="{{ $input }}" placeholder="Nom" required>
            <input type="text" name="code" class="{{ $input }}" placeholder="code" required>
            <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="impacts_balance" value="1" checked> Solde</label>
            <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="paid" value="1" checked> Payé</label>
            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Ajouter</button>
        </form>
        <ul class="text-sm space-y-1">@foreach($leaveTypes as $type)<li>{{ $type->name }} ({{ $type->code }})</li>@endforeach</ul>
    </section>

    <section class="bg-white rounded-xl border p-5">
        <h2 class="font-semibold mb-1">Paramètres de paie (nouvelle version)</h2>
        <p class="text-xs text-gray-500 mb-3">Ne modifie jamais rétroactivement une ancienne paie. Les barèmes IR de la dernière version sont repris ; ajustez-les ensuite si besoin via une nouvelle version.</p>
        <form method="POST" action="{{ route('hr.settings.rule-sets.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <input type="text" name="name" class="{{ $input }}" placeholder="Nom de la version" required>
            <input type="date" name="effective_from" class="{{ $input }}" required>
            <input type="number" step="0.01" name="monthly_hours" value="191" class="{{ $input }}" placeholder="Heures / mois">
            <input type="number" step="0.01" name="overtime_multiplier" value="1.25" class="{{ $input }}" placeholder="Majoration HS">
            <input type="number" step="0.01" name="employee_cnss_rate" value="4.48" class="{{ $input }}" placeholder="CNSS sal. %">
            <input type="number" step="0.01" name="employer_cnss_rate" value="8.98" class="{{ $input }}" placeholder="CNSS pat. %">
            <input type="number" step="0.01" name="employee_amo_rate" value="2.26" class="{{ $input }}" placeholder="AMO sal. %">
            <input type="number" step="0.01" name="employer_amo_rate" value="2.26" class="{{ $input }}" placeholder="AMO pat. %">
            <input type="number" step="0.01" name="professional_expenses_rate" value="20" class="{{ $input }}" placeholder="Frais pro %">
            <input type="number" step="0.01" name="professional_expenses_cap" value="2500" class="{{ $input }}" placeholder="Plafond frais pro">
            <button class="md:col-span-3 px-4 py-2 bg-[#fdb819] text-white rounded-lg font-semibold">Créer une version</button>
        </form>
        <ul class="mt-4 text-sm divide-y">
            @foreach($ruleSets as $set)
                <li class="py-2"><strong>{{ $set->name }}</strong> — à partir du {{ $set->effective_from?->format('d/m/Y') }}</li>
            @endforeach
        </ul>
    </section>
</main>
@endsection
