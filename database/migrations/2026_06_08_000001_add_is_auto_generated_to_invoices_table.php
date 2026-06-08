<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_auto_generated')->default(false)->after('pos_sale_id');
        });

        DB::table('invoices')
            ->where('remarks', 'like', 'Générée automatiquement%')
            ->update(['is_auto_generated' => true]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_auto_generated');
        });
    }
};
