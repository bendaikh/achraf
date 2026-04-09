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
        Schema::table('shopify_integrations', function (Blueprint $table) {
            $table->text('api_access_token')->nullable()->after('webhook_secret');
            $table->string('api_version')->default('2024-01')->after('api_access_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_integrations', function (Blueprint $table) {
            $table->dropColumn(['api_access_token', 'api_version']);
        });
    }
};
