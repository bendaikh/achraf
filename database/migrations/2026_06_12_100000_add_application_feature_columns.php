<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->string('receipt_file_path')->nullable()->after('total');
        });

        Schema::table('supplier_credit_notes', function (Blueprint $table) {
            $table->string('receipt_file_path')->nullable()->after('total');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('document_file_path')->nullable()->after('total');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('document_file_path')->nullable()->after('total');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('document_file_path')->nullable()->after('total');
        });

        Schema::table('receptions', function (Blueprint $table) {
            $table->string('document_file_path')->nullable()->after('remarks');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('discount_type')->default('fixed')->after('discount');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->string('discount_type')->default('fixed')->after('discount');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type_category')->nullable()->after('element_type');
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', fn (Blueprint $table) => $table->dropColumn('receipt_file_path'));
        Schema::table('supplier_credit_notes', fn (Blueprint $table) => $table->dropColumn('receipt_file_path'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('document_file_path'));
        Schema::table('quotes', fn (Blueprint $table) => $table->dropColumn('document_file_path'));
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->dropColumn('document_file_path'));
        Schema::table('receptions', fn (Blueprint $table) => $table->dropColumn('document_file_path'));
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn('discount_type'));
        Schema::table('purchase_items', fn (Blueprint $table) => $table->dropColumn('discount_type'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('product_type_category'));
    }
};
