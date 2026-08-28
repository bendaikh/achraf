<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('commercial_status', 40)->default('normal')->after('payment_status')->index();
            $table->string('source', 30)->nullable()->after('commercial_status')->index();
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreignId('pos_sale_id')->nullable()->after('invoice_id')->constrained('pos_sales')->nullOnDelete();
            $table->string('source', 30)->nullable()->after('pos_sale_id');
            $table->string('external_id')->nullable()->after('source');
            $table->string('return_type', 30)->nullable()->after('external_id');
            $table->boolean('physical_return')->default(false)->after('return_type');
            $table->boolean('restock')->default(false)->after('physical_return');
            $table->string('product_condition', 30)->nullable()->after('restock');
            $table->string('return_location')->nullable()->after('product_condition');
            $table->foreignId('created_by')->nullable()->after('return_location')->constrained('users')->nullOnDelete();

            $table->unique(['source', 'external_id'], 'credit_notes_source_external_unique');
        });

        Schema::create('client_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_note_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();
            $table->string('source', 30)->nullable();
            $table->date('refund_date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50);
            $table->string('payment_reference')->nullable();
            $table->string('payment_file_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'refund_date']);
            $table->unique(['source', 'external_id'], 'client_refunds_source_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_refunds');

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropUnique('credit_notes_source_external_unique');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('pos_sale_id');
            $table->dropColumn([
                'source',
                'external_id',
                'return_type',
                'physical_return',
                'restock',
                'product_condition',
                'return_location',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['commercial_status', 'source']);
        });
    }
};
