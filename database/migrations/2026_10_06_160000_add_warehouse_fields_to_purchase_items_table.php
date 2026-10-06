<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_items', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()
                    ->constrained('warehouses')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchase_items', 'warehouse_location_id')) {
                $table->foreignId('warehouse_location_id')->nullable()
                    ->constrained('warehouse_locations')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_items', 'warehouse_location_id')) {
                $table->dropConstrainedForeignId('warehouse_location_id');
            }
            if (Schema::hasColumn('purchase_items', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });
    }
};
