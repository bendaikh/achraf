<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_movements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('movement_date');
            $table->string('origin', 40);
            $table->string('type', 20);
            $table->string('label');
            $table->string('account', 20)->default('other');
            $table->decimal('amount_in', 15, 2)->default(0);
            $table->decimal('amount_out', 15, 2)->default(0);
            $table->string('status', 20)->default('valide');
            $table->boolean('is_manual')->default(false);
            $table->nullableMorphs('source');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('justificatif_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('pointed_at')->nullable();
            $table->foreignId('pointed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('day_closed_at')->nullable();
            $table->timestamps();

            $table->index(['movement_date', 'type']);
            $table->index(['account', 'status']);
            $table->index('origin');
        });

        Schema::create('financial_declarations', function (Blueprint $table) {
            $table->id();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 30)->default('ouverte');
            $table->decimal('vat_collected', 15, 2)->nullable();
            $table->decimal('vat_deductible', 15, 2)->nullable();
            $table->decimal('vat_net', 15, 2)->nullable();
            $table->decimal('revenue', 15, 2)->nullable();
            $table->json('anomalies')->nullable();
            $table->json('control_report')->nullable();
            $table->timestamp('controlled_at')->nullable();
            $table->foreignId('controlled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period_from', 'period_to']);
        });

        Schema::create('financial_pieces', function (Blueprint $table) {
            $table->id();
            $table->date('piece_date');
            $table->string('label');
            $table->string('category', 40)->default('tva');
            $table->string('file_path');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_declaration_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_pieces');
        Schema::dropIfExists('financial_declarations');
        Schema::dropIfExists('financial_movements');
    }
};
