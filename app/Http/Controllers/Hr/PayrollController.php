<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Services\Hr\PayrollEngine;
use App\Services\Hr\PayrollService;
use App\Services\Hr\PayslipPdfService;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $runs = PayrollRun::query()->withCount('slips')->orderByDesc('period_year')->orderByDesc('period_month')->paginate(12);

        return view('hr.payroll.index', [
            'runs' => $runs,
            'year' => (int) $request->input('year', now()->year),
            'month' => (int) $request->input('month', now()->month),
        ]);
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load(['slips.employee', 'slips.payments']);

        return view('hr.payroll.show', ['run' => $payrollRun]);
    }

    public function prepare(Request $request, PayrollService $service)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $run = $service->prepare((int) $validated['year'], (int) $validated['month']);

        return redirect()->route('hr.payroll.show', $run)->with('success', 'Mois préparé : salaires, primes, HS, absences et avances ont été récupérés automatiquement.');
    }

    public function calculate(PayrollRun $payrollRun, PayrollService $service)
    {
        $service->calculate($payrollRun);

        return back()->with('success', 'Paie calculée selon les paramètres applicables à la période.');
    }

    public function transition(Request $request, PayrollRun $payrollRun, PayrollService $service)
    {
        $validated = $request->validate([
            'status' => 'required|in:verifiee,validee,payee',
        ]);

        $service->transition($payrollRun, $validated['status']);

        return back()->with('success', 'Statut de paie mis à jour.');
    }

    public function pay(Request $request, PayrollSlip $slip, PayrollService $service)
    {
        $validated = $request->validate([
            'paid_at' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
            'method' => 'required|in:virement,especes,cheque',
            'account' => 'required|in:caisse,banque,other',
            'reference' => 'nullable|string|max:120',
            'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
            'notes' => 'nullable|string',
        ]);

        if ($request->hasFile('proof')) {
            $validated['proof_path'] = $request->file('proof')->store('hr/payroll-proofs', 'public');
        }
        unset($validated['proof']);

        $service->paySlip($slip, $validated);

        return back()->with('success', 'Paiement enregistré. Une dépense de trésorerie (mouvement Salaire) a été créée automatiquement.');
    }

    public function pdf(PayrollSlip $slip, PayslipPdfService $pdfs)
    {
        return $pdfs->download($slip);
    }

    public function print(PayrollSlip $slip, PayslipPdfService $pdfs)
    {
        return $pdfs->stream($slip);
    }

    public function simulate(Request $request, PayrollEngine $engine)
    {
        $employees = Employee::query()->orderBy('last_name')->get();
        $simulation = null;
        $employee = null;

        if ($request->filled('employee_id')) {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'year' => 'required|integer',
                'month' => 'required|integer|min:1|max:12',
            ]);
            $employee = Employee::with(['compensationItems', 'salaryRecords'])->findOrFail($request->integer('employee_id'));
            $period = \Carbon\Carbon::create((int) $request->input('year'), (int) $request->input('month'), 1);
            $simulation = $engine->simulate($employee, $period);
        }

        return view('hr.payroll.simulate', [
            'employees' => $employees,
            'employee' => $employee,
            'simulation' => $simulation,
            'year' => (int) $request->input('year', now()->year),
            'month' => (int) $request->input('month', now()->month),
        ]);
    }
}
