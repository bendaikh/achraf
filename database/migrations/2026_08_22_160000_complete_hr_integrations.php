<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->date('effective_from')->default('1970-01-01')->after('employee_id');
        });

        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->dropUnique('employee_schedules_employee_id_weekday_unique');
            $table->unique(['employee_id', 'weekday', 'effective_from']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('early_minutes')->default(0)->after('late_minutes');
            $table->boolean('is_incomplete')->default(false)->after('overtime_minutes');
        });

        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->date('trial_start_date')->nullable()->after('salary');
            $table->string('workplace')->nullable()->after('job_title');
        });

        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->decimal('remaining_amount', 15, 2)->nullable()->after('amount');
            $table->string('payment_method', 40)->nullable()->after('reason');
            $table->string('reference')->nullable()->after('payment_method');
            $table->timestamp('recovered_at')->nullable()->after('reference');
        });

        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('account');
            $table->string('proof_path')->nullable()->after('reference');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('hr_permissions')->nullable()->after('remember_token');
        });

        DB::table('payroll_adjustments')->whereNull('remaining_amount')->update([
            'remaining_amount' => DB::raw('amount'),
        ]);

        $now = now();
        $settings = [
            ['key' => 'hr.late_threshold_minutes', 'value' => '5', 'description' => 'Seuil de retard (minutes) avant impact'],
            ['key' => 'hr.job_titles', 'value' => '[]', 'description' => 'Fonctions / postes RH'],
            ['key' => 'hr.workplaces', 'value' => '[]', 'description' => 'Lieux de travail'],
        ];
        foreach ($settings as $row) {
            if (! DB::table('settings')->where('key', $row['key'])->exists()) {
                DB::table('settings')->insert($row + ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hr_permissions');
        });
        Schema::table('payroll_payments', function (Blueprint $table) {
            $table->dropColumn(['reference', 'proof_path']);
        });
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->dropColumn(['remaining_amount', 'payment_method', 'reference', 'recovered_at']);
        });
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropColumn(['trial_start_date', 'workplace']);
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['early_minutes', 'is_incomplete']);
        });
        Schema::table('employee_schedules', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'weekday', 'effective_from']);
            $table->unique(['employee_id', 'weekday']);
            $table->dropColumn('effective_from');
        });
    }
};
