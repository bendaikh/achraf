<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_match_memories')) {
            return;
        }

        Schema::create('payment_match_memories', function (Blueprint $table) {
            $table->id();
            $table->string('lookup_type', 40)->index();
            $table->string('lookup_value', 191);
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('hit_count')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['lookup_type', 'lookup_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_match_memories');
    }
};
