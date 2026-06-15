<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->timestamp('jumia_synced_at')->nullable()->after('shopify_synced_at');
            $table->json('external_metadata')->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn(['jumia_synced_at', 'external_metadata']);
        });
    }
};
