<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['quotes', 'purchase_orders', 'delivery_notes', 'invoices', 'credit_notes'];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'collaborator_id')) {
                    $table->foreignId('collaborator_id')
                        ->nullable()
                        ->after('client_id')
                        ->constrained('collaborators')
                        ->nullOnDelete();
                    $table->index('collaborator_id');
                }

                if (! Schema::hasColumn($tableName, 'created_by_user_id')) {
                    $table->foreignId('created_by_user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('invoice_payments') && ! Schema::hasColumn('invoice_payments', 'collaborator_id')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->foreignId('collaborator_id')
                    ->nullable()
                    ->constrained('collaborators')
                    ->nullOnDelete();
            });
        }

        // Freelance remuneration ledger (Phase 5) — separate from salarié payroll
        if (! Schema::hasTable('freelance_payouts')) {
            Schema::create('freelance_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collaborator_id')->constrained('collaborators')->cascadeOnDelete();
                $table->decimal('amount_due', 14, 2)->default(0);
                $table->decimal('amount_validated', 14, 2)->default(0);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->date('paid_at')->nullable();
                $table->string('payment_method')->nullable();
                $table->string('payment_reference')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            Schema::create('freelance_payout_commission', function (Blueprint $table) {
                $table->id();
                $table->foreignId('freelance_payout_id')->constrained('freelance_payouts')->cascadeOnDelete();
                $table->foreignId('commission_id')->constrained('commissions')->cascadeOnDelete();
                $table->unique(['freelance_payout_id', 'commission_id'], 'fp_commission_unique');
            });
        }

        // Optional link commission → payroll variable (validated, not auto)
        if (Schema::hasTable('commissions') && ! Schema::hasColumn('commissions', 'payroll_linked_at')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->timestamp('payroll_linked_at')->nullable()->after('notes');
                $table->foreignId('payroll_adjustment_id')->nullable()->after('payroll_linked_at')
                    ->constrained('payroll_adjustments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commissions') && Schema::hasColumn('commissions', 'payroll_linked_at')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payroll_adjustment_id');
                $table->dropColumn('payroll_linked_at');
            });
        }

        Schema::dropIfExists('freelance_payout_commission');
        Schema::dropIfExists('freelance_payouts');

        if (Schema::hasTable('invoice_payments') && Schema::hasColumn('invoice_payments', 'collaborator_id')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('collaborator_id');
            });
        }

        foreach (['quotes', 'purchase_orders', 'delivery_notes', 'invoices', 'credit_notes'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'collaborator_id')) {
                    $table->dropConstrainedForeignId('collaborator_id');
                }
                if (Schema::hasColumn($tableName, 'created_by_user_id')) {
                    $table->dropConstrainedForeignId('created_by_user_id');
                }
            });
        }
    }
};
