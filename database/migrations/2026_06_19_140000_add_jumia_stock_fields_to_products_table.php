<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('jumia_product_sid')->nullable()->after('shopify_synced_at');
            $table->timestamp('jumia_stock_synced_at')->nullable()->after('jumia_product_sid');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['jumia_product_sid', 'jumia_stock_synced_at']);
        });
    }
};
