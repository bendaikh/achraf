<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_imports', 'progress')) {
                $table->unsignedTinyInteger('progress')->default(0)->after('duplicate_count');
            }
            if (! Schema::hasColumn('payment_imports', 'processed_rows')) {
                $table->unsignedInteger('processed_rows')->default(0)->after('progress');
            }
            if (! Schema::hasColumn('payment_imports', 'error_message')) {
                $table->text('error_message')->nullable()->after('processed_rows');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_imports', function (Blueprint $table) {
            foreach (['error_message', 'processed_rows', 'progress'] as $column) {
                if (Schema::hasColumn('payment_imports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
