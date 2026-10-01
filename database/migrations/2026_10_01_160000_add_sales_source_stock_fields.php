<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'warehouse_id')) {
                $table->foreignId('warehouse_id')
                    ->nullable()
                    ->after('product_variant_id')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('invoice_items', 'warehouse_location_id')) {
                $table->foreignId('warehouse_location_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('warehouse_locations')
                    ->nullOnDelete();
            }
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_notes', 'stock_applied_at')) {
                $table->timestamp('stock_applied_at')->nullable()->after('converted_to_invoice_at');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'stock_applied_at')) {
                $table->timestamp('stock_applied_at')->nullable()->after('stock_location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'warehouse_location_id')) {
                $table->dropConstrainedForeignId('warehouse_location_id');
            }
            if (Schema::hasColumn('invoice_items', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_notes', 'stock_applied_at')) {
                $table->dropColumn('stock_applied_at');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'stock_applied_at')) {
                $table->dropColumn('stock_applied_at');
            }
        });
    }
};
