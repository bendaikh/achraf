<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\CompensationItem;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\EmployeeContract;
use App\Models\ManagedDocument;
use App\Models\PayrollAdjustment;
use App\Models\PayrollSlip;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrReportService
{
    /**
     * @return array<string, string>
     */
    public function types(): array
    {
        return [
            'employees' => 'Liste des salariés',
            'employees_status' => 'Salariés actifs / sortis',
            'contracts' => 'Contrats et échéances',
            'attendance' => 'Présences',
            'lates' => 'Retards',
            'absences' => 'Absences',
            'overtime' => 'Heures supplémentaires',
            'leaves' => 'Congés et soldes',
            'compensations' => 'Primes / indemnités',
            'adjustments' => 'Avances / retenues',
            'payroll' => 'Paies',
            'payroll_mass' => 'Masse salariale',
            'employer_cost' => 'Coût employeur',
            'documents_expiring' => 'Documents arrivant à expiration',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title: string, headers: list<string>, rows: list<list<string>>}
     */
    public function build(string $type, array $filters): array
    {
        $month = (int) ($filters['month'] ?? now()->month);
        $year = (int) ($filters['year'] ?? now()->year);
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::query()
            ->with('department')
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['job_title'] ?? null, fn ($q, $title) => $q->where('job_title', $title))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('last_name')
            ->get();

        $ids = $employees->pluck('id');
        $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
        $title = $this->types()[$type] ?? $type;

        return match ($type) {
            'employees', 'employees_status' => [
                'title' => $title,
                'headers' => ['Matricule', 'Nom', 'Fonction', 'Service', 'Entrée', 'Statut'],
                'rows' => $employees->map(fn (Employee $e) => [
                    $e->matricule, $e->fullName(), (string) $e->job_title, (string) $e->department?->name,
                    $e->hire_date?->format('d/m/Y') ?? '', $e->statusLabel(),
                ])->all(),
            ],
            'contracts' => $this->table($title, ['Salarié', 'Type', 'Début', 'Fin', 'Essai', 'Statut'],
                EmployeeContract::query()->with('employee')->whereIn('employee_id', $ids)->orderBy('end_date')->get()
                    ->map(fn ($c) => [
                        $c->employee?->fullName(), $c->typeLabel(), $c->start_date?->format('d/m/Y'),
                        $c->end_date?->format('d/m/Y') ?: '—', $c->trial_end_date?->format('d/m/Y') ?: '—', $c->statusLabel(),
                    ])),
            'attendance' => $this->attendanceTable($title, $ids, $start, $end, null),
            'lates' => $this->attendanceTable($title, $ids, $start, $end, 'late'),
            'overtime' => $this->attendanceTable($title, $ids, $start, $end, 'overtime'),
            'absences' => $this->table($title, ['Salarié', 'Type', 'Début', 'Fin', 'Jours', 'Impact paie'],
                EmployeeAbsence::query()->with('employee')->whereIn('employee_id', $ids)
                    ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->get()
                    ->map(fn ($a) => [
                        $a->employee?->fullName(), $a->typeLabel(), $a->start_date?->format('d/m/Y'),
                        $a->end_date?->format('d/m/Y'), $fmt($a->days), $a->impacts_payroll ? 'Oui' : 'Non',
                    ])),
            'leaves' => $this->table($title, ['Salarié', 'Acquis', 'Pris', 'Restant'],
                $employees->map(function (Employee $e) use ($fmt) {
                    $s = $e->leaveBalanceSummary();

                    return [$e->fullName(), $fmt($s['acquired']), $fmt($s['taken']), $fmt($s['remaining'])];
                })),
            'compensations' => $this->table($title, ['Salarié', 'Nature', 'Récurrence', 'Montant', 'Début', 'Fin'],
                CompensationItem::query()->with('employee')->whereIn('employee_id', $ids)->get()
                    ->map(fn ($i) => [
                        $i->employee?->fullName(), $i->kindLabel(), CompensationItem::RECURRENCES[$i->recurrence] ?? $i->recurrence,
                        $fmt($i->amount), $i->start_date?->format('d/m/Y'), $i->end_date?->format('d/m/Y') ?: '—',
                    ])),
            'adjustments' => $this->table($title, ['Salarié', 'Type', 'Montant', 'Période', 'Solde', 'Récupérée'],
                PayrollAdjustment::query()->with('employee')->whereIn('employee_id', $ids)->get()
                    ->map(fn ($a) => [
                        $a->employee?->fullName(), $a->typeLabel(), $fmt($a->amount),
                        sprintf('%02d/%d', $a->period_month, $a->period_year),
                        $fmt($a->remaining_amount ?? $a->amount), $a->recovered_at ? 'Oui' : 'Non',
                    ])),
            'payroll', 'payroll_mass', 'employer_cost' => $this->table($title, ['Salarié', 'Période', 'Brut', 'Net', 'Masse (net)', 'Coût employeur'],
                PayrollSlip::query()->with(['employee', 'run'])
                    ->whereIn('employee_id', $ids)
                    ->whereHas('run', fn ($q) => $q->where('period_year', $year)->where('period_month', $month))
                    ->get()
                    ->map(fn ($s) => [
                        $s->employee?->fullName(), $s->run?->periodLabel(), $fmt($s->gross), $fmt($s->net), $fmt($s->net), $fmt($s->employer_cost),
                    ])),
            'documents_expiring' => $this->table($title, ['Salarié', 'Catégorie', 'Expiration'],
                ManagedDocument::query()->where('section_key', 'hr-employees')->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now()->toDateString(), now()->addDays((int) Setting::get('hr.alert.document_expiry_days', 30))->toDateString()])
                    ->with('documentable')
                    ->get()
                    ->map(fn ($d) => [
                        $d->documentable instanceof Employee ? $d->documentable->fullName() : '—',
                        $d->document_type_label, optional($d->expires_at)->format('d/m/Y'),
                    ])),
            default => ['title' => $title, 'headers' => [], 'rows' => []],
        };
    }

    public function excel(string $type, array $filters): StreamedResponse
    {
        $data = $this->build($type, $filters);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(array_merge([$data['headers']], $data['rows']));
        $filename = \Illuminate\Support\Str::slug($data['title']).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pdf(string $type, array $filters)
    {
        $data = $this->build($type, $filters);

        return Pdf::loadView('hr.reports.pdf', $data + ['filters' => $filters])->setPaper('a4', 'landscape')->download(\Illuminate\Support\Str::slug($data['title']).'.pdf');
    }

    /**
     * @param  Collection<int, list<mixed>>|iterable  $rows
     * @return array{title: string, headers: list<string>, rows: list<list<string>>}
     */
    private function table(string $title, array $headers, iterable $rows): array
    {
        return [
            'title' => $title,
            'headers' => $headers,
            'rows' => collect($rows)->map(fn ($row) => array_map(fn ($v) => (string) ($v ?? ''), $row))->all(),
        ];
    }

    private function attendanceTable(string $title, Collection $ids, Carbon $start, Carbon $end, ?string $mode): array
    {
        $query = AttendanceRecord::query()->with('employee')->whereIn('employee_id', $ids)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);
        if ($mode === 'late') {
            $query->where(fn ($q) => $q->where('late_minutes', '>', 0)->orWhere('status', AttendanceRecord::STATUS_LATE));
        }
        if ($mode === 'overtime') {
            $query->where('overtime_minutes', '>', 0);
        }

        return $this->table($title, ['Salarié', 'Date', 'Entrée', 'Sortie', 'Durée', 'Retard', 'Départ ant.', 'HS', 'Statut', 'Source'],
            $query->orderBy('work_date')->get()->map(fn ($r) => [
                $r->employee?->fullName(), $r->work_date?->format('d/m/Y'),
                $r->clock_in ? substr($r->clock_in, 0, 5) : '—',
                $r->clock_out ? substr($r->clock_out, 0, 5) : '—',
                $r->workedHoursLabel(), $r->late_minutes.' min', ($r->early_minutes ?? 0).' min',
                $r->overtime_minutes.' min', $r->statusLabel(), $r->sourceLabel(),
            ]));
    }
}
