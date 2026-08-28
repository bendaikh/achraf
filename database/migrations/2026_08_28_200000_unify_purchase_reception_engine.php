<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->foreignId('supplier_delivery_note_id')
                ->nullable()
                ->after('supplier_purchase_order_id')
                ->constrained('supplier_delivery_notes')
                ->nullOnDelete();

            $table->foreignId('source_supplier_invoice_id')
                ->nullable()
                ->after('supplier_delivery_note_id')
                ->constrained('supplier_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_supplier_invoice_id');
            $table->dropConstrainedForeignId('supplier_delivery_note_id');
        });
    }
};
