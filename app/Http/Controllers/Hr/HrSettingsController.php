<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrDepartment;
use App\Models\LeaveType;
use App\Models\PayrollRuleSet;
use App\Models\Setting;
use App\Models\User;
use App\Support\HrPermission;
use Illuminate\Http\Request;

class HrSettingsController extends Controller
{
    public function index()
    {
        return view('hr.settings.index', [
            'departments' => HrDepartment::query()->orderBy('name')->get(),
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
            'ruleSets' => PayrollRuleSet::query()->orderByDesc('effective_from')->get(),
            'alerts' => [
                'contract' => Setting::get('hr.alert.contract_expiry_days', '30'),
                'trial' => Setting::get('hr.alert.trial_end_days', '15'),
                'document' => Setting::get('hr.alert.document_expiry_days', '30'),
            ],
            'lateThreshold' => Setting::get('hr.late_threshold_minutes', '5'),
            'jobTitles' => implode("\n", Setting::getList('hr.job_titles')),
            'workplaces' => implode("\n", Setting::getList('hr.workplaces')),
            'users' => User::query()->orderBy('name')->get(),
            'permissionLabels' => HrPermission::labels(),
        ]);
    }

    public function updateAlerts(Request $request)
    {
        $validated = $request->validate([
            'contract' => 'required|integer|min:1|max:180',
            'trial' => 'required|integer|min:1|max:180',
            'document' => 'required|integer|min:1|max:180',
        ]);

        Setting::set('hr.alert.contract_expiry_days', (string) $validated['contract'], 'Alerte contrats avant échéance (jours)');
        Setting::set('hr.alert.trial_end_days', (string) $validated['trial'], 'Alerte fin de période d\'essai (jours)');
        Setting::set('hr.alert.document_expiry_days', (string) $validated['document'], 'Alerte documents avant expiration (jours)');

        return back()->with('success', 'Délais d\'alertes RH enregistrés.');
    }

    public function updateOptions(Request $request)
    {
        $validated = $request->validate([
            'late_threshold' => 'required|integer|min:0|max:180',
            'job_titles' => 'nullable|string',
            'workplaces' => 'nullable|string',
        ]);

        Setting::set('hr.late_threshold_minutes', (string) $validated['late_threshold'], 'Seuil de retard (minutes)');
        Setting::setList('hr.job_titles', preg_split('/\r\n|\r|\n/', (string) $validated['job_titles']) ?: [], 'Fonctions / postes RH');
        Setting::setList('hr.workplaces', preg_split('/\r\n|\r|\n/', (string) $validated['workplaces']) ?: [], 'Lieux de travail');

        return back()->with('success', 'Règles de pointage et listes RH enregistrées.');
    }

    public function updatePermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:'.implode(',', HrPermission::keys()),
            'unrestricted' => 'sometimes|boolean',
        ]);

        if ($request->boolean('unrestricted')) {
            $user->update(['hr_permissions' => null]);
        } else {
            $user->update(['hr_permissions' => array_values($validated['permissions'] ?? [])]);
        }

        return back()->with('success', 'Droits RH mis à jour pour '.$user->name.'.');
    }

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:40|unique:hr_departments,code',
        ]);
        HrDepartment::create($validated);

        return back()->with('success', 'Service créé.');
    }

    public function storeLeaveType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:40|unique:leave_types,code',
            'paid' => 'sometimes|boolean',
            'requires_justification' => 'sometimes|boolean',
            'impacts_balance' => 'sometimes|boolean',
            'impacts_payroll' => 'sometimes|boolean',
        ]);
        $validated['paid'] = $request->boolean('paid');
        $validated['requires_justification'] = $request->boolean('requires_justification');
        $validated['impacts_balance'] = $request->boolean('impacts_balance');
        $validated['impacts_payroll'] = $request->boolean('impacts_payroll');
        LeaveType::create($validated);

        return back()->with('success', 'Type de congé créé.');
    }

    public function storeRuleSet(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'effective_from' => 'required|date',
            'notes' => 'nullable|string',
            'monthly_hours' => 'required|numeric|min:1',
            'overtime_multiplier' => 'required|numeric|min:1',
            'employee_cnss_rate' => 'required|numeric|min:0',
            'employer_cnss_rate' => 'required|numeric|min:0',
            'employee_amo_rate' => 'required|numeric|min:0',
            'employer_amo_rate' => 'required|numeric|min:0',
            'professional_expenses_rate' => 'required|numeric|min:0',
            'professional_expenses_cap' => 'required|numeric|min:0',
        ]);

        $latest = PayrollRuleSet::query()->orderByDesc('effective_from')->first();
        $brackets = $latest?->rule('ir_brackets', []) ?? [];

        PayrollRuleSet::create([
            'name' => $validated['name'],
            'effective_from' => $validated['effective_from'],
            'notes' => $validated['notes'] ?? null,
            'rules' => [
                'monthly_hours' => (float) $validated['monthly_hours'],
                'overtime_multiplier' => (float) $validated['overtime_multiplier'],
                'employee_cnss_rate' => (float) $validated['employee_cnss_rate'],
                'employer_cnss_rate' => (float) $validated['employer_cnss_rate'],
                'employee_amo_rate' => (float) $validated['employee_amo_rate'],
                'employer_amo_rate' => (float) $validated['employer_amo_rate'],
                'professional_expenses_rate' => (float) $validated['professional_expenses_rate'],
                'professional_expenses_cap' => (float) $validated['professional_expenses_cap'],
                'ir_brackets' => $brackets,
            ],
        ]);

        return back()->with('success', 'Nouvelle version des paramètres de paie créée. Les anciennes paies restent inchangées.');
    }
}
