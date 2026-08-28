<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouses', 'kind')) {
                $table->string('kind')->default('physical')->after('code'); // physical | online
            }
            if (! Schema::hasColumn('warehouses', 'is_fulfillment_default')) {
                $table->boolean('is_fulfillment_default')->default(false)->after('is_primary');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'quantity_before')) {
                $table->integer('quantity_before')->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('stock_movements', 'quantity_after')) {
                $table->integer('quantity_after')->nullable()->after('quantity_before');
            }
            if (! Schema::hasColumn('stock_movements', 'reason')) {
                $table->string('reason')->nullable()->after('notes');
            }
        });

        Schema::table('receptions', function (Blueprint $table) {
            if (! Schema::hasColumn('receptions', 'supplier_purchase_order_id')) {
                $table->foreignId('supplier_purchase_order_id')->nullable()->after('supplier_id')
                    ->constrained('supplier_purchase_orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('receptions', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('stock_location')
                    ->constrained('warehouses')->nullOnDelete();
            }
            if (! Schema::hasColumn('receptions', 'stock_applied_at')) {
                $table->timestamp('stock_applied_at')->nullable()->after('converted_at');
            }
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_invoices', 'stock_applied_at')) {
                $table->timestamp('stock_applied_at')->nullable()->after('total');
            }
            if (! Schema::hasColumn('supplier_invoices', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('stock_location')
                    ->constrained('warehouses')->nullOnDelete();
            }
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sales', 'physical_stock_processed_at')) {
                $table->timestamp('physical_stock_processed_at')->nullable()->after('fulfillment_status');
            }
        });

        if (! Schema::hasTable('reception_stock_allocations')) {
            Schema::create('reception_stock_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reception_id')->constrained('receptions')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_replenishment_needs')) {
            Schema::create('stock_replenishment_needs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->foreignId('pos_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();
                $table->unsignedInteger('quantity_needed');
                $table->unsignedInteger('quantity_ordered')->default(0);
                $table->foreignId('suggested_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->foreignId('supplier_purchase_order_id')->nullable()->constrained('supplier_purchase_orders')->nullOnDelete();
                $table->string('status')->default('open'); // open | ordered | cancelled
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'product_id']);
            });
        }

        $now = now();

        DB::table('warehouses')->whereNull('kind')->orWhere('kind', '')->update(['kind' => 'physical']);

        $shopifyId = DB::table('warehouses')->where('code', 'SHOPIFY')->value('id');
        if (! $shopifyId) {
            $shopifyId = DB::table('warehouses')->insertGetId([
                'name' => 'Stock Shopify / En ligne',
                'code' => 'SHOPIFY',
                'kind' => 'online',
                'address' => null,
                'city' => null,
                'status' => 'active',
                'is_primary' => false,
                'is_fulfillment_default' => false,
                'comment' => 'Stock marketplace / en ligne — distinct du stock physique magasin',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('warehouses')->where('id', $shopifyId)->update([
                'kind' => 'online',
                'updated_at' => $now,
            ]);
        }

        $belvedereId = DB::table('warehouses')->where('code', 'BELVEDERE')->value('id');
        if (! $belvedereId) {
            $existingBelvedere = DB::table('warehouses')
                ->whereRaw('LOWER(name) LIKE ?', ['%belv%'])
                ->value('id');
            if ($existingBelvedere) {
                $belvedereId = $existingBelvedere;
                DB::table('warehouses')->where('id', $belvedereId)->update([
                    'kind' => 'physical',
                    'is_fulfillment_default' => true,
                    'updated_at' => $now,
                ]);
            } else {
                $belvedereId = DB::table('warehouses')->insertGetId([
                    'name' => 'Magasin Belvédère',
                    'code' => 'BELVEDERE',
                    'kind' => 'physical',
                    'address' => null,
                    'city' => 'Casablanca',
                    'status' => 'active',
                    'is_primary' => false,
                    'is_fulfillment_default' => true,
                    'comment' => 'Emplacement de préparation des commandes par défaut',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } else {
            DB::table('warehouses')->where('id', $belvedereId)->update([
                'kind' => 'physical',
                'is_fulfillment_default' => true,
                'updated_at' => $now,
            ]);
        }

        DB::table('warehouses')
            ->where('id', '!=', $belvedereId)
            ->update(['is_fulfillment_default' => false]);

        $shopifyProductIds = DB::table('products')
            ->where('source', 'shopify')
            ->where(function ($q) {
                $q->whereNull('item_kind')->orWhere('item_kind', 'stocked');
            })
            ->pluck('id');

        foreach ($shopifyProductIds as $productId) {
            $slots = DB::table('product_stocks')->where('product_id', $productId)->get();
            if ($slots->isEmpty()) {
                $qty = (int) DB::table('products')->where('id', $productId)->value('stock_enligne');
                if ($qty !== 0) {
                    DB::table('product_stocks')->insert([
                        'product_id' => $productId,
                        'warehouse_id' => $shopifyId,
                        'warehouse_location_id' => null,
                        'quantity' => $qty,
                        'reserved' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                continue;
            }

            foreach ($slots as $slot) {
                if ((int) $slot->warehouse_id === (int) $shopifyId) {
                    continue;
                }

                $exists = DB::table('product_stocks')
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $shopifyId)
                    ->whereNull('warehouse_location_id')
                    ->exists();

                if (! $exists && (int) $slot->quantity !== 0) {
                    DB::table('product_stocks')->where('id', $slot->id)->update([
                        'warehouse_id' => $shopifyId,
                        'warehouse_location_id' => null,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_replenishment_needs');
        Schema::dropIfExists('reception_stock_allocations');

        Schema::table('pos_sales', function (Blueprint $table) {
            if (Schema::hasColumn('pos_sales', 'physical_stock_processed_at')) {
                $table->dropColumn('physical_stock_processed_at');
            }
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_invoices', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
            if (Schema::hasColumn('supplier_invoices', 'stock_applied_at')) {
                $table->dropColumn('stock_applied_at');
            }
        });

        Schema::table('receptions', function (Blueprint $table) {
            if (Schema::hasColumn('receptions', 'stock_applied_at')) {
                $table->dropColumn('stock_applied_at');
            }
            if (Schema::hasColumn('receptions', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
            if (Schema::hasColumn('receptions', 'supplier_purchase_order_id')) {
                $table->dropConstrainedForeignId('supplier_purchase_order_id');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            foreach (['quantity_before', 'quantity_after', 'reason'] as $col) {
                if (Schema::hasColumn('stock_movements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouses', 'is_fulfillment_default')) {
                $table->dropColumn('is_fulfillment_default');
            }
            if (Schema::hasColumn('warehouses', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};
