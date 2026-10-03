<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien BL → commande source (pos_sales) pour que la validation d'un BL issu d'une
 * commande consomme les réservations du picking au lieu de créer une 2e sortie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_notes', 'pos_sale_id')) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->foreignId('pos_sale_id')
                    ->nullable()
                    ->after('client_id')
                    ->constrained('pos_sales')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_notes', 'pos_sale_id')) {
            Schema::table('delivery_notes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pos_sale_id');
            });
        }
    }
};
