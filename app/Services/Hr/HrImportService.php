<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\HrDepartment;
use App\Models\SalaryRecord;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrImportService
{
    public function __construct(
        protected EmployeeMatriculeService $matricules,
        protected LeaveBalanceService $leaveBalances,
        protected AttendanceService $attendance,
        protected HrTimelineService $timeline,
    ) {}

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            'nom', 'prenom', 'date_naissance', 'cin', 'adresse', 'telephone', 'email',
            'date_entree', 'fonction', 'service', 'lieu_travail', 'statut',
            'id_pointeuse', 'salaire', 'negocie_en', 'solde_conges_repris',
            'type_contrat', 'date_fin_contrat',
        ];
        $example = [
            'Alaoui', 'Karim', '1990-05-12', 'BE123456', 'Casablanca', '0612345678', 'karim@example.com',
            '2025-09-01', 'Commercial', 'Ventes', 'Siège', 'actif',
            'EMP-0001', '5000', 'net', '5',
            'cdi', '',
        ];

        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
            $sheet->setCellValue([$i + 1, 2], $example[$i]);
        }

        $filename = 'modele-import-salaries.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function importEmployees(UploadedFile $file): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];

        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $headerRow = array_shift($rows);
        $map = [];
        foreach ($headerRow ?? [] as $col => $label) {
            $map[strtolower(trim((string) $label))] = $col;
        }

        $required = ['nom', 'prenom', 'date_entree'];
        foreach ($required as $key) {
            if (! isset($map[$key])) {
                return ['created' => 0, 'skipped' => 0, 'errors' => ['Colonne obligatoire manquante : '.$key]];
            }
        }

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $lastName = trim((string) ($row[$map['nom']] ?? ''));
            $firstName = trim((string) ($row[$map['prenom']] ?? ''));
            $hireDate = trim((string) ($row[$map['date_entree']] ?? ''));

            if ($lastName === '' && $firstName === '') {
                continue;
            }

            if ($lastName === '' || $firstName === '' || $hireDate === '') {
                $errors[] = "Ligne {$line} : nom, prénom et date d'entrée sont obligatoires.";
                continue;
            }

            $cin = trim((string) ($row[$map['cin'] ?? ''] ?? ''));
            if ($cin !== '' && Employee::query()->where('cin', $cin)->exists()) {
                $skipped++;
                continue;
            }

            $departmentName = trim((string) ($row[$map['service'] ?? ''] ?? ''));
            $departmentId = null;
            if ($departmentName !== '') {
                $departmentId = HrDepartment::query()->firstOrCreate(['name' => $departmentName])->id;
            }

            try {
                $employee = Employee::create([
                    'matricule' => $this->matricules->next(),
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'birth_date' => $this->parseDate($row[$map['date_naissance'] ?? ''] ?? null),
                    'cin' => $cin ?: null,
                    'address' => trim((string) ($row[$map['adresse'] ?? ''] ?? '')) ?: null,
                    'phone' => trim((string) ($row[$map['telephone'] ?? ''] ?? '')) ?: null,
                    'email' => trim((string) ($row[$map['email'] ?? ''] ?? '')) ?: null,
                    'hire_date' => $this->parseDate($hireDate) ?? now()->toDateString(),
                    'job_title' => trim((string) ($row[$map['fonction'] ?? ''] ?? '')) ?: null,
                    'department_id' => $departmentId,
                    'workplace' => trim((string) ($row[$map['lieu_travail'] ?? ''] ?? '')) ?: null,
                    'status' => $this->mapStatus(trim((string) ($row[$map['statut'] ?? ''] ?? 'actif'))),
                    'timeclock_external_id' => trim((string) ($row[$map['id_pointeuse'] ?? ''] ?? '')) ?: null,
                    'initial_leave_balance' => (float) str_replace(',', '.', (string) ($row[$map['solde_conges_repris'] ?? ''] ?? 0)),
                ]);

                $this->attendance->seedDefaultSchedule($employee);
                $this->leaveBalances->ensureInitialBalance($employee);
                $this->timeline->record($employee, 'hire', 'Embauche', $employee->hire_date, 'Import Excel — date réelle d\'entrée');

                $salary = (float) str_replace(',', '.', (string) ($row[$map['salaire'] ?? ''] ?? 0));
                if ($salary > 0) {
                    $negotiated = strtolower(trim((string) ($row[$map['negocie_en'] ?? ''] ?? 'brut')));
                    SalaryRecord::create([
                        'employee_id' => $employee->id,
                        'effective_date' => $employee->hire_date,
                        'base_salary' => $salary,
                        'negotiated_as' => str_contains($negotiated, 'net') ? SalaryRecord::AS_NET : SalaryRecord::AS_BRUT,
                    ]);
                    $this->timeline->record($employee, 'salary', 'Salaire initial', $employee->hire_date, number_format($salary, 2, ',', ' ').' MAD');
                }

                $contractType = strtolower(trim((string) ($row[$map['type_contrat'] ?? ''] ?? '')));
                if (in_array($contractType, ['cdi', 'cdd', 'stage', 'autre'], true)) {
                    EmployeeContract::create([
                        'employee_id' => $employee->id,
                        'type' => $contractType,
                        'start_date' => $employee->hire_date,
                        'end_date' => $this->parseDate($row[$map['date_fin_contrat'] ?? ''] ?? null),
                        'job_title' => $employee->job_title,
                        'salary' => $salary > 0 ? $salary : null,
                        'status' => EmployeeContract::STATUS_EN_COURS,
                    ]);
                }

                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Ligne {$line} : ".$e->getMessage();
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapStatus(string $status): string
    {
        $status = mb_strtolower($status);

        return match (true) {
            str_contains($status, 'sort') => Employee::STATUS_SORTI,
            str_contains($status, 'susp') => Employee::STATUS_SUSPENDU,
            default => Employee::STATUS_ACTIF,
        };
    }
}
