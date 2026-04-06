<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->string('source', 32)->nullable()->after('notes');
            $table->string('external_id', 64)->nullable()->after('source');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn(['source', 'external_id']);
        });
    }
};
