<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrDepartment;
use App\Services\Hr\HrDashboardService;
use Illuminate\Http\Request;

class HrDashboardController extends Controller
{
    public function __invoke(Request $request, HrDashboardService $dashboard)
    {
        $filters = $request->only(['month', 'year', 'employee_id', 'department_id', 'job_title', 'status']);
        $stats = $dashboard->stats($filters);

        return view('hr.dashboard', [
            'stats' => $stats,
            'filters' => $filters,
            'employees' => Employee::query()->orderBy('last_name')->get(),
            'departments' => HrDepartment::query()->orderBy('name')->get(),
            'jobTitles' => Employee::query()->whereNotNull('job_title')->distinct()->orderBy('job_title')->pluck('job_title'),
        ]);
    }
}
