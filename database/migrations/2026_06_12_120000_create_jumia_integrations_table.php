<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jumia_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('integration_name')->default('Jumia Store');
            $table->string('api_base_url')->nullable();
            $table->string('user_id')->nullable();
            $table->text('api_key')->nullable();
            $table->string('api_version', 10)->default('1.0');
            $table->boolean('enabled')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jumia_integrations');
    }
};
