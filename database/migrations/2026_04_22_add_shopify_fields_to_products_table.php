<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0)->change();
            
            // Shopify integration fields
            $table->string('source')->nullable()->after('status'); // 'shopify', 'manual', null
            $table->string('external_id')->nullable()->after('source'); // Shopify product ID
            $table->string('shopify_status')->nullable()->after('external_id'); // active, draft, archived
            $table->timestamp('shopify_synced_at')->nullable()->after('shopify_status');
            
            // Add index for better query performance
            $table->index(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['source', 'external_id']);
            $table->dropColumn(['source', 'external_id', 'shopify_status', 'shopify_synced_at']);
        });
    }
};
