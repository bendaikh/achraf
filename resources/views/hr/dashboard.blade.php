@extends('layouts.with-sidebar')

@section('title', 'Tableau de bord RH')
@section('sidebar_page_title', 'RH')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tableau de bord RH</h1>
                <p class="text-gray-500 mt-1">Vue globale des salariés, présences, congés, paie et alertes.</p>
            </div>
            <a href="{{ route('hr.employees.create') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-[#fdb819] text-white rounded-lg font-semibold hover:bg-[#e5a617]">Ajouter un salarié</a>
        </div>

        @include('hr.partials.flash')

        <form method="GET" action="{{ route('hr.dashboard') }}" class="bg-white rounded-xl border border-gray-200 p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-7 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Mois</label>
                <select name="month" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) ($filters['month'] ?? now()->month) === $m)>{{ sprintf('%02d', $m) }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Année</label>
                <input type="number" name="year" value="{{ $filters['year'] ?? now()->year }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Salarié</label>
                <select name="employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $emp->id)>{{ $emp->matricule }} — {{ $emp->fullName() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Service</label>
                <select name="department_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach($departments as $dep)
                        <option value="{{ $dep->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $dep->id)>{{ $dep->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Fonction</label>
                <select name="job_title" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Toutes</option>
                    @foreach($jobTitles as $title)
                        <option value="{{ $title }}" @selected(($filters['job_title'] ?? '') === $title)>{{ $title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Statut</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach(\App\Models\Employee::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="flex-1 px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Filtrer</button>
                <a href="{{ route('hr.dashboard') }}" class="px-3 py-2 border rounded-lg text-sm">Reset</a>
            </div>
        </form>

        @php
            $cards = [
                ['Nombre total de salariés', $stats['total'], null],
                ['Salariés actifs', $stats['actifs'], 'text-emerald-700'],
                ['Salariés sortis', $stats['sortis'], 'text-gray-600'],
                ['Nouvelles embauches', $stats['embauches'], null],
                ['Contrats à échéance', $stats['contrats_echeance'], 'text-amber-700'],
                ['Présents aujourd\'hui', $stats['presents_today'], 'text-emerald-700'],
                ['Absents aujourd\'hui', $stats['absents_today'], 'text-red-700'],
                ['En congé aujourd\'hui', $stats['conges_today'], null],
                ['Retards (période)', $stats['retards'], 'text-amber-700'],
                ['Congés à valider', $stats['conges_a_valider'], 'text-amber-700'],
                ['Documents à expiration', $stats['documents_expiration'], 'text-amber-700'],
                ['Masse salariale du mois', number_format($stats['masse_salariale'], 2, ',', ' ').' MAD', null],
                ['Primes / indemnités du mois', number_format($stats['primes_mois'], 2, ',', ' ').' MAD', null],
                ['Coût employeur du mois', number_format($stats['cout_employeur'], 2, ',', ' ').' MAD', null],
            ];
        @endphp

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-8">
            @foreach($cards as [$label, $value, $tone])
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-xl font-bold {{ $tone ?: 'text-gray-900' }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @if(($stats['alerts'] ?? []) !== [])
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-8">
                <h2 class="font-semibold text-amber-900 mb-3">Alertes RH</h2>
                <ul class="space-y-2">
                    @foreach($stats['alerts'] as $alert)
                        <li>
                            <a href="{{ $alert['url'] }}" class="text-sm text-amber-900 hover:underline">
                                ⚠️ {{ $alert['label'] }} — {{ $alert['count'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-2">Rapports RH</h2>
            <p class="text-sm text-gray-600 mb-3">Exports Excel / PDF : salariés, contrats, présences, retards, absences, HS, congés, primes, avances, paies, masse salariale, coût employeur, documents à expiration.</p>
            <a href="{{ route('hr.reports.index') }}" class="inline-flex px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Ouvrir les rapports</a>
        </div>
    </div>
</main>
@endsection
