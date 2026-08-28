<?php

namespace App\Services\Hr;

use App\Models\Employee;

class EmployeeMatriculeService
{
    public function next(): string
    {
        $last = Employee::query()
            ->where('matricule', 'like', 'EMP-%')
            ->orderByDesc('id')
            ->value('matricule');

        $next = 1;
        if ($last && preg_match('/EMP-(\d+)/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return 'EMP-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
