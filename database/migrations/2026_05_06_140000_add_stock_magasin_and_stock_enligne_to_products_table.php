<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if columns already exist (in case of partial migration)
        if (!Schema::hasColumn('products', 'stock_magasin')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('stock_magasin')->default(0)->after('stock_quantity');
                $table->integer('stock_enligne')->default(0)->after('stock_magasin');
            });
        }

        // Initialize stock_magasin from stock_quantity for non-Shopify products
        // Use GREATEST to handle negative values, and cap at a reasonable maximum
        DB::statement("
            UPDATE products 
            SET stock_magasin = CASE 
                WHEN stock_quantity < 0 THEN 0 
                WHEN stock_quantity > 2147483647 THEN 2147483647 
                ELSE stock_quantity 
            END
            WHERE source IS NULL OR source != 'shopify'
        ");

        // Initialize stock_enligne from stock_quantity for Shopify products
        DB::statement("
            UPDATE products 
            SET stock_enligne = CASE 
                WHEN stock_quantity < 0 THEN 0 
                WHEN stock_quantity > 2147483647 THEN 2147483647 
                ELSE stock_quantity 
            END
            WHERE source = 'shopify'
        ");
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_magasin', 'stock_enligne']);
        });
    }
};
