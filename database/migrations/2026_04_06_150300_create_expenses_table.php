<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('designation');
            $table->string('expense_category')->nullable();
            $table->date('expense_date');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('dh - MAD');
            $table->string('reference')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->onDelete('set null');
            $table->string('payment_method')->nullable();
            $table->string('account')->nullable();
            $table->string('tax_type')->default('NO TAXE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
