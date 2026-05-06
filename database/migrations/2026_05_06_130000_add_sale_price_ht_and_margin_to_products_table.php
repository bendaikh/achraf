<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_price_ht', 10, 2)->nullable()->after('sale_price');
            $table->decimal('product_margin', 10, 2)->nullable()->after('sale_price_ht');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_price_ht', 'product_margin']);
        });
    }
};
