<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('converted_purchase_order_id')
                ->nullable()
                ->after('status')
                ->constrained('purchase_orders')
                ->nullOnDelete();
            $table->timestamp('converted_to_purchase_order_at')->nullable()->after('converted_purchase_order_id');
            $table->foreignId('converted_delivery_note_id')
                ->nullable()
                ->after('converted_to_purchase_order_at')
                ->constrained('delivery_notes')
                ->nullOnDelete();
            $table->timestamp('converted_to_delivery_note_at')->nullable()->after('converted_delivery_note_id');
            $table->foreignId('converted_invoice_id')
                ->nullable()
                ->after('converted_to_delivery_note_at')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->timestamp('converted_to_invoice_at')->nullable()->after('converted_invoice_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('converted_delivery_note_id')
                ->nullable()
                ->after('status')
                ->constrained('delivery_notes')
                ->nullOnDelete();
            $table->timestamp('converted_to_delivery_note_at')->nullable()->after('converted_delivery_note_id');
            $table->foreignId('converted_invoice_id')
                ->nullable()
                ->after('converted_to_delivery_note_at')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->timestamp('converted_to_invoice_at')->nullable()->after('converted_invoice_id');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->foreignId('converted_invoice_id')
                ->nullable()
                ->after('status')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->timestamp('converted_to_invoice_at')->nullable()->after('converted_invoice_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('source_document_reference')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('source_document_reference');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_invoice_id');
            $table->dropColumn('converted_to_invoice_at');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_delivery_note_id');
            $table->dropConstrainedForeignId('converted_invoice_id');
            $table->dropColumn(['converted_to_delivery_note_at', 'converted_to_invoice_at']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_purchase_order_id');
            $table->dropConstrainedForeignId('converted_delivery_note_id');
            $table->dropConstrainedForeignId('converted_invoice_id');
            $table->dropColumn([
                'converted_to_purchase_order_at',
                'converted_to_delivery_note_at',
                'converted_to_invoice_at',
            ]);
        });
    }
};
