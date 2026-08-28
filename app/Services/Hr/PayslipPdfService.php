<?php

namespace App\Services\Hr;

use App\Models\PayrollSlip;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PayslipPdfService
{
    public function download(PayrollSlip $slip): Response
    {
        $slip->loadMissing(['employee.department', 'run']);

        $pdf = Pdf::loadView('hr.payroll.payslip-pdf', [
            'slip' => $slip,
            'employee' => $slip->employee,
            'run' => $slip->run,
            'breakdown' => $slip->breakdown ?? [],
        ])->setPaper('a4');

        $name = sprintf('bulletin-%s-%s.pdf', $slip->employee?->matricule ?? 'salarie', str_replace('/', '-', $slip->run?->periodLabel() ?? ''));

        return $pdf->download($name);
    }

    public function stream(PayrollSlip $slip): Response
    {
        $slip->loadMissing(['employee.department', 'run']);

        return Pdf::loadView('hr.payroll.payslip-pdf', [
            'slip' => $slip,
            'employee' => $slip->employee,
            'run' => $slip->run,
            'breakdown' => $slip->breakdown ?? [],
        ])->setPaper('a4')->stream(sprintf('bulletin-%s.pdf', $slip->employee?->matricule ?? 'salarie'));
    }
}
