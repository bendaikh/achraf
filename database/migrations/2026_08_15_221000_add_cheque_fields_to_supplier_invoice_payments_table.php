<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoice_payments', function (Blueprint $table) {
            $table->string('cheque_number')->nullable()->after('payment_reference');
            $table->string('cheque_bank')->nullable()->after('cheque_number');
            $table->date('cheque_date')->nullable()->after('cheque_bank');
            $table->date('cheque_due_date')->nullable()->after('cheque_date');
            $table->string('cheque_beneficiary')->nullable()->after('cheque_due_date');
            $table->string('cheque_status', 32)->nullable()->after('cheque_beneficiary');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoice_payments', function (Blueprint $table) {
            $table->dropColumn([
                'cheque_number',
                'cheque_bank',
                'cheque_date',
                'cheque_due_date',
                'cheque_beneficiary',
                'cheque_status',
            ]);
        });
    }
};
