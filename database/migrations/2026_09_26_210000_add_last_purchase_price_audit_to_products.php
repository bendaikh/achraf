<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('last_purchase_price_updated_at')->nullable()->after('last_purchase_price');
            $table->foreignId('last_purchase_price_updated_by')
                ->nullable()
                ->after('last_purchase_price_updated_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_purchase_price_updated_by');
            $table->dropColumn('last_purchase_price_updated_at');
        });
    }
};
