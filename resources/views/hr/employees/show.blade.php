@extends('layouts.with-sidebar')

@section('title', $employee->fullName())
@section('sidebar_page_title', 'RH')

@section('main')
@php
    $tabs = [
        'identite' => 'Identité',
        'contrat' => 'Contrat & ancienneté',
        'presence' => 'Présence / Pointage',
        'conges' => 'Congés / Absences',
        'documents' => 'Documents',
        'historique' => 'Historique',
        'sortie' => 'Sortie',
    ];
    if ($canSeeSalary ?? true) {
        $tabs = array_merge(
            array_slice($tabs, 0, 4, true),
            [
                'salaire' => 'Salaire',
                'primes' => 'Primes / Indemnités / Retenues',
                'paie' => 'Paie',
            ],
            array_slice($tabs, 4, null, true)
        );
    }
    $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
    $input = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm';
@endphp
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen bg-gray-50/80" x-data="{ tab: '{{ $tab }}' }">
    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto">
        <div class="mb-6">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('hr.employees.index') }}" class="hover:text-[#c9920f]">Salariés</a>
                <span>/</span>
                <span class="text-gray-900">{{ $employee->fullName() }}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-3">
                    @if($employee->photo_path)
                        <img src="{{ asset('storage/'.$employee->photo_path) }}" alt="" class="h-14 w-14 rounded-xl object-cover">
                    @else
                        <div class="h-14 w-14 rounded-xl bg-[#fdb819]/15 text-[#c9920f] flex items-center justify-center font-bold text-lg">{{ strtoupper(mb_substr($employee->last_name, 0, 1)) }}</div>
                    @endif
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-bold text-gray-900">{{ $employee->fullName() }}</h1>
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $employee->statusBadgeClass() }}">{{ $employee->statusLabel() }}</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ $employee->matricule }} · Entrée le {{ $employee->hire_date?->format('d/m/Y') }} · Fiche créée le {{ $employee->created_at?->format('d/m/Y') }}</p>
                    </div>
                </div>
                <a href="{{ route('hr.employees.edit', $employee) }}" class="inline-flex px-4 py-2 bg-[#fdb819] text-white rounded-lg font-semibold">Modifier</a>
            </div>
        </div>

        @include('hr.partials.flash')

        <nav class="flex gap-1 overflow-x-auto bg-white border border-gray-200 rounded-xl p-1 mb-6">
            @foreach($tabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-[#0a5d8a] text-white' : 'text-gray-600 hover:bg-gray-50'" class="whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium">{{ $label }}</button>
            @endforeach
        </nav>

        <div x-show="tab === 'identite'" x-cloak class="bg-white rounded-xl border border-gray-200 p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <p><span class="text-gray-500">CIN</span><br><strong>{{ $employee->cin ?: '—' }}</strong></p>
            <p><span class="text-gray-500">Naissance</span><br><strong>{{ $employee->birth_date?->format('d/m/Y') ?: '—' }}</strong></p>
            <p><span class="text-gray-500">Téléphone</span><br><strong>{{ $employee->phone ?: '—' }}</strong></p>
            <p><span class="text-gray-500">Email</span><br><strong>{{ $employee->email ?: '—' }}</strong></p>
            <p class="md:col-span-2"><span class="text-gray-500">Adresse</span><br><strong>{{ $employee->address ?: '—' }}</strong></p>
            <p><span class="text-gray-500">Fonction</span><br><strong>{{ $employee->job_title ?: '—' }}</strong></p>
            <p><span class="text-gray-500">Service</span><br><strong>{{ $employee->department?->name ?: '—' }}</strong></p>
            <p><span class="text-gray-500">Responsable</span><br><strong>{{ $employee->manager?->fullName() ?: '—' }}</strong></p>
            <p><span class="text-gray-500">Lieu</span><br><strong>{{ $employee->workplace ?: '—' }}</strong></p>
            <p><span class="text-gray-500">ID pointeuse</span><br><strong>{{ $employee->timeclock_external_id ?: '—' }}</strong></p>
            <p><span class="text-gray-500">Compte Libromart</span><br><strong>{{ $employee->user?->email ?: 'Non rattaché — le salarié RH n’est pas un compte utilisateur' }}</strong></p>
            <p><span class="text-gray-500">Commissions</span><br><strong>{{ $employee->commission_eligible ? 'Éligible (module ultérieur)' : 'Non' }}</strong></p>
            <p><span class="text-gray-500">CNSS / AMO</span><br><strong>{{ $employee->cnss_number ?: '—' }} / {{ $employee->amo_number ?: '—' }}</strong></p>
        </div>

        <div x-show="tab === 'contrat'" x-cloak class="space-y-6">
            <form method="POST" action="{{ route('hr.employees.contracts.store', $employee) }}" class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <select name="type" class="{{ $input }}" required>
                    @foreach(\App\Models\EmployeeContract::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                <input type="date" name="start_date" class="{{ $input }}" required>
                <input type="date" name="end_date" class="{{ $input }}" placeholder="Fin">
                <input type="text" name="job_title" class="{{ $input }}" placeholder="Fonction">
                <input type="text" name="workplace" class="{{ $input }}" placeholder="Lieu de travail">
                <input type="number" step="0.01" name="salary" class="{{ $input }}" placeholder="Salaire contractuel">
                <input type="date" name="trial_start_date" class="{{ $input }}" title="Début période d’essai">
                <input type="date" name="trial_end_date" class="{{ $input }}" title="Fin période d’essai">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="renew" value="1"> Renouveler le contrat en cours (l’ancien reste)</label>
                <button class="md:col-span-3 px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Ajouter un contrat</button>
            </form>
            <div class="bg-white rounded-xl border overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Type</th><th class="px-4 py-2 text-left">Début</th><th class="px-4 py-2 text-left">Fin</th><th class="px-4 py-2 text-left">Fonction</th><th class="px-4 py-2 text-left">Salaire</th><th class="px-4 py-2 text-left">Statut</th></tr></thead>
                    <tbody>
                        @forelse($employee->contracts as $contract)
                            <tr class="border-t"><td class="px-4 py-2">{{ $contract->typeLabel() }}</td><td class="px-4 py-2">{{ $contract->start_date?->format('d/m/Y') }}</td><td class="px-4 py-2">{{ $contract->end_date?->format('d/m/Y') ?: '—' }}</td><td class="px-4 py-2">{{ $contract->job_title }}</td><td class="px-4 py-2">{{ $contract->salary ? $fmt($contract->salary).' MAD' : '—' }}</td><td class="px-4 py-2">{{ $contract->statusLabel() }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Aucun contrat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'presence'" x-cloak class="space-y-6">
            <form method="POST" action="{{ route('hr.employees.schedule.update', $employee) }}" class="bg-white rounded-xl border p-5">
                @csrf
                @method('PUT')
                <h3 class="font-semibold mb-1">Horaires (versionnés)</h3>
                <p class="text-xs text-gray-500 mb-3">Une nouvelle date d’effet conserve l’ancien planning. Le pointage compare l’horaire applicable au jour concerné.</p>
                <label class="text-xs text-gray-500">Date d’effet</label>
                <input type="date" name="effective_from" value="{{ now()->toDateString() }}" class="{{ $input }} mb-3 max-w-xs">
                @php $currentSchedules = $employee->currentSchedules(); @endphp
                <div class="space-y-2">
                    @foreach(\App\Models\EmployeeSchedule::WEEKDAYS as $day => $label)
                        @php $row = $currentSchedules[$day] ?? $employee->schedules->firstWhere('weekday', $day); @endphp
                        <div class="grid grid-cols-5 gap-2 items-center text-sm">
                            <input type="hidden" name="days[{{ $day }}][weekday]" value="{{ $day }}">
                            <span>{{ $label }}</span>
                            <input type="time" name="days[{{ $day }}][start_time]" value="{{ $row?->start_time ? substr($row->start_time, 0, 5) : '' }}" class="{{ $input }}">
                            <input type="time" name="days[{{ $day }}][end_time]" value="{{ $row?->end_time ? substr($row->end_time, 0, 5) : '' }}" class="{{ $input }}">
                            <input type="number" name="days[{{ $day }}][break_minutes]" value="{{ $row?->break_minutes ?? 60 }}" class="{{ $input }}">
                            <label class="flex items-center gap-1"><input type="checkbox" name="days[{{ $day }}][is_off]" value="1" @checked($row?->is_off)> Repos</label>
                        </div>
                    @endforeach
                </div>
                <button class="mt-3 px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer les horaires</button>
            </form>
            <form method="POST" action="{{ route('hr.attendance.store') }}" class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="date" name="work_date" value="{{ now()->toDateString() }}" class="{{ $input }}" required>
                <input type="time" name="clock_in" class="{{ $input }}">
                <input type="time" name="clock_out" class="{{ $input }}">
                <select name="status" class="{{ $input }}">
                    @foreach(\App\Models\AttendanceRecord::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                <input type="text" name="correction_reason" class="{{ $input }}" placeholder="Motif si correction">
                <input type="text" name="notes" class="{{ $input }}" placeholder="Observation">
                <button class="md:col-span-3 px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer / corriger le pointage</button>
            </form>
            <div class="bg-white rounded-xl border overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-left">Entrée</th><th class="px-4 py-2 text-left">Sortie</th><th class="px-4 py-2 text-left">Durée</th><th class="px-4 py-2 text-left">Retard</th><th class="px-4 py-2 text-left">Départ ant.</th><th class="px-4 py-2 text-left">HS</th><th class="px-4 py-2 text-left">Statut</th><th class="px-4 py-2 text-left">Source</th></tr></thead>
                    <tbody>
                        @foreach($employee->attendanceRecords as $row)
                            <tr class="border-t"><td class="px-4 py-2">{{ $row->work_date?->format('d/m/Y') }}</td><td class="px-4 py-2">{{ $row->clock_in ? substr($row->clock_in,0,5) : '—' }}</td><td class="px-4 py-2">{{ $row->clock_out ? substr($row->clock_out,0,5) : '—' }}</td><td class="px-4 py-2">{{ $row->workedHoursLabel() }}</td><td class="px-4 py-2">{{ $row->late_minutes }} min</td><td class="px-4 py-2">{{ $row->early_minutes }} min</td><td class="px-4 py-2">{{ $row->overtime_minutes }} min</td><td class="px-4 py-2">{{ $row->statusLabel() }}{{ $row->is_incomplete ? ' · incomplet' : '' }}</td><td class="px-4 py-2">{{ $row->sourceLabel() }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'conges'" class="space-y-6">
            @php $solde = $employee->leaveBalanceSummary(); @endphp
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white rounded-xl border p-5"><p class="text-sm text-gray-500">Acquis</p><p class="text-2xl font-bold">{{ $fmt($solde['acquired']) }} j</p></div>
                <div class="bg-white rounded-xl border p-5"><p class="text-sm text-gray-500">Pris</p><p class="text-2xl font-bold">{{ $fmt($solde['taken']) }} j</p></div>
                <div class="bg-white rounded-xl border p-5"><p class="text-sm text-gray-500">Restant</p><p class="text-2xl font-bold">{{ $fmt($solde['remaining']) }} j</p></div>
            </div>
            <form method="POST" action="{{ route('hr.employees.leave-balance.store', $employee) }}" class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-4 gap-3">
                @csrf
                <select name="type" class="{{ $input }}">
                    <option value="initial">Solde repris</option>
                    <option value="accrual">Droits acquis</option>
                    <option value="carryover">Report</option>
                    <option value="adjustment">Ajustement</option>
                </select>
                <input type="number" step="0.5" name="days" class="{{ $input }}" placeholder="Jours (+/-)" required>
                <input type="date" name="entry_date" value="{{ $employee->hire_date?->format('Y-m-d') }}" class="{{ $input }}" required>
                <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Ajouter au ledger</button>
            </form>
            <div class="bg-white rounded-xl border overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-left">Type</th><th class="px-4 py-2 text-left">Jours</th><th class="px-4 py-2 text-left">Solde</th><th class="px-4 py-2 text-left">Note</th></tr></thead>
                    <tbody>
                        @foreach($employee->leaveBalanceEntries as $entry)
                            <tr class="border-t"><td class="px-4 py-2">{{ $entry->entry_date?->format('d/m/Y') }}</td><td class="px-4 py-2">{{ $entry->typeLabel() }}</td><td class="px-4 py-2">{{ $fmt($entry->days) }}</td><td class="px-4 py-2 font-medium">{{ $fmt($entry->balance_after) }}</td><td class="px-4 py-2">{{ $entry->notes }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('hr.leaves.store') }}" class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <select name="leave_type_id" class="{{ $input }}" required>
                    @foreach($leaveTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
                </select>
                <input type="date" name="start_date" class="{{ $input }}" required>
                <input type="date" name="end_date" class="{{ $input }}" required>
                <input type="number" step="0.5" name="days" class="{{ $input }}" placeholder="Nb jours (auto si vide)">
                <input type="text" name="comment" class="md:col-span-2 {{ $input }}" placeholder="Commentaire">
                <button class="md:col-span-3 px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Demander un congé</button>
            </form>
            <form method="POST" action="{{ route('hr.absences.store') }}" class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <select name="type" class="{{ $input }}">
                    @foreach(\App\Models\EmployeeAbsence::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                <input type="date" name="start_date" class="{{ $input }}" required>
                <input type="date" name="end_date" class="{{ $input }}" required>
                <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="impacts_payroll" value="1" checked> Impact sur la rémunération</label>
                <input type="text" name="comment" class="{{ $input }}" placeholder="Commentaire">
                <button class="md:col-span-3 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm">Enregistrer une absence</button>
            </form>
        </div>

        @if($canSeeSalary ?? true)
        <div x-show="tab === 'salaire'" class="space-y-6">
            <form method="POST" action="{{ route('hr.employees.salary.store', $employee) }}" class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-4 gap-3">
                @csrf
                <input type="date" name="effective_date" class="{{ $input }}" required>
                <input type="number" step="0.01" name="base_salary" class="{{ $input }}" placeholder="Montant" required>
                <select name="negotiated_as" class="{{ $input }}">
                    <option value="brut">Brut</option>
                    <option value="net">Net à payer</option>
                </select>
                <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Ajouter un salaire</button>
            </form>
            <div class="bg-white rounded-xl border overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Date d’effet</th><th class="px-4 py-2 text-left">Montant</th><th class="px-4 py-2 text-left">Type</th></tr></thead>
                    <tbody>
                        @foreach($employee->salaryRecords as $sal)
                            <tr class="border-t"><td class="px-4 py-2">{{ $sal->effective_date?->format('d/m/Y') }}</td><td class="px-4 py-2">{{ $fmt($sal->base_salary) }} MAD</td><td class="px-4 py-2">{{ \App\Models\SalaryRecord::NEGOTIATED_AS[$sal->negotiated_as] ?? $sal->negotiated_as }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'primes'" class="space-y-6">
            <form method="POST" action="{{ route('hr.compensations.store') }}" class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <select name="kind" class="{{ $input }}">
                    @foreach(\App\Models\CompensationItem::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                <select name="recurrence" class="{{ $input }}">
                    @foreach(\App\Models\CompensationItem::RECURRENCES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                <input type="number" step="0.01" name="amount" class="{{ $input }}" placeholder="Montant" required>
                <input type="date" name="start_date" class="{{ $input }}" required>
                <input type="date" name="end_date" class="{{ $input }}">
                <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Ajouter</button>
            </form>
            <form method="POST" action="{{ route('hr.adjustments.store') }}" class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <select name="type" class="{{ $input }}">
                    @foreach(\App\Models\PayrollAdjustment::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                <input type="number" step="0.01" name="amount" class="{{ $input }}" required>
                <input type="number" name="period_year" value="{{ now()->year }}" class="{{ $input }}">
                <input type="number" name="period_month" value="{{ now()->month }}" min="1" max="12" class="{{ $input }}">
                <input type="text" name="reason" class="{{ $input }}" placeholder="Motif">
                <input type="text" name="payment_method" class="{{ $input }}" placeholder="Moyen de paiement (avance)">
                <input type="text" name="reference" class="{{ $input }}" placeholder="Référence">
                <button class="px-4 py-2 bg-white border rounded-lg text-sm">Retenue / avance</button>
            </form>
            <ul class="bg-white rounded-xl border divide-y text-sm">
                @foreach($employee->compensationItems as $item)
                    <li class="px-4 py-3">{{ $item->kindLabel() }} — {{ $fmt($item->amount) }} MAD — {{ $item->recurrence }} dès {{ $item->start_date?->format('d/m/Y') }}</li>
                @endforeach
            </ul>
        </div>

        <div x-show="tab === 'paie'" class="bg-white rounded-xl border overflow-hidden">
            <div class="p-4 flex justify-between items-center">
                <h3 class="font-semibold">Bulletins</h3>
                <a href="{{ route('hr.payroll.simulate', ['employee_id' => $employee->id, 'year' => now()->year, 'month' => now()->month]) }}" class="text-sm text-[#0a5d8a]">Simulation</a>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-2 text-left">Période</th><th class="px-4 py-2 text-left">Brut</th><th class="px-4 py-2 text-left">Net</th><th class="px-4 py-2 text-left">Coût employeur</th><th class="px-4 py-2 text-left">Bulletin</th></tr></thead>
                <tbody>
                    @forelse($employee->payrollSlips as $slip)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $slip->run?->periodLabel() }}</td>
                            <td class="px-4 py-2">{{ $fmt($slip->gross) }}</td>
                            <td class="px-4 py-2">{{ $fmt($slip->net) }}</td>
                            <td class="px-4 py-2">{{ $fmt($slip->employer_cost) }}</td>
                            <td class="px-4 py-2 space-x-2">
                                <a class="text-[#0a5d8a] text-sm" href="{{ route('hr.payroll.slip.print', $slip) }}" target="_blank">Voir</a>
                                <a class="text-[#0a5d8a] text-sm" href="{{ route('hr.payroll.slip.pdf', $slip) }}">PDF</a>
                                <a class="text-[#0a5d8a] text-sm" href="{{ route('hr.payroll.slip.print', $slip) }}" target="_blank">Imprimer</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Aucune paie calculée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        <div x-show="tab === 'documents'" class="bg-white rounded-xl border p-5 space-y-4">
            <form action="{{ route('document-files.store', ['type' => 'hr-employees', 'id' => $employee->id]) }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                @csrf
                <select name="category" class="{{ $input }}">
                    @foreach(\App\Support\DocumentAttachmentRegistry::get('hr-employees')['categories'] as $k => $l)
                        <option value="{{ $k }}">{{ $l }}</option>
                    @endforeach
                </select>
                <input type="date" name="expires_at" class="{{ $input }}" title="Expiration">
                <input type="hidden" name="source" value="upload">
                <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required class="{{ $input }}">
                <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">+ Ajouter / Téléverser</button>
            </form>
            <ul class="divide-y text-sm">
                @forelse($employee->documents as $doc)
                    <li class="py-3 flex flex-wrap justify-between gap-2">
                        <span>{{ $doc->document_type_label }} · {{ $doc->expires_at ? 'exp. '.$doc->expires_at->format('d/m/Y') : 'sans expiration' }}</span>
                        <span class="space-x-2">
                            <a class="text-[#0a5d8a]" href="{{ route('managed-documents.show', $doc) }}" target="_blank">Voir</a>
                            <a class="text-[#0a5d8a]" href="{{ route('managed-documents.download', $doc) }}">Télécharger</a>
                            <a class="text-[#0a5d8a]" href="{{ route('managed-documents.history', $doc) }}">Historique</a>
                        </span>
                    </li>
                @empty
                    <li class="py-4 text-gray-500">Aucun document.</li>
                @endforelse
            </ul>
        </div>

        <div x-show="tab === 'historique'" class="bg-white rounded-xl border divide-y">
            @forelse($employee->events as $event)
                <div class="px-5 py-4">
                <p class="text-xs text-gray-500">{{ $event->created_at?->format('d/m/Y H:i') }} · {{ $event->user?->name }}</p>
                    <p class="font-medium text-gray-900">{{ $event->title }}</p>
                    @if($event->description)<p class="text-sm text-gray-600">{{ $event->description }}</p>@endif
                </div>
            @empty
                <p class="px-5 py-8 text-center text-gray-500">Aucun événement.</p>
            @endforelse
        </div>

        <div x-show="tab === 'sortie'" class="bg-white rounded-xl border p-5">
            @if($employee->exitRecord)
                <p class="text-sm">Sorti le <strong>{{ $employee->exitRecord->exit_date?->format('d/m/Y') }}</strong> — {{ $employee->exitRecord->reason ?: '—' }}</p>
                    <p class="text-sm text-gray-500 mt-2">Congés restants à la sortie : {{ $fmt($employee->currentLeaveBalance()) }} j. Avances : {{ $employee->payrollAdjustments->where('type', 'avance')->count() }}.</p>
            @else
                <form method="POST" action="{{ route('hr.employees.exit', $employee) }}" class="grid grid-cols-1 md:grid-cols-2 gap-3" onsubmit="return confirm('Confirmer la sortie du salarié ? Le dossier sera conservé.');">
                    @csrf
                    <input type="date" name="exit_date" class="{{ $input }}" required>
                    <input type="date" name="last_work_date" class="{{ $input }}">
                    <input type="text" name="reason" class="{{ $input }}" placeholder="Motif">
                    <input type="number" step="0.5" name="leave_balance_settlement" class="{{ $input }}" placeholder="Solde de congés">
                    <textarea name="notes" class="md:col-span-2 {{ $input }}" placeholder="Éléments de paie restants, observations"></textarea>
                    <button class="md:col-span-2 px-4 py-2 bg-red-600 text-white rounded-lg text-sm">Enregistrer la sortie</button>
                </form>
            @endif
        </div>
    </div>
</main>
@endsection
