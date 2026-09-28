<?php

use App\Services\ServiceStockCleanupService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouses') && ! Schema::hasColumn('warehouses', 'archived_at')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('status');
            });
        }

        // Neutralise les quantités fantômes des services / articles non stockables.
        if (Schema::hasTable('product_stocks') && Schema::hasTable('products')) {
            app(ServiceStockCleanupService::class)->neutralizeNonStockableSlots(null);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('warehouses') && Schema::hasColumn('warehouses', 'archived_at')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
