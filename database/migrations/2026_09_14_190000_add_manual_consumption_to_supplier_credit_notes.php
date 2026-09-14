<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_credit_notes', function (Blueprint $table) {
            $table->timestamp('manually_consumed_at')->nullable()->after('total');
            $table->date('manually_consumed_date')->nullable()->after('manually_consumed_at');
            $table->text('manually_consumed_note')->nullable()->after('manually_consumed_date');
            $table->foreignId('manually_consumed_by')->nullable()->after('manually_consumed_note')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_credit_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manually_consumed_by');
            $table->dropColumn([
                'manually_consumed_at',
                'manually_consumed_date',
                'manually_consumed_note',
            ]);
        });
    }
};
