<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\FinancialMovement;
use App\Models\LeaveBalanceEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\EmployeeMatriculeService;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\LeaveBalanceService;
use App\Services\Hr\PayrollEngine;
use App\Services\Hr\PayrollService;
use App\Support\Navigation;
use App\Support\SoftNavigation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_appears_once_in_main_navigation_with_internal_tabs(): void
    {
        $user = User::factory()->create();
        $modules = Navigation::modules($user);
        $hr = collect($modules)->firstWhere('key', 'hr');

        $this->assertNotNull($hr);
        $this->assertSame('RH', $hr['label']);
        $this->assertCount(1, collect($modules)->where('key', 'hr'));
        $this->assertSame([
            'Tableau de bord RH',
            'Salariés',
            'Contrats',
            'Présences & Pointage',
            'Congés & Absences',
            'Rémunération / Paie',
            'Primes & Indemnités',
            'Documents RH',
            'Rapports RH',
            'Historique RH',
            'Paramètres RH',
        ], array_column($hr['children'], 'label'));
    }

    public function test_matricule_is_automatic_and_hire_date_is_independent(): void
    {
        $this->actingAs(User::factory()->create());
        Carbon::setTestNow('2026-08-19');

        $employee = app(EmployeeService::class)->create([
            'last_name' => 'Alaoui',
            'first_name' => 'Karim',
            'hire_date' => '2025-09-01',
            'job_title' => 'Commercial',
            'status' => Employee::STATUS_ACTIF,
        ]);

        $this->assertSame('EMP-0001', $employee->matricule);
        $this->assertSame('2025-09-01', $employee->hire_date->toDateString());
        $this->assertSame('2026-08-19', $employee->created_at->toDateString());
        $this->assertTrue($employee->events()->where('type', 'hire')->whereDate('event_date', '2025-09-01')->exists());
        $this->assertNull($employee->user_id);
        $this->assertFalse($employee->commission_eligible);
    }

    public function test_contracts_are_kept_on_renewal(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $service = app(EmployeeService::class);

        $first = $service->addContract($employee, [
            'type' => EmployeeContract::TYPE_CDD,
            'start_date' => '2025-09-01',
            'end_date' => '2026-02-28',
            'status' => EmployeeContract::STATUS_EN_COURS,
            'job_title' => 'Commercial',
            'salary' => 4000,
        ]);

        $second = $service->addContract($employee, [
            'type' => EmployeeContract::TYPE_CDI,
            'start_date' => '2026-03-01',
            'status' => EmployeeContract::STATUS_EN_COURS,
            'job_title' => 'Commercial',
            'salary' => 5000,
        ], renew: true);

        $this->assertSame(EmployeeContract::STATUS_RENOUVELE, $first->fresh()->status);
        $this->assertSame(EmployeeContract::STATUS_EN_COURS, $second->status);
        $this->assertSame($first->id, $second->previous_contract_id);
        $this->assertSame(2, $employee->contracts()->count());
    }

    public function test_attendance_correction_is_traced(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $employee = Employee::factory()->create();
        $service = app(AttendanceService::class);

        $service->upsertManual($employee, [
            'work_date' => '2026-08-18',
            'clock_in' => '09:00',
            'clock_out' => '17:00',
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $record = $service->upsertManual($employee, [
            'work_date' => '2026-08-18',
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'status' => AttendanceRecord::STATUS_PRESENT,
        ], 'Oubli de pointage sortie', $user->id);

        $this->assertSame(1, $record->corrections()->count());
        $correction = $record->corrections()->first();
        $this->assertSame('clock_out', $correction->field);
        $this->assertSame($user->id, $correction->user_id);
        $this->assertSame('Oubli de pointage sortie', $correction->reason);
    }

    public function test_timeclock_punch_binds_via_external_id(): void
    {
        $employee = Employee::factory()->create(['timeclock_external_id' => 'BADGE-42', 'matricule' => 'EMP-0009']);
        $record = app(AttendanceService::class)->ingestTimeclockPunch([
            'external_id' => 'BADGE-42',
            'punched_at' => '2026-08-18 08:03:00',
            'type' => 'in',
        ]);

        $this->assertNotNull($record);
        $this->assertSame($employee->id, $record->employee_id);
        $this->assertSame(AttendanceRecord::SOURCE_TIMECLOCK, $record->source);
        $this->assertSame('08:03:00', $record->clock_in);
    }

    public function test_schedule_effective_from_drives_late_and_overtime(): void
    {
        $employee = Employee::factory()->create();
        app(AttendanceService::class)->seedDefaultSchedule($employee);

        $record = app(AttendanceService::class)->upsertManual($employee, [
            'work_date' => '2026-08-18',
            'clock_in' => '09:17',
            'clock_out' => '19:10',
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $this->assertSame(17, $record->late_minutes);
        $this->assertSame(70, $record->overtime_minutes);
        $this->assertSame(AttendanceRecord::STATUS_LATE, $record->status);
    }

    public function test_prepare_month_pulls_primes_and_advances(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create(['status' => Employee::STATUS_ACTIF]);
        \App\Models\SalaryRecord::create([
            'employee_id' => $employee->id,
            'effective_date' => '2026-01-01',
            'base_salary' => 5000,
            'negotiated_as' => SalaryRecord::AS_BRUT,
        ]);
        \App\Models\CompensationItem::create([
            'employee_id' => $employee->id,
            'kind' => \App\Models\CompensationItem::KIND_INDEMNITE_TRANSPORT,
            'recurrence' => \App\Models\CompensationItem::RECURRENCE_RECURRENT,
            'amount' => 300,
            'start_date' => '2026-01-01',
        ]);
        \App\Models\PayrollAdjustment::create([
            'employee_id' => $employee->id,
            'type' => \App\Models\PayrollAdjustment::TYPE_AVANCE,
            'amount' => 1000,
            'remaining_amount' => 1000,
            'period_year' => 2026,
            'period_month' => 8,
            'reason' => 'Acompte',
        ]);

        $run = app(PayrollService::class)->prepare(2026, 8);
        $slip = $run->slips->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($slip);
        $this->assertEqualsWithDelta(300.0, (float) $slip->indemnites, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $slip->avances, 0.01);
    }

    public function test_leave_balance_ledger_explains_the_solde(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create(['initial_leave_balance' => 5, 'hire_date' => '2025-09-01']);
        $balances = app(LeaveBalanceService::class);
        $balances->ensureInitialBalance($employee);
        $balances->addEntry($employee, LeaveBalanceEntry::TYPE_ACCRUAL, 1.5, '2026-01-01', 'Droits acquis');

        $type = LeaveType::query()->where('code', 'cp')->first();
        $this->assertNotNull($type);
        $this->assertTrue((bool) $type->impacts_balance);

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-14',
            'days' => 5,
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);
        $balances->applyApprovedLeave($leave->fresh(['leaveType', 'employee']));

        $this->assertEquals(1.5, $balances->currentBalance($employee));
        $this->assertSame(3, LeaveBalanceEntry::query()->where('employee_id', $employee->id)->count());
    }

    public function test_payroll_rules_are_versioned_and_payment_creates_treasury_movement(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $employee = Employee::factory()->create(['status' => Employee::STATUS_ACTIF]);
        SalaryRecord::create([
            'employee_id' => $employee->id,
            'effective_date' => '2026-01-01',
            'base_salary' => 5000,
            'negotiated_as' => SalaryRecord::AS_NET,
        ]);

        $sim = app(PayrollEngine::class)->simulate($employee, Carbon::parse('2026-08-01'));
        $this->assertGreaterThan($sim['net'], $sim['gross']);
        $this->assertEqualsWithDelta(5000.0, $sim['net'], 1);
        $this->assertGreaterThan($sim['net'], $sim['employer_cost']);

        $payroll = app(PayrollService::class);
        $run = $payroll->prepare(2026, 8);
        $payroll->calculate($run);
        $payroll->transition($run->fresh(), PayrollRun::STATUS_VERIFIEE);
        $payroll->transition($run->fresh(), PayrollRun::STATUS_VALIDEE);

        $slip = $run->fresh('slips')->slips->first();
        $payment = $payroll->paySlip($slip, [
            'paid_at' => '2026-08-31',
            'method' => 'virement',
            'account' => 'banque',
        ]);

        $this->assertDatabaseHas('financial_movements', [
            'source_type' => $payment->getMorphClass(),
            'source_id' => $payment->id,
            'origin' => FinancialMovement::ORIGIN_SALAIRE,
            'type' => FinancialMovement::TYPE_SORTIE,
        ]);
        $movement = FinancialMovement::query()->where('source_id', $payment->id)->first();
        $this->assertEquals((float) $slip->net, (float) $movement->amount_out);
        $this->assertStringContainsString('hors charges', (string) $movement->notes);
    }

    public function test_exit_does_not_delete_employee(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        app(EmployeeService::class)->exit($employee, [
            'exit_date' => '2026-08-31',
            'reason' => 'Démission',
        ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'status' => Employee::STATUS_SORTI,
        ]);
        $this->assertTrue($employee->fresh()->events()->where('type', 'exit')->exists());
    }

    public function test_hr_pages_are_reachable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('hr.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('hr.employees.index'))->assertOk();
        $this->actingAs($user)->get(route('hr.contracts.index'))->assertOk();
        $this->actingAs($user)->get(route('hr.attendance.index'))->assertOk();
        $this->actingAs($user)->get(route('hr.leaves.index'))->assertOk();
        $this->actingAs($user)->get(route('hr.payroll.index'))->assertOk();
        $this->actingAs($user)->get(route('hr.compensations.index'))->assertOk();
        $this->actingAs($user)->get(route('hr.documents.index'))->assertOk();
        $this->actingAs($user)->get(route('hr.history.index'))->assertOk();
        $this->actingAs($user)->get(route('hr.settings.index'))->assertOk();
        $this->actingAs($user)->get(route('hr.reports.index'))->assertOk();

        $response = $this->actingAs($user)->get(route('hr.dashboard'), [
            SoftNavigation::HEADER => '1',
            'Accept' => 'application/json',
        ]);
        $response->assertOk()->assertJsonPath('module', 'hr');
    }

    public function test_next_matricule_increments(): void
    {
        Employee::factory()->create(['matricule' => 'EMP-0001']);
        $this->assertSame('EMP-0002', app(EmployeeMatriculeService::class)->next());
    }
}
