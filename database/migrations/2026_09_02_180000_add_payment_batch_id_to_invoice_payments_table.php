<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_payments')) {
            return;
        }

        Schema::table('invoice_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_payments', 'payment_batch_id')) {
                $table->uuid('payment_batch_id')->nullable()->after('source')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_payments')) {
            return;
        }

        Schema::table('invoice_payments', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_payments', 'payment_batch_id')) {
                $table->dropColumn('payment_batch_id');
            }
        });
    }
};
