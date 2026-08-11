<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'inventory_item_id')) {
                $table->string('inventory_item_id')->nullable()->after('shopify_variant_id');
                $table->index('inventory_item_id');
            }
        });

        Schema::table('shopify_integrations', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_integrations', 'primary_location_id')) {
                $table->string('primary_location_id')->nullable()->after('api_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'inventory_item_id')) {
                $table->dropIndex(['inventory_item_id']);
                $table->dropColumn('inventory_item_id');
            }
        });

        Schema::table('shopify_integrations', function (Blueprint $table) {
            if (Schema::hasColumn('shopify_integrations', 'primary_location_id')) {
                $table->dropColumn('primary_location_id');
            }
        });
    }
};
