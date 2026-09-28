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
            if (! Schema::hasColumn('warehouses', 'is_sellable')) {
                $table->boolean('is_sellable')->default(true)->after('available_for_shopify');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'stock_sellable')) {
                $table->integer('stock_sellable')->default(0)->after('stock_magasin');
            }
        });

        // Existing physical warehouses remain sellable; online never sellable for ERP picking.
        if (Schema::hasColumn('warehouses', 'is_sellable')) {
            DB::table('warehouses')->where('kind', 'online')->update(['is_sellable' => false]);
            DB::table('warehouses')->where(function ($q) {
                $q->where('kind', 'physical')->orWhereNull('kind');
            })->where('is_sellable', '!=', false)->update(['is_sellable' => true]);
        }

        // Seed SAV / quarantine warehouse (physically traceable, not available for sale).
        $savId = DB::table('warehouses')->where('code', 'SAV')->value('id');
        if (! $savId) {
            $savId = DB::table('warehouses')->insertGetId([
                'name' => 'SAV / Quarantaine',
                'code' => 'SAV',
                'kind' => 'physical',
                'status' => 'active',
                'is_primary' => false,
                'is_fulfillment_default' => false,
                'available_for_shopify' => false,
                'is_sellable' => false,
                'comment' => 'Retours endommagés / à contrôler — traçables mais non disponibles à la vente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('warehouses')->where('id', $savId)->update([
                'is_sellable' => false,
                'available_for_shopify' => false,
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('customer_returns')) {
            Schema::create('customer_returns', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->string('status', 32)->default('draft'); // draft | validated | cancelled
                $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->foreignId('credit_note_id')->nullable()->constrained('credit_notes')->nullOnDelete();
                $table->string('source')->nullable();
                $table->string('search_key')->nullable();
                $table->string('tracking_number')->nullable();
                $table->boolean('create_credit_note')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'pos_sale_id']);
                $table->index('tracking_number');
            });
        }

        if (! Schema::hasTable('customer_return_lines')) {
            Schema::create('customer_return_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_return_id')->constrained('customer_returns')->cascadeOnDelete();
                $table->foreignId('pos_sale_item_id')->nullable()->constrained('pos_sale_items')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->string('sku')->nullable();
                $table->string('designation')->nullable();
                $table->unsignedInteger('quantity_ordered')->default(0);
                $table->unsignedInteger('quantity_expected')->default(0);
                $table->unsignedInteger('quantity_received')->default(0);
                $table->string('condition', 32)->default('vendable'); // vendable|damaged|to_check|missing
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
                $table->boolean('stock_applied')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['customer_return_id', 'condition']);
            });
        }

        // Backfill stock_sellable ≈ stock_magasin for existing rows.
        if (Schema::hasColumn('products', 'stock_sellable')) {
            DB::table('products')->update([
                'stock_sellable' => DB::raw('COALESCE(stock_magasin, 0)'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_return_lines');
        Schema::dropIfExists('customer_returns');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'stock_sellable')) {
                $table->dropColumn('stock_sellable');
            }
        });

        Schema::table('warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouses', 'is_sellable')) {
                $table->dropColumn('is_sellable');
            }
        });
    }
};
