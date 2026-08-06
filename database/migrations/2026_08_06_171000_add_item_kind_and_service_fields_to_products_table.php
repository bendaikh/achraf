<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columns may already exist if a previous same-timestamp migration applied them.
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'item_kind')) {
                $table->string('item_kind', 32)->default('stocked')->after('element_type');
            }
            if (! Schema::hasColumn('products', 'maximum_stock')) {
                $table->unsignedInteger('maximum_stock')->nullable()->after('minimum_alert_stock');
            }
            if (! Schema::hasColumn('products', 'location')) {
                $table->string('location')->nullable()->after('maximum_stock');
            }
            if (! Schema::hasColumn('products', 'primary_supplier_id')) {
                $table->foreignId('primary_supplier_id')->nullable()->after('location')
                    ->constrained('suppliers')->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'service_category')) {
                $table->string('service_category')->nullable()->after('product_category');
            }
            if (! Schema::hasColumn('products', 'estimated_duration')) {
                $table->string('estimated_duration')->nullable()->after('service_category');
            }
            if (! Schema::hasColumn('products', 'billing_unit')) {
                $table->string('billing_unit')->nullable()->after('estimated_duration');
            }
            if (! Schema::hasColumn('products', 'technician_required')) {
                $table->boolean('technician_required')->default(false)->after('billing_unit');
            }
        });

        if (Schema::hasColumn('products', 'item_kind')) {
            DB::table('products')
                ->whereRaw("LOWER(COALESCE(element_type, '')) = 'service'")
                ->where(function ($q) {
                    $q->whereNull('item_kind')->orWhere('item_kind', '');
                })
                ->update(['item_kind' => 'service']);

            DB::table('products')
                ->where(function ($q) {
                    $q->whereNull('item_kind')->orWhere('item_kind', '');
                })
                ->update(['item_kind' => 'stocked']);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'primary_supplier_id')) {
                $table->dropConstrainedForeignId('primary_supplier_id');
            }
            foreach ([
                'item_kind',
                'maximum_stock',
                'location',
                'service_category',
                'estimated_duration',
                'billing_unit',
                'technician_required',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
