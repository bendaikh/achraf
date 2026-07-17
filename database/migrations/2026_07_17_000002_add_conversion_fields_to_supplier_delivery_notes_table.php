<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_delivery_notes', function (Blueprint $table) {
            $table->foreignId('converted_supplier_invoice_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('supplier_invoices')
                ->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_supplier_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_delivery_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_supplier_invoice_id');
            $table->dropColumn('converted_at');
        });
    }
};
