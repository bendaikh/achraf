<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\HrReportService;
use Illuminate\Http\Request;

class HrReportController extends Controller
{
    public function index(HrReportService $reports)
    {
        return view('hr.reports.index', [
            'types' => $reports->types(),
        ]);
    }

    public function export(Request $request, HrReportService $reports)
    {
        $validated = $request->validate([
            'type' => 'required|in:'.implode(',', array_keys($reports->types())),
            'format' => 'required|in:xlsx,pdf',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000',
            'employee_id' => 'nullable|exists:employees,id',
            'department_id' => 'nullable|exists:hr_departments,id',
            'job_title' => 'nullable|string',
            'status' => 'nullable|in:actif,suspendu,sorti',
        ]);

        $format = $validated['format'];
        unset($validated['format']);
        $type = $validated['type'];
        unset($validated['type']);

        return $format === 'pdf'
            ? $reports->pdf($type, $validated)
            : $reports->excel($type, $validated);
    }
}
