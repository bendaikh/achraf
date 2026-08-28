<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrAuditLog;
use App\Models\HrEvent;
use Illuminate\Http\Request;

class HrHistoryController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = HrEvent::query()->with(['employee', 'user']);
        $this->applyTableSearch($query, $request, ['title', 'description', 'employee.last_name', 'employee.matricule']);
        $this->applyTableFilter($query, $request, 'employee_id', 'employee_id');
        $this->applyTableFilter($query, $request, 'type', 'type');
        $this->applyTableDateRange($query, $request, 'event_date');
        $this->applyTableSort($query, $request, ['event_date' => 'event_date'], 'event_date', 'desc');

        return view('hr.history.index', [
            'events' => $this->paginateTable($query, $request),
            'audits' => HrAuditLog::query()->with('user')->latest()->limit(80)->get(),
            'employees' => Employee::query()->orderBy('last_name')->get(),
        ]);
    }
}
