<?php

namespace App\Services\Access;

use App\Models\Collaborator;
use App\Models\Employee;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CollaboratorService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Collaborator
    {
        if (($data['type'] ?? null) === Collaborator::TYPE_SALARIE && ! empty($data['employee_id'])) {
            $data = $this->mergeFromEmployee($data, (int) $data['employee_id']);
        }

        if (($data['type'] ?? null) !== Collaborator::TYPE_SALARIE) {
            $data['employee_id'] = null;
        }

        $collaborator = Collaborator::query()->create($data);

        ActivityLogger::log(
            'creation',
            'Création collaborateur '.$collaborator->fullName(),
            $collaborator,
            null,
            $collaborator->only(['type', 'last_name', 'first_name', 'email', 'employee_id']),
        );

        return $collaborator;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Collaborator $collaborator, array $data): Collaborator
    {
        $old = $collaborator->only(['type', 'last_name', 'first_name', 'email', 'status', 'employee_id', 'is_commercial']);

        if (($data['type'] ?? $collaborator->type) === Collaborator::TYPE_SALARIE && ! empty($data['employee_id'])) {
            if ((int) $data['employee_id'] !== (int) $collaborator->employee_id) {
                $data = $this->mergeFromEmployee($data, (int) $data['employee_id'], preserveExisting: true);
            }
        }

        if (($data['type'] ?? $collaborator->type) !== Collaborator::TYPE_SALARIE) {
            $data['employee_id'] = null;
        }

        $collaborator->update($data);

        ActivityLogger::log(
            'modification',
            'Modification collaborateur '.$collaborator->fullName(),
            $collaborator,
            $old,
            $collaborator->only(['type', 'last_name', 'first_name', 'email', 'status', 'employee_id', 'is_commercial']),
        );

        return $collaborator->refresh();
    }

    public function storePhoto(Collaborator $collaborator, UploadedFile $file): string
    {
        if ($collaborator->photo_path) {
            Storage::disk('public')->delete($collaborator->photo_path);
        }

        return $file->store('collaborators/photos', 'public');
    }

    /**
     * Create collaborators for RH employees that are not yet linked.
     *
     * @return int Number created
     */
    public function syncFromEmployees(): int
    {
        $created = 0;

        Employee::query()
            ->whereDoesntHave('collaborator')
            ->orderBy('id')
            ->each(function (Employee $employee) use (&$created) {
                Collaborator::query()->create([
                    'type' => Collaborator::TYPE_SALARIE,
                    'last_name' => $employee->last_name,
                    'first_name' => $employee->first_name,
                    'photo_path' => $employee->photo_path,
                    'phone' => $employee->phone,
                    'email' => $employee->email,
                    'job_title' => $employee->job_title,
                    'department' => $employee->department?->name,
                    'employee_id' => $employee->id,
                    'start_date' => $employee->hire_date,
                    'status' => $employee->status === Employee::STATUS_ACTIF
                        ? Collaborator::STATUS_ACTIF
                        : Collaborator::STATUS_INACTIF,
                    'is_commercial' => (bool) $employee->commission_eligible,
                    'notes' => null,
                ]);
                $created++;
            });

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeFromEmployee(array $data, int $employeeId, bool $preserveExisting = false): array
    {
        $employee = Employee::query()->with('department')->findOrFail($employeeId);

        $map = [
            'last_name' => $employee->last_name,
            'first_name' => $employee->first_name,
            'phone' => $employee->phone,
            'email' => $employee->email,
            'job_title' => $employee->job_title,
            'department' => $employee->department?->name,
            'start_date' => $employee->hire_date?->toDateString(),
            'photo_path' => $employee->photo_path,
        ];

        foreach ($map as $key => $value) {
            if ($preserveExisting && filled($data[$key] ?? null)) {
                continue;
            }
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        $data['employee_id'] = $employee->id;
        $data['type'] = Collaborator::TYPE_SALARIE;

        return $data;
    }
}
