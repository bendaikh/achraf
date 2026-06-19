<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jumia_integrations', function (Blueprint $table) {
            $table->string('client_id')->nullable()->after('integration_name');
            $table->text('refresh_token')->nullable()->after('client_id');
            $table->text('access_token')->nullable()->after('refresh_token');
            $table->timestamp('access_token_expires_at')->nullable()->after('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('jumia_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'client_id',
                'refresh_token',
                'access_token',
                'access_token_expires_at',
            ]);
        });
    }
};
