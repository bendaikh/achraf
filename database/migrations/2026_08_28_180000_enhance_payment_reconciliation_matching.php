<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_import_rows')) {
            Schema::table('payment_import_rows', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_import_rows', 'file_delivery_fees')) {
                    $table->decimal('file_delivery_fees', 12, 2)->nullable()->after('file_amount');
                }
                if (! Schema::hasColumn('payment_import_rows', 'file_net_amount')) {
                    $table->decimal('file_net_amount', 12, 2)->nullable()->after('file_delivery_fees');
                }
                if (! Schema::hasColumn('payment_import_rows', 'match_confidence')) {
                    $table->string('match_confidence')->nullable()->after('match_status');
                }
                if (! Schema::hasColumn('payment_import_rows', 'match_criteria')) {
                    $table->json('match_criteria')->nullable()->after('match_confidence');
                }
                if (! Schema::hasColumn('payment_import_rows', 'match_score')) {
                    $table->unsignedSmallInteger('match_score')->nullable()->after('match_criteria');
                }
            });
        }

        if (Schema::hasTable('invoice_payments')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('invoice_payments', 'gross_amount')) {
                    $table->decimal('gross_amount', 12, 2)->nullable()->after('amount');
                }
                if (! Schema::hasColumn('invoice_payments', 'delivery_fees')) {
                    $table->decimal('delivery_fees', 12, 2)->nullable()->after('gross_amount');
                }
                if (! Schema::hasColumn('invoice_payments', 'net_received')) {
                    $table->decimal('net_received', 12, 2)->nullable()->after('delivery_fees');
                }
            });
        }

        if (! Schema::hasTable('invoice_activities')) {
            Schema::create('invoice_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event')->index();
                $table->text('description');
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_activities');

        if (Schema::hasTable('invoice_payments')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                foreach (['gross_amount', 'delivery_fees', 'net_received'] as $column) {
                    if (Schema::hasColumn('invoice_payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('payment_import_rows')) {
            Schema::table('payment_import_rows', function (Blueprint $table) {
                foreach (['file_delivery_fees', 'file_net_amount', 'match_confidence', 'match_criteria', 'match_score'] as $column) {
                    if (Schema::hasColumn('payment_import_rows', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
