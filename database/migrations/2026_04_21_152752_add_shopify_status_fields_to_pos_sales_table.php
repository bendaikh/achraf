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
        Schema::table('pos_sales', function (Blueprint $table) {
            // Shopify payment status
            $table->string('payment_status')->nullable()->after('status');
            
            // Shopify fulfillment status
            $table->string('fulfillment_status')->nullable()->after('payment_status');
            
            // Last sync timestamp for tracking updates
            $table->timestamp('shopify_synced_at')->nullable()->after('fulfillment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'fulfillment_status', 'shopify_synced_at']);
        });
    }
};
