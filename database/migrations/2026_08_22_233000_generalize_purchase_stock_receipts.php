<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_delivery_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_delivery_notes', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('stock_location')
                    ->constrained('warehouses')->nullOnDelete();
            }
            if (! Schema::hasColumn('supplier_delivery_notes', 'stock_applied_at')) {
                $table->timestamp('stock_applied_at')->nullable()->after('converted_at');
            }
            if (! Schema::hasColumn('supplier_delivery_notes', 'supplier_purchase_order_id')) {
                $table->foreignId('supplier_purchase_order_id')->nullable()->after('supplier_id')
                    ->constrained('supplier_purchase_orders')->nullOnDelete();
            }
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_invoices', 'supplier_purchase_order_id')) {
                $table->foreignId('supplier_purchase_order_id')->nullable()->after('supplier_id')
                    ->constrained('supplier_purchase_orders')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('purchase_stock_allocations')) {
            Schema::create('purchase_stock_allocations', function (Blueprint $table) {
                $table->id();
                $table->morphs('allocatable');
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_movement_documents')) {
            Schema::create('stock_movement_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_movement_id')->constrained('stock_movements')->cascadeOnDelete();
                $table->string('document_type');
                $table->unsignedBigInteger('document_id');
                $table->string('document_reference')->nullable();
                $table->timestamps();

                $table->unique(['stock_movement_id', 'document_type', 'document_id'], 'stock_movement_documents_unique');
                $table->index(['document_type', 'document_id']);
            });
        }

        if (Schema::hasTable('reception_stock_allocations') && Schema::hasTable('purchase_stock_allocations')) {
            $rows = DB::table('reception_stock_allocations')->get();
            foreach ($rows as $row) {
                DB::table('purchase_stock_allocations')->insert([
                    'allocatable_type' => 'App\\Models\\Reception',
                    'allocatable_id' => $row->reception_id,
                    'product_id' => $row->product_id,
                    'warehouse_id' => $row->warehouse_id,
                    'quantity' => $row->quantity,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        $movements = DB::table('stock_movements')
            ->where('type', 'purchase')
            ->whereNotNull('document_type')
            ->whereNotNull('document_id')
            ->get();
        foreach ($movements as $movement) {
            $exists = DB::table('stock_movement_documents')
                ->where('stock_movement_id', $movement->id)
                ->where('document_type', $movement->document_type)
                ->where('document_id', $movement->document_id)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('stock_movement_documents')->insert([
                'stock_movement_id' => $movement->id,
                'document_type' => $movement->document_type,
                'document_id' => $movement->document_id,
                'document_reference' => $movement->document_reference,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_documents');
        Schema::dropIfExists('purchase_stock_allocations');

        Schema::table('supplier_delivery_notes', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_delivery_notes', 'supplier_purchase_order_id')) {
                $table->dropConstrainedForeignId('supplier_purchase_order_id');
            }
            if (Schema::hasColumn('supplier_delivery_notes', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
            if (Schema::hasColumn('supplier_delivery_notes', 'stock_applied_at')) {
                $table->dropColumn('stock_applied_at');
            }
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_invoices', 'supplier_purchase_order_id')) {
                $table->dropConstrainedForeignId('supplier_purchase_order_id');
            }
        });
    }
};
