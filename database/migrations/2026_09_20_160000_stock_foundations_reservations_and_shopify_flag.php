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
            if (! Schema::hasColumn('warehouses', 'available_for_shopify')) {
                $table->boolean('available_for_shopify')->default(false)->after('is_fulfillment_default');
            }
        });

        // Physical fulfillment warehouses feed Shopify; the online mirror depot does not.
        if (Schema::hasColumn('warehouses', 'available_for_shopify')) {
            DB::table('warehouses')
                ->where(function ($q) {
                    $q->where('kind', 'physical')->orWhereNull('kind');
                })
                ->where('is_fulfillment_default', true)
                ->update(['available_for_shopify' => true]);

            DB::table('warehouses')
                ->where('kind', 'online')
                ->update(['available_for_shopify' => false]);
        }

        if (! Schema::hasTable('stock_reservations')) {
            Schema::create('stock_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
                $table->unsignedInteger('quantity');
                $table->string('status', 32)->default('active'); // active | released | consumed
                $table->string('source_type', 64)->nullable(); // order, order_item, picking, …
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('source_line_type', 64)->nullable();
                $table->unsignedBigInteger('source_line_id')->nullable();
                $table->string('document_reference')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reserved_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'status']);
                $table->index(['source_type', 'source_id']);
                $table->index(['warehouse_id', 'warehouse_location_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');

        Schema::table('warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouses', 'available_for_shopify')) {
                $table->dropColumn('available_for_shopify');
            }
        });
    }
};
