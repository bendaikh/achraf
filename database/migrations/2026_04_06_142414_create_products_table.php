<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ref')->unique();
            $table->string('image')->nullable();
            $table->decimal('cost_price_ht', 10, 2)->nullable();
            $table->decimal('cost_price_ttc', 10, 2)->nullable();
            $table->decimal('last_purchase_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->integer('minimum_safety_stock')->nullable();
            $table->integer('minimum_alert_stock')->nullable();
            $table->string('barcode')->nullable();
            $table->string('vat_category')->nullable();
            $table->string('element_type')->nullable();
            $table->string('tag')->nullable();
            $table->string('status')->default('Activer');
            $table->string('product_category')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
