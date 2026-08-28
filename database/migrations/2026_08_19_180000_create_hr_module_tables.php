<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 20)->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->date('birth_date')->nullable();
            $table->string('cin', 40)->nullable()->index();
            $table->string('nationality')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('cnss_number')->nullable();
            $table->string('amo_number')->nullable();
            $table->string('rib')->nullable();
            $table->string('bank_name')->nullable();
            $table->date('hire_date');
            $table->string('job_title')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('workplace')->nullable();
            $table->string('status', 20)->default('actif')->index();
            $table->string('timeclock_external_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('commission_eligible')->default(false);
            $table->decimal('initial_leave_balance', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('salary', 15, 2)->nullable();
            $table->date('trial_end_date')->nullable();
            $table->string('status', 20)->default('en_cours')->index();
            $table->foreignId('previous_contract_id')->nullable()->constrained('employee_contracts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->boolean('is_off')->default(false);
            $table->timestamps();
            $table->unique(['employee_id', 'weekday']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status', 30)->default('present')->index();
            $table->string('source', 30)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->string('field', 40);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->boolean('paid')->default(true);
            $table->boolean('requires_justification')->default(false);
            $table->boolean('impacts_balance')->default(true);
            $table->boolean('impacts_payroll')->default(false);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 8, 2);
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_balance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('type', 30);
            $table->decimal('days', 8, 2);
            $table->decimal('balance_after', 8, 2);
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 8, 2);
            $table->text('comment')->nullable();
            $table->boolean('impacts_payroll')->default(true);
            $table->timestamps();
        });

        Schema::create('salary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date');
            $table->decimal('base_salary', 15, 2);
            $table->string('negotiated_as', 10)->default('brut');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('compensation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('recurrence', 20)->default('ponctuel');
            $table->decimal('amount', 15, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('effective_from');
            $table->json('rules');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('effective_from');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('status', 20)->default('brouillon')->index();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['period_year', 'period_month']);
        });

        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('gross', 15, 2)->default(0);
            $table->decimal('net', 15, 2)->default(0);
            $table->decimal('primes', 15, 2)->default(0);
            $table->decimal('indemnites', 15, 2)->default(0);
            $table->decimal('overtime_amount', 15, 2)->default(0);
            $table->decimal('absence_deduction', 15, 2)->default(0);
            $table->decimal('retenues', 15, 2)->default(0);
            $table->decimal('avances', 15, 2)->default(0);
            $table->decimal('employee_contributions', 15, 2)->default(0);
            $table->decimal('income_tax', 15, 2)->default(0);
            $table->decimal('employer_contributions', 15, 2)->default(0);
            $table->decimal('employer_cost', 15, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_slip_id')->constrained()->cascadeOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 15, 2);
            $table->string('method', 40)->default('virement');
            $table->string('account', 20)->default('banque');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_exits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('exit_date');
            $table->date('last_work_date')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('leave_balance_settlement', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('event_date');
            $table->string('type', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'event_date']);
        });

        Schema::create('hr_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('auditable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::table('managed_documents', function (Blueprint $table) {
            $table->date('expires_at')->nullable()->index();
        });

        $now = now();

        DB::table('leave_types')->insert([
            ['name' => 'Congé payé', 'code' => 'cp', 'paid' => 1, 'requires_justification' => 0, 'impacts_balance' => 1, 'impacts_payroll' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Congé sans solde', 'code' => 'css', 'paid' => 0, 'requires_justification' => 0, 'impacts_balance' => 0, 'impacts_payroll' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Maladie', 'code' => 'maladie', 'paid' => 1, 'requires_justification' => 1, 'impacts_balance' => 0, 'impacts_payroll' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Événement familial', 'code' => 'famille', 'paid' => 1, 'requires_justification' => 1, 'impacts_balance' => 0, 'impacts_payroll' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('payroll_rule_sets')->insert([
            'name' => 'Paramètres paie (modèle)',
            'effective_from' => '2026-01-01',
            'rules' => json_encode([
                'monthly_hours' => 191,
                'overtime_multiplier' => 1.25,
                'late_impacts_payroll' => false,
                'employee_cnss_rate' => 4.48,
                'employer_cnss_rate' => 8.98,
                'employee_amo_rate' => 2.26,
                'employer_amo_rate' => 2.26,
                'professional_expenses_rate' => 20,
                'professional_expenses_cap' => 2500,
                'ir_brackets' => [
                    ['up_to' => 2500, 'rate' => 0, 'deduction' => 0],
                    ['up_to' => 4166.67, 'rate' => 10, 'deduction' => 250],
                    ['up_to' => 5000, 'rate' => 20, 'deduction' => 666.67],
                    ['up_to' => 6666.67, 'rate' => 30, 'deduction' => 1166.67],
                    ['up_to' => 15000, 'rate' => 34, 'deduction' => 1433.33],
                    ['up_to' => null, 'rate' => 37, 'deduction' => 1883.33],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'notes' => 'Valeurs indicatives à ajuster selon la réglementation en vigueur. Créer une nouvelle version en cas de changement de taux.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('settings')->insert([
            ['key' => 'hr.alert.contract_expiry_days', 'value' => '30', 'description' => 'Alerte contrats avant échéance (jours)', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'hr.alert.trial_end_days', 'value' => '15', 'description' => 'Alerte fin de période d\'essai (jours)', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'hr.alert.document_expiry_days', 'value' => '30', 'description' => 'Alerte documents avant expiration (jours)', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('managed_documents', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });

        Schema::dropIfExists('hr_audit_logs');
        Schema::dropIfExists('hr_events');
        Schema::dropIfExists('employee_exits');
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_rule_sets');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('compensation_items');
        Schema::dropIfExists('salary_records');
        Schema::dropIfExists('employee_absences');
        Schema::dropIfExists('leave_balance_entries');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employee_schedules');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('hr_departments');
    }
};
