<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
            $table->index(['product_id', 'product_variant_id', 'warehouse_id'], 'product_stocks_variant_warehouse_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
            $table->index(['product_variant_id', 'moved_at'], 'stock_movements_variant_moved_idx');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
        });

        if (Schema::hasTable('purchase_stock_allocations')) {
            Schema::table('purchase_stock_allocations', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_stock_allocations', 'product_variant_id')) {
                    $table->foreignId('product_variant_id')
                        ->nullable()
                        ->after('product_id')
                        ->constrained('product_variants')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('reception_stock_allocations')) {
            Schema::table('reception_stock_allocations', function (Blueprint $table) {
                if (! Schema::hasColumn('reception_stock_allocations', 'product_variant_id')) {
                    $table->foreignId('product_variant_id')
                        ->nullable()
                        ->after('product_id')
                        ->constrained('product_variants')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropIndex('product_stocks_variant_warehouse_idx');
            $table->dropConstrainedForeignId('product_variant_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_variant_moved_idx');
            $table->dropConstrainedForeignId('product_variant_id');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
        });

        if (Schema::hasTable('purchase_stock_allocations') && Schema::hasColumn('purchase_stock_allocations', 'product_variant_id')) {
            Schema::table('purchase_stock_allocations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_variant_id');
            });
        }

        if (Schema::hasTable('reception_stock_allocations') && Schema::hasColumn('reception_stock_allocations', 'product_variant_id')) {
            Schema::table('reception_stock_allocations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_variant_id');
            });
        }
    }
};
