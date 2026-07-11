<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->date('delivery_date');
            $table->date('expected_reception_date')->nullable();
            $table->string('reference')->nullable();
            $table->string('currency')->default('dh - MAD');
            $table->string('status')->default('brouillon');
            $table->string('stock_location')->default('DEPOT');
            $table->string('model')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('adjustment', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('document_file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_delivery_notes');
    }
};
