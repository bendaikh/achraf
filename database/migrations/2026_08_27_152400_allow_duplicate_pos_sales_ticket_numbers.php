<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shopify (and other channels) can reuse order names / n° de commande
     * after a sequence reset. Identity must stay on Shopify/external IDs,
     * not on the display ticket_number.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pos_sales')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // Fresh sqlite installs already get a non-unique index from create_pos_sales_table.
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM pos_sales WHERE Column_name = ?', ['ticket_number']));

        if ($indexes->contains(fn ($row) => $row->Key_name === 'pos_sales_ticket_number_unique')) {
            Schema::table('pos_sales', function (Blueprint $table) {
                $table->dropUnique('pos_sales_ticket_number_unique');
            });
        }

        $indexes = collect(DB::select('SHOW INDEX FROM pos_sales WHERE Column_name = ?', ['ticket_number']));

        if (! $indexes->contains(fn ($row) => $row->Key_name === 'pos_sales_ticket_number_index')) {
            Schema::table('pos_sales', function (Blueprint $table) {
                $table->index('ticket_number', 'pos_sales_ticket_number_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_sales')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM pos_sales WHERE Column_name = ?', ['ticket_number']));

        if ($indexes->contains(fn ($row) => $row->Key_name === 'pos_sales_ticket_number_index')) {
            Schema::table('pos_sales', function (Blueprint $table) {
                $table->dropIndex('pos_sales_ticket_number_index');
            });
        }

        $indexes = collect(DB::select('SHOW INDEX FROM pos_sales WHERE Column_name = ?', ['ticket_number']));

        if (! $indexes->contains(fn ($row) => $row->Key_name === 'pos_sales_ticket_number_unique')) {
            Schema::table('pos_sales', function (Blueprint $table) {
                $table->unique('ticket_number', 'pos_sales_ticket_number_unique');
            });
        }
    }
};
