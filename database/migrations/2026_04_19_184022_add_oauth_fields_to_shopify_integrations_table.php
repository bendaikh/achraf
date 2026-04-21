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
            $table->string('shop_domain')->nullable()->after('shop_name');
            $table->text('oauth_access_token')->nullable()->after('api_access_token');
            $table->string('oauth_scope')->nullable()->after('oauth_access_token');
            $table->string('oauth_state')->nullable()->after('oauth_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_integrations', function (Blueprint $table) {
            $table->dropColumn(['shop_domain', 'oauth_access_token', 'oauth_scope', 'oauth_state']);
        });
    }
};
