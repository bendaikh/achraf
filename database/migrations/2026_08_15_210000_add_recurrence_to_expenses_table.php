<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('paid')->after('invoice_file_path')->index();
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->boolean('is_recurring')->default(false)->after('paid_at')->index();
            $table->string('recurrence_frequency', 20)->nullable()->after('is_recurring');
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_frequency');
            $table->string('recurrence_interval_unit', 10)->nullable()->after('recurrence_interval');
            $table->date('recurrence_start_date')->nullable()->after('recurrence_interval_unit');
            $table->date('recurrence_end_date')->nullable()->after('recurrence_start_date');
            $table->date('next_due_date')->nullable()->after('recurrence_end_date')->index();
            $table->string('recurrence_status', 20)->nullable()->after('next_due_date');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_status')
                ->constrained('expenses')->nullOnDelete();
            $table->date('occurrence_date')->nullable()->after('recurrence_parent_id');

            $table->unique(['recurrence_parent_id', 'occurrence_date'], 'expenses_recurrence_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_recurrence_occurrence_unique');
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropColumn([
                'payment_status',
                'paid_at',
                'is_recurring',
                'recurrence_frequency',
                'recurrence_interval',
                'recurrence_interval_unit',
                'recurrence_start_date',
                'recurrence_end_date',
                'next_due_date',
                'recurrence_status',
                'recurrence_parent_id',
                'occurrence_date',
            ]);
        });
    }
};
