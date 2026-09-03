<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->foreignId('converted_supplier_delivery_note_id')
                ->nullable()
                ->after('converted_at')
                ->constrained('supplier_delivery_notes')
                ->nullOnDelete();
            $table->timestamp('converted_to_delivery_note_at')
                ->nullable()
                ->after('converted_supplier_delivery_note_id');
        });
    }

    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_supplier_delivery_note_id');
            $table->dropColumn('converted_to_delivery_note_at');
        });
    }
};
