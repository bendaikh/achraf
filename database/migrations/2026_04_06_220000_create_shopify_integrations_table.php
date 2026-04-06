<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('integration_name')->default('Shopify Store');
            $table->string('shop_name')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_integrations');
    }
};
