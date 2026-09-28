<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_cards', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('account', 32)->default('banque'); // caisse|banque|other
            $table->string('last_four', 4)->nullable();
            $table->string('currency', 10)->default('MAD');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('endowments', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('type')->nullable(); // carte|caisse|autre
            $table->string('currency', 10)->default('MAD');
            $table->decimal('initial_amount', 14, 2);
            $table->decimal('consumed_amount', 14, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('bank_account', 32)->default('banque');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('endowment_bank_card', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endowment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_card_id')->constrained()->cascadeOnDelete();
            $table->unique(['endowment_id', 'bank_card_id']);
        });

        Schema::create('endowment_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endowment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_card_id')->nullable()->constrained()->nullOnDelete();
            $table->date('consumed_on');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('currency', 10)->default('MAD');
            $table->decimal('amount_currency', 14, 2)->default(0);
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('amount_mad', 14, 2);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['endowment_id', 'consumed_on']);
        });

        Schema::create('finance_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->string('account', 32); // caisse|banque|other
            $table->date('as_of_date'); // official cutoff
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 10)->default('MAD');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['account', 'as_of_date']);
        });

        Schema::create('finance_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 32)->default('open'); // open|closed
            $table->text('close_reason')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
        });

        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->string('account', 32)->default('banque');
            $table->string('original_filename');
            $table->string('stored_path')->nullable();
            $table->string('status', 32)->default('imported'); // imported|matched|archived
            $table->unsignedInteger('lines_count')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();
            $table->date('operation_date')->nullable();
            $table->date('value_date')->nullable();
            $table->string('label')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->nullable();
            $table->string('status', 32)->default('unmatched'); // unmatched|suggested|matched|variance
            $table->foreignId('financial_movement_id')->nullable()->constrained('financial_movements')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'operation_date']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'amount_paid')) {
                $table->decimal('amount_paid', 14, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('expenses', 'bank_card_id')) {
                $table->foreignId('bank_card_id')->nullable()->after('account')->constrained('bank_cards')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'endowment_id')) {
                $table->foreignId('endowment_id')->nullable()->after('bank_card_id')->constrained('endowments')->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'amount_currency')) {
                $table->decimal('amount_currency', 14, 2)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('expenses', 'exchange_rate')) {
                $table->decimal('exchange_rate', 14, 6)->nullable()->after('amount_currency');
            }
        });

        Schema::table('financial_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_movements', 'bank_card_id')) {
                $table->foreignId('bank_card_id')->nullable()->after('account')->constrained('bank_cards')->nullOnDelete();
            }
            if (! Schema::hasColumn('financial_movements', 'endowment_id')) {
                $table->foreignId('endowment_id')->nullable()->after('bank_card_id')->constrained('endowments')->nullOnDelete();
            }
            if (! Schema::hasColumn('financial_movements', 'is_opening_balance')) {
                $table->boolean('is_opening_balance')->default(false)->after('is_manual');
            }
        });

        // Seed official finance cutoff setting.
        if (Schema::hasTable('settings')) {
            $exists = DB::table('settings')->where('key', 'finance_cutoff_date')->exists();
            if (! $exists) {
                DB::table('settings')->insert([
                    'key' => 'finance_cutoff_date',
                    'value' => '2026-10-01',
                    'description' => 'Date de bascule Finance (soldes d’ouverture)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('financial_movements', function (Blueprint $table) {
            if (Schema::hasColumn('financial_movements', 'is_opening_balance')) {
                $table->dropColumn('is_opening_balance');
            }
            if (Schema::hasColumn('financial_movements', 'endowment_id')) {
                $table->dropConstrainedForeignId('endowment_id');
            }
            if (Schema::hasColumn('financial_movements', 'bank_card_id')) {
                $table->dropConstrainedForeignId('bank_card_id');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            foreach (['exchange_rate', 'amount_currency', 'endowment_id', 'bank_card_id', 'amount_paid'] as $col) {
                if (Schema::hasColumn('expenses', $col)) {
                    if (in_array($col, ['endowment_id', 'bank_card_id'], true)) {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });

        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('finance_periods');
        Schema::dropIfExists('finance_opening_balances');
        Schema::dropIfExists('endowment_consumptions');
        Schema::dropIfExists('endowment_bank_card');
        Schema::dropIfExists('endowments');
        Schema::dropIfExists('bank_cards');
    }
};
