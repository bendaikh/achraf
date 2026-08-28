<?php

use App\Models\SupplierInvoicePayment;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('unallocated_amount', 12, 2)->default(0);
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('cheque_bank')->nullable();
            $table->date('cheque_date')->nullable();
            $table->date('cheque_due_date')->nullable();
            $table->string('cheque_beneficiary')->nullable();
            $table->string('cheque_status', 32)->nullable();
            $table->string('payment_file_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->nullable();
            $table->string('tracking_number')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('payment_import_id')->nullable();
            $table->unsignedBigInteger('payment_import_row_id')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_payment_id')->nullable()->constrained('supplier_payments')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->boolean('is_cash')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier_credit_note_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::table('supplier_invoice_payments', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->after('supplier_id')->constrained('supplier_payments')->nullOnDelete();
            $table->boolean('is_cash_movement')->default(true)->after('allow_overpayment');
        });

        SupplierInvoicePayment::query()
            ->with('supplierInvoice')
            ->whereNull('supplier_payment_id')
            ->orderBy('id')
            ->each(function (SupplierInvoicePayment $line) {
                $supplierId = $line->supplier_id ?: $line->supplierInvoice?->supplier_id;
                if (! $supplierId) {
                    return;
                }

                $header = SupplierPayment::query()->create([
                    'supplier_id' => $supplierId,
                    'payment_date' => $line->payment_date,
                    'amount' => $line->amount,
                    'unallocated_amount' => 0,
                    'payment_method' => $line->payment_method,
                    'payment_reference' => $line->payment_reference,
                    'cheque_number' => $line->cheque_number,
                    'cheque_bank' => $line->cheque_bank,
                    'cheque_date' => $line->cheque_date,
                    'cheque_due_date' => $line->cheque_due_date,
                    'cheque_beneficiary' => $line->cheque_beneficiary,
                    'cheque_status' => $line->cheque_status,
                    'payment_file_path' => $line->payment_file_path,
                    'notes' => $line->notes,
                    'source' => $line->source,
                    'tracking_number' => $line->tracking_number,
                    'user_id' => $line->user_id,
                    'payment_import_id' => $line->payment_import_id,
                    'payment_import_row_id' => $line->payment_import_row_id,
                    'dedupe_key' => $line->dedupe_key,
                ]);

                SupplierPaymentAllocation::query()->create([
                    'supplier_payment_id' => $header->id,
                    'supplier_invoice_id' => $line->supplier_invoice_id,
                    'amount' => $line->amount,
                    'is_cash' => true,
                ]);

                $line->forceFill([
                    'supplier_id' => $supplierId,
                    'supplier_payment_id' => $header->id,
                    'is_cash_movement' => true,
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('supplier_invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_payment_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('is_cash_movement');
        });

        Schema::dropIfExists('supplier_credit_note_allocations');
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
    }
};
