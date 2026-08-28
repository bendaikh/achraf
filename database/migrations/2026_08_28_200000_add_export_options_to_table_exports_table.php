<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_exports', function (Blueprint $table) {
            $table->json('export_options')->nullable()->after('ids');
        });
    }

    public function down(): void
    {
        Schema::table('table_exports', function (Blueprint $table) {
            $table->dropColumn('export_options');
        });
    }
};
