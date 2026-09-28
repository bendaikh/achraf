<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->foreignId('collaborator_id')
                ->nullable()
                ->after('name')
                ->constrained('collaborators')
                ->nullOnDelete();
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->timestamp('archived_at')->nullable()->after('notes');

            $table->index(['collaborator_id', 'is_active']);
            $table->index(['is_default', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->dropIndex(['collaborator_id', 'is_active']);
            $table->dropIndex(['is_default', 'is_active']);
            $table->dropConstrainedForeignId('collaborator_id');
            $table->dropColumn(['is_default', 'archived_at']);
        });
    }
};
