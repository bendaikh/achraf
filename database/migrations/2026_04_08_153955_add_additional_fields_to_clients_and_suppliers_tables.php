<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('region')->nullable()->after('postal_code');
            $table->string('ice')->nullable()->after('tax_id');
            $table->string('fiscal_identifier')->nullable()->after('ice');
            $table->decimal('latitude', 10, 8)->nullable()->after('fiscal_identifier');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('ville')->nullable()->after('longitude');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('region')->nullable()->after('postal_code');
            $table->string('ice')->nullable()->after('tax_id');
            $table->string('fiscal_identifier')->nullable()->after('ice');
            $table->decimal('latitude', 10, 8)->nullable()->after('fiscal_identifier');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('ville')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['code', 'postal_code', 'region', 'ice', 'fiscal_identifier', 'latitude', 'longitude', 'ville']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['code', 'postal_code', 'region', 'ice', 'fiscal_identifier', 'latitude', 'longitude', 'ville']);
        });
    }
};
