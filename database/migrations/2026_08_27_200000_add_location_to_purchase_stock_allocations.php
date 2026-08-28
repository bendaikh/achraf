<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_stock_allocations', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_stock_allocations', 'warehouse_location_id')) {
                $table->foreignId('warehouse_location_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('warehouse_locations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_stock_allocations', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_stock_allocations', 'warehouse_location_id')) {
                $table->dropConstrainedForeignId('warehouse_location_id');
            }
        });
    }
};
