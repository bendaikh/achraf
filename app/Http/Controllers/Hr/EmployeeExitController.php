<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Hr\EmployeeService;
use Illuminate\Http\Request;

class EmployeeExitController extends Controller
{
    public function store(Request $request, Employee $employee, EmployeeService $service)
    {
        abort_if($employee->status === Employee::STATUS_SORTI, 422, 'Ce salarié est déjà sorti.');

        $validated = $request->validate([
            'exit_date' => 'required|date',
            'last_work_date' => 'nullable|date',
            'reason' => 'nullable|string|max:255',
            'leave_balance_settlement' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $service->exit($employee, $validated);

        return redirect()->route('hr.employees.show', [$employee, 'tab' => 'sortie'])
            ->with('success', 'Sortie enregistrée. Le dossier et l\'historique restent disponibles.');
    }
}
