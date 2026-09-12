@extends('layouts.with-sidebar')
@section('title', 'Présences & Pointage')
@section('sidebar_page_title', 'RH')
@section('main')
@php
    $statusOptions = \App\Models\AttendanceRecord::ENTRY_STATUSES;
    $badge = fn (string $s) => \App\Models\AttendanceRecord::statusBadgeClass($s);
@endphp
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8 bg-slate-50/60" x-data="attendancePage(@js([
    'mode' => $mode,
    'days' => $days,
    'summary' => $summary,
    'statuses' => $statusOptions,
]))">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Présences & Pointage</h1>
        <p class="text-gray-500 mt-1">Saisie journalière ou mensuelle pour un suivi complet et simplifié.</p>
    </div>

    @include('hr.partials.flash')

    {{-- Mode toggle --}}
    <div class="inline-flex rounded-xl border border-gray-200 bg-white p-1 shadow-sm mb-6">
        <a href="{{ route('hr.attendance.index', ['mode' => 'daily']) }}"
           class="px-5 py-2.5 rounded-lg text-sm font-semibold transition {{ $mode === 'daily' ? 'bg-[#fdb819] text-gray-900 shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">
            Saisie journalière
        </a>
        <a href="{{ route('hr.attendance.index', array_filter(['mode' => 'monthly', 'employee_id' => $employeeId, 'month' => $month, 'year' => $year])) }}"
           class="px-5 py-2.5 rounded-lg text-sm font-semibold transition {{ $mode === 'monthly' ? 'bg-[#fdb819] text-gray-900 shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">
            Saisie mensuelle
        </a>
    </div>

    @if($mode === 'daily')
        {{-- Daily entry --}}
        <form method="POST" action="{{ route('hr.attendance.store') }}" class="bg-white rounded-xl border border-gray-200 p-5 mb-6 shadow-sm">
            @csrf
            <h2 class="text-sm font-semibold text-gray-800 mb-4">Enregistrer un pointage</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Salarié</label>
                    <select name="employee_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" required>
                        <option value="">Sélectionner…</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" @selected(old('employee_id') == $emp->id)>{{ $emp->fullName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Date</label>
                    <input type="date" name="work_date" value="{{ old('work_date', now()->toDateString()) }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Heure entrée</label>
                    <input type="time" name="clock_in" value="{{ old('clock_in') }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Heure sortie</label>
                    <input type="time" name="clock_out" value="{{ old('clock_out') }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Statut</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        @foreach(\App\Models\AttendanceRecord::STATUSES as $k => $l)
                            <option value="{{ $k }}" @selected(old('status', 'present') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Motif (si correction)</label>
                    <input type="text" name="correction_reason" value="{{ old('correction_reason') }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="Obligatoire si déjà saisi">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-[#0a5d8a] hover:bg-[#084d72] text-white rounded-lg text-sm font-semibold">Enregistrer</button>
            </div>
        </form>

        <div class="flex flex-wrap gap-2 mb-4">
            @foreach(['today' => 'Aujourd\'hui', 'week' => 'Cette semaine', 'month' => 'Ce mois', 'custom' => 'Période'] as $key => $label)
                <a href="{{ route('hr.attendance.index', ['mode' => 'daily', 'preset' => $key]) }}"
                   class="px-3 py-1.5 rounded-lg text-sm {{ ($preset ?? '') === $key ? 'bg-[#0a5d8a] text-white' : 'bg-white border border-gray-200 text-gray-700' }}">{{ $label }}</a>
            @endforeach
        </div>

        <x-table-filters :action="route('hr.attendance.index')" search-placeholder="Salarié..." grid-cols="md:grid-cols-5">
            <input type="hidden" name="mode" value="daily">
            <input type="hidden" name="preset" value="custom">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach(\App\Models\AttendanceRecord::STATUSES as $k => $l)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </x-table-filters>

        <x-table-list-toolbar table-id="hr-attendance" />
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
            <table data-lm-table="hr-attendance" class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Salarié</th>
                        <th class="px-4 py-3 text-left">Entrée</th>
                        <th class="px-4 py-3 text-left">Sortie</th>
                        <th class="px-4 py-3 text-left">Durée</th>
                        <th class="px-4 py-3 text-left">Retard</th>
                        <th class="px-4 py-3 text-left">HS</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Source</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $row)
                        <tr class="border-t border-gray-100">
                            <td class="px-4 py-3">{{ $row->work_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $row->employee?->fullName() }}</td>
                            <td class="px-4 py-3">{{ $row->clock_in ? substr($row->clock_in,0,5) : '—' }}</td>
                            <td class="px-4 py-3">{{ $row->clock_out ? substr($row->clock_out,0,5) : '—' }}</td>
                            <td class="px-4 py-3">{{ $row->workedHoursLabel() }}</td>
                            <td class="px-4 py-3 {{ $row->late_minutes > 0 ? 'text-amber-600 font-medium' : '' }}">{{ \App\Models\AttendanceRecord::minutesLabel((int) $row->late_minutes) }}</td>
                            <td class="px-4 py-3">{{ \App\Models\AttendanceRecord::minutesLabel((int) $row->overtime_minutes) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $badge($row->status) }}">{{ $row->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $row->sourceLabel() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-gray-500">Aucun pointage.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($records)
                <div class="px-4 py-3 border-t border-gray-100">{{ $records->links() }}</div>
            @endif
        </div>
    @else
        {{-- Monthly entry --}}
        <div class="grid grid-cols-1 xl:grid-cols-[1fr_280px] gap-6 mb-4">
            <form method="GET" action="{{ route('hr.attendance.index') }}" class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                <input type="hidden" name="mode" value="monthly">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Salarié</label>
                        <select name="employee_id" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm font-medium" required>
                            @forelse($employees as $emp)
                                <option value="{{ $emp->id }}" @selected((int) $employeeId === (int) $emp->id)>{{ $emp->fullName() }}</option>
                            @empty
                                <option value="">Aucun salarié actif</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="w-36">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Mois</label>
                        <select name="month" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                            @foreach($monthNames as $num => $label)
                                <option value="{{ $num }}" @selected((int) $month === (int) $num)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-28">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Année</label>
                        <select name="year" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                            @for($y = now()->year + 1; $y >= now()->year - 5; $y--)
                                <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('hr.attendance.index', ['mode' => 'monthly', 'employee_id' => $employeeId, 'month' => $prevMonth, 'year' => $prevYear]) }}"
                           class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" title="Mois précédent">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                        <button type="submit" class="px-5 py-2.5 bg-[#0a5d8a] hover:bg-[#084d72] text-white rounded-lg text-sm font-semibold whitespace-nowrap">
                            Charger le mois
                        </button>
                        <a href="{{ route('hr.attendance.index', ['mode' => 'monthly', 'employee_id' => $employeeId, 'month' => $nextMonth, 'year' => $nextYear]) }}"
                           class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" title="Mois suivant">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </form>

            <div class="bg-sky-50 border border-sky-200 rounded-xl p-4 text-sm text-sky-900 leading-relaxed">
                <p class="font-semibold mb-1">Info</p>
                <p>Les congés et absences validés dans les autres modules apparaissent automatiquement ici pour éviter la double saisie.</p>
            </div>
        </div>

        @if($employee)
            {{-- Summary --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-4 mb-4 overflow-x-auto">
                <div class="flex flex-wrap gap-x-8 gap-y-3 min-w-max" x-show="summary">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-gray-500">Jours théoriques</p>
                            <p class="text-lg font-bold text-gray-900" x-text="summary.theoretical_work_days"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-gray-500">Jours travaillés</p>
                            <p class="text-lg font-bold text-gray-900" x-text="summary.worked_days"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-gray-500">Présence</p>
                            <p class="text-lg font-bold text-gray-900" x-text="summary.present_days"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-gray-500">Heures travaillées</p>
                            <p class="text-lg font-bold text-gray-900" x-text="summary.worked_hours"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-gray-500">Congés</p>
                            <p class="text-lg font-bold text-gray-900" x-text="summary.leave_days"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-gray-500">Absences</p>
                            <p class="text-lg font-bold text-gray-900" x-text="summary.absent_days"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7h8m-8 4h5m-9 8h16a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-gray-500">Repos</p>
                            <p class="text-lg font-bold text-gray-900" x-text="summary.rest_days"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-gray-500">Retards</p>
                            <p class="text-lg font-bold text-gray-900" x-text="summary.late_days"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <div>
                            <p class="text-xs text-gray-500">Heures supp.</p>
                            <p class="text-lg font-bold text-gray-900" x-text="summary.overtime_hours"></p>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('hr.attendance.store-month') }}" @submit="prepareSubmit">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">

                {{-- Bulk toolbar --}}
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 mr-2 cursor-pointer select-none">
                        <input type="checkbox" class="rounded border-gray-300 text-[#0a5d8a] focus:ring-[#0a5d8a]"
                               :checked="allSelected" @change="toggleAll($event.target.checked)">
                        Tout sélectionner
                    </label>
                    <button type="button" @click="applySchedule()" class="px-3 py-2 rounded-lg text-xs font-semibold bg-[#0a5d8a] text-white hover:bg-[#084d72]">Appliquer horaires</button>
                    <button type="button" @click="bulkStatus('present')" class="px-3 py-2 rounded-lg text-xs font-semibold bg-teal-600 text-white hover:bg-teal-700">Marquer Présent</button>
                    <button type="button" @click="bulkStatus('rest')" class="px-3 py-2 rounded-lg text-xs font-semibold bg-slate-500 text-white hover:bg-slate-600">Marquer Repos</button>
                    <button type="button" @click="bulkStatus('absent')" class="px-3 py-2 rounded-lg text-xs font-semibold bg-rose-500 text-white hover:bg-rose-600">Marquer Absent</button>
                    <button type="button" @click="bulkStatus('leave')" class="px-3 py-2 rounded-lg text-xs font-semibold bg-violet-600 text-white hover:bg-violet-700">Marquer Congé</button>
                    <div class="flex-1"></div>
                    <button type="button" @click="applySchedule(true)" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Importer un planning
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-lg text-xs font-semibold bg-[#0a5d8a] text-white hover:bg-[#084d72]">Enregistrer le mois</button>
                </div>

                <div class="mb-3 flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Motif de correction (si mois déjà saisi)</label>
                        <input type="text" name="correction_reason" value="{{ old('correction_reason', 'Saisie mensuelle') }}" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="Ex. Ajustement fin de mois">
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-[1fr_200px] gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-3 py-3 w-10"></th>
                                    <th class="px-3 py-3 text-left">Date</th>
                                    <th class="px-3 py-3 text-left">Jour</th>
                                    <th class="px-3 py-3 text-left">Statut</th>
                                    <th class="px-3 py-3 text-left">Heure entrée</th>
                                    <th class="px-3 py-3 text-left">Heure sortie</th>
                                    <th class="px-3 py-3 text-left">Pause (min)</th>
                                    <th class="px-3 py-3 text-left">Durée</th>
                                    <th class="px-3 py-3 text-left">Retard</th>
                                    <th class="px-3 py-3 text-left">Heures supp.</th>
                                    <th class="px-3 py-3 text-left">Commentaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(day, index) in days" :key="day.date">
                                    <tr class="border-t border-gray-100" :class="day.is_weekend ? 'bg-slate-50/80' : ''">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" class="rounded border-gray-300 text-[#0a5d8a] focus:ring-[#0a5d8a]"
                                                   :disabled="day.locked"
                                                   x-model="day.selected">
                                            <input type="hidden" :name="`days[${index}][work_date]`" :value="day.date">
                                            <input type="hidden" :name="`days[${index}][status]`" :value="day.status">
                                            <input type="hidden" :name="`days[${index}][clock_in]`" :value="day.clock_in || ''">
                                            <input type="hidden" :name="`days[${index}][clock_out]`" :value="day.clock_out || ''">
                                            <input type="hidden" :name="`days[${index}][notes]`" :value="day.notes || ''">
                                        </td>
                                        <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap" x-text="day.date_label"></td>
                                        <td class="px-3 py-2 text-gray-600 whitespace-nowrap" x-text="day.day_label"></td>
                                        <td class="px-3 py-2 min-w-[150px]">
                                            <select class="w-full px-2 py-1.5 border rounded-lg text-xs font-semibold"
                                                    :class="statusClass(day.status)"
                                                    :disabled="day.locked"
                                                    x-model="day.status"
                                                    @change="onStatusChange(day)">
                                                <template x-for="(label, key) in statuses" :key="key">
                                                    <option :value="key" x-text="label"></option>
                                                </template>
                                            </select>
                                            <p class="text-[10px] text-sky-600 mt-0.5" x-show="day.locked" x-text="day.lock_source === 'leave' ? 'Depuis module congés' : 'Depuis absences'"></p>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="time" class="w-full min-w-[110px] px-2 py-1.5 border border-gray-200 rounded-lg text-sm disabled:bg-gray-50 disabled:text-gray-400"
                                                   :disabled="day.locked || !needsTimes(day.status)"
                                                   x-model="day.clock_in"
                                                   @change="recalcDay(day)">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="time" class="w-full min-w-[110px] px-2 py-1.5 border border-gray-200 rounded-lg text-sm disabled:bg-gray-50 disabled:text-gray-400"
                                                   :disabled="day.locked || !needsTimes(day.status)"
                                                   x-model="day.clock_out"
                                                   @change="recalcDay(day)">
                                        </td>
                                        <td class="px-3 py-2 text-center text-gray-600 whitespace-nowrap" x-text="day.break_minutes ?? 0"></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-700" x-text="minutesLabel(day.worked_minutes)"></td>
                                        <td class="px-3 py-2 whitespace-nowrap" :class="day.late_minutes > 0 ? 'text-orange-600 font-semibold' : 'text-gray-500'" x-text="minutesLabel(day.late_minutes)"></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-700" x-text="minutesLabel(day.overtime_minutes)"></td>
                                        <td class="px-3 py-2 min-w-[180px]">
                                            <input type="text" class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm disabled:bg-gray-50"
                                                   :disabled="day.locked"
                                                   x-model="day.notes"
                                                   placeholder="—">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <aside class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 h-fit">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3">Légende des statuts</h3>
                        <ul class="space-y-2.5 text-sm text-gray-700">
                            <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Présent</li>
                            <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span> Repos</li>
                            <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-violet-700"></span> Congé payé</li>
                            <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-purple-400"></span> Congé maladie</li>
                            <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span> Absent</li>
                            <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span> Retard</li>
                            <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span> Heures supplémentaires</li>
                        </ul>
                    </aside>
                </div>
            </form>

            {{-- Bottom feature cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <h4 class="text-sm font-semibold text-emerald-900 mb-2">Fonctionnalités clés</h4>
                    <ul class="text-sm text-emerald-900/80 space-y-1 list-disc list-inside">
                        <li>Saisie mensuelle jour par jour</li>
                        <li>Actions de masse sur la sélection</li>
                        <li>Calcul automatique durée / retard / HS</li>
                        <li>Application du planning salarié</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                    <h4 class="text-sm font-semibold text-sky-900 mb-2">Règles automatiques</h4>
                    <ul class="text-sm text-sky-900/80 space-y-1 list-disc list-inside">
                        <li>Congés validés pré-remplis</li>
                        <li>Absences synchronisées</li>
                        <li>Repos selon le planning du salarié</li>
                        <li>Totaux mis à jour en temps réel</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <h4 class="text-sm font-semibold text-amber-900 mb-2">Résultat</h4>
                    <ul class="text-sm text-amber-900/80 space-y-1 list-disc list-inside">
                        <li>Gain de temps en fin de mois</li>
                        <li>Moins d’erreurs de saisie</li>
                        <li>Suivi complet et lisible</li>
                        <li>Interface intuitive pour la RH</li>
                    </ul>
                </div>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-500">
                Aucun salarié actif. Créez un salarié pour commencer la saisie mensuelle.
            </div>
        @endif
    @endif
</main>

<script>
function attendancePage(payload) {
    const noTimeStatuses = ['rest', 'leave', 'sick', 'absent', 'holiday'];

    function toMinutes(time) {
        if (!time || !/^\d{2}:\d{2}/.test(time)) return null;
        const [h, m] = time.split(':').map(Number);
        return h * 60 + m;
    }

    function minutesLabel(mins) {
        mins = Number(mins) || 0;
        const sign = mins < 0 ? '-' : '';
        mins = Math.abs(mins);
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        return `${sign}${h}h${String(m).padStart(2, '0')}`;
    }

    function summarize(days) {
        let theoretical = 0, workedDays = 0, presentDays = 0, workedMinutes = 0, leaveDays = 0, absentDays = 0, restDays = 0, lateDays = 0, overtimeMinutes = 0;
        days.forEach((day) => {
            if (!day.schedule_is_off) theoretical++;
            if (day.status === 'present' || day.status === 'late') {
                workedDays++;
                presentDays++;
                workedMinutes += Number(day.worked_minutes) || 0;
            }
            if (day.status === 'leave' || day.status === 'sick') leaveDays++;
            if (day.status === 'absent') absentDays++;
            if (day.status === 'rest' || day.status === 'holiday') restDays++;
            if (day.status === 'late' || (Number(day.late_minutes) || 0) > 0) lateDays++;
            overtimeMinutes += Number(day.overtime_minutes) || 0;
        });
        return {
            theoretical_work_days: theoretical,
            worked_days: workedDays,
            present_days: presentDays,
            worked_hours: minutesLabel(workedMinutes),
            leave_days: leaveDays,
            absent_days: absentDays,
            rest_days: restDays,
            late_days: lateDays,
            overtime_hours: minutesLabel(overtimeMinutes),
        };
    }

    return {
        days: (payload.days || []).map((d) => ({ ...d, selected: false })),
        statuses: payload.statuses || {},
        summary: payload.summary || summarize(payload.days || []),
        minutesLabel,
        init() {
            this.days.forEach((day) => this.recalcDay(day, false));
            this.refreshSummary();
        },
        get allSelected() {
            const editable = this.days.filter((d) => !d.locked);
            return editable.length > 0 && editable.every((d) => d.selected);
        },
        needsTimes(status) {
            return !noTimeStatuses.includes(status);
        },
        statusClass(status) {
            const map = {
                present: 'bg-emerald-100 text-emerald-800 border-emerald-200',
                rest: 'bg-slate-100 text-slate-600 border-slate-200',
                leave: 'bg-violet-100 text-violet-800 border-violet-200',
                sick: 'bg-purple-50 text-purple-700 border-purple-200',
                absent: 'bg-red-100 text-red-700 border-red-200',
                late: 'bg-amber-100 text-amber-800 border-amber-200',
                holiday: 'bg-sky-100 text-sky-800 border-sky-200',
            };
            return map[status] || 'bg-gray-50 text-gray-700 border-gray-200';
        },
        toggleAll(checked) {
            this.days.forEach((d) => { if (!d.locked) d.selected = checked; });
        },
        selectedDays() {
            const picked = this.days.filter((d) => d.selected && !d.locked);
            return picked.length ? picked : this.days.filter((d) => !d.locked);
        },
        bulkStatus(status) {
            this.selectedDays().forEach((day) => {
                day.status = status;
                this.onStatusChange(day);
            });
            this.refreshSummary();
        },
        applySchedule(allEmptyOnly = false) {
            // Applique le planning versionné jour par jour (schedule_* déjà résolu
            // côté serveur via EmployeeSchedule::forEmployeeOnDate). Ne touche pas
            // aux jours verrouillés (congés/absences) ni aux jours sans planning applicable.
            const targets = allEmptyOnly
                ? this.days.filter((d) => !d.locked && !d.has_record)
                : this.selectedDays();
            targets.forEach((day) => {
                if (day.schedule_is_off) {
                    day.status = 'rest';
                    day.clock_in = null;
                    day.clock_out = null;
                } else if (day.schedule_in || day.schedule_out) {
                    day.status = 'present';
                    day.clock_in = day.schedule_in;
                    day.clock_out = day.schedule_out;
                } else if (day.has_schedule) {
                    day.status = 'rest';
                    day.clock_in = null;
                    day.clock_out = null;
                } else {
                    // Aucune version de planning pour cette date : ne pas inventer un Repos week-end.
                    return;
                }
                this.recalcDay(day, false);
            });
            this.refreshSummary();
        },
        onStatusChange(day) {
            if (!this.needsTimes(day.status)) {
                day.clock_in = null;
                day.clock_out = null;
                day.worked_minutes = 0;
                day.late_minutes = 0;
                day.overtime_minutes = 0;
            } else if (!day.clock_in && day.schedule_in) {
                day.clock_in = day.schedule_in;
                day.clock_out = day.schedule_out;
            }
            this.recalcDay(day);
        },
        recalcDay(day, refresh = true) {
            if (!this.needsTimes(day.status) || !day.clock_in || !day.clock_out) {
                if (!this.needsTimes(day.status)) {
                    day.worked_minutes = 0;
                    day.late_minutes = 0;
                    day.overtime_minutes = 0;
                }
                if (refresh) this.refreshSummary();
                return;
            }
            let inM = toMinutes(day.clock_in);
            let outM = toMinutes(day.clock_out);
            if (inM === null || outM === null) return;
            if (outM < inM) outM += 24 * 60;
            const breakMins = Number(day.break_minutes) || 0;
            let worked = Math.max(0, outM - inM - breakMins);
            day.worked_minutes = worked;

            const schedIn = toMinutes(day.schedule_in);
            const schedOut = toMinutes(day.schedule_out);
            day.late_minutes = schedIn !== null && inM > schedIn ? inM - schedIn : 0;
            day.overtime_minutes = schedOut !== null && outM > schedOut ? outM - schedOut : 0;

            if (day.status === 'present' && day.late_minutes > 5) {
                day.status = 'late';
            }
            if (refresh) this.refreshSummary();
        },
        refreshSummary() {
            this.summary = summarize(this.days);
        },
        prepareSubmit() {
            this.days.forEach((day) => this.recalcDay(day, false));
        },
    };
}
</script>
@endsection
