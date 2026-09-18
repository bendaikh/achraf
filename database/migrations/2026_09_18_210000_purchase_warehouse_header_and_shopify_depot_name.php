<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_purchase_orders', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('stock_location')
                    ->constrained('warehouses')->nullOnDelete();
            }
        });

        // Align display name with purchase UI / Shopify online dépôt.
        DB::table('warehouses')
            ->where('code', 'SHOPIFY')
            ->update(['name' => 'SHOPIFY STOCK EN LIGNE']);

        DB::table('warehouses')
            ->where('kind', 'online')
            ->where('name', 'Stock Shopify / En ligne')
            ->update(['name' => 'SHOPIFY STOCK EN LIGNE']);
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_purchase_orders', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });

        DB::table('warehouses')
            ->where('code', 'SHOPIFY')
            ->where('name', 'SHOPIFY STOCK EN LIGNE')
            ->update(['name' => 'Stock Shopify / En ligne']);
    }
};
