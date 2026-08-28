<?php

use App\Models\SupplierPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->string('payment_number')->nullable()->unique()->after('id');
            $table->string('status', 32)->default('validated')->after('dedupe_key');
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->json('allocation_snapshot')->nullable()->after('cancellation_reason');
        });

        Schema::create('supplier_payment_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        $yearCounts = [];
        SupplierPayment::query()->orderBy('id')->each(function (SupplierPayment $payment) use (&$yearCounts) {
            $year = $payment->payment_date?->format('Y') ?: date('Y');
            $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
            $payment->forceFill([
                'payment_number' => sprintf('REG-%s-%06d', $year, $yearCounts[$year]),
                'status' => $payment->status ?: 'validated',
            ])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_audits');

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'payment_number',
                'status',
                'cancelled_at',
                'cancellation_reason',
                'allocation_snapshot',
            ]);
        });
    }
};
