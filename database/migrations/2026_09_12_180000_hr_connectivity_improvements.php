<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_contracts', 'department_name')) {
                $table->string('department_name')->nullable()->after('workplace');
            }
            if (! Schema::hasColumn('employee_contracts', 'is_amendment')) {
                $table->boolean('is_amendment')->default(false)->after('status');
            }
        });

        Schema::table('compensation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('compensation_items', 'status')) {
                $table->string('status', 20)->default('actif')->after('recurrence');
            }
            if (! Schema::hasColumn('compensation_items', 'status_effective_date')) {
                $table->date('status_effective_date')->nullable()->after('end_date');
            }
        });

        Schema::table('payroll_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustments', 'monthly_amount')) {
                $table->decimal('monthly_amount', 15, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('payroll_adjustments', 'start_date')) {
                $table->date('start_date')->nullable()->after('period_month');
            }
            if (! Schema::hasColumn('payroll_adjustments', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('payroll_adjustments', 'status')) {
                $table->string('status', 20)->default('actif')->after('end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->dropColumn(['monthly_amount', 'start_date', 'end_date', 'status']);
        });

        Schema::table('compensation_items', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_effective_date']);
        });

        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropColumn(['department_name', 'is_amendment']);
        });
    }
};
