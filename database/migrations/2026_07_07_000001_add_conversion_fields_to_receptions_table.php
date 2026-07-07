<?php

use App\Models\Reception;
use App\Models\SupplierInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->foreignId('converted_supplier_invoice_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('supplier_invoices')
                ->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_supplier_invoice_id');
        });

        SupplierInvoice::query()
            ->where('remarks', 'like', 'Générée depuis Bon(s) de Réception:%')
            ->whereNotNull('reference_invoice')
            ->each(function (SupplierInvoice $invoice) {
                $receptionNumbers = collect(explode(',', $invoice->reference_invoice))
                    ->map(fn (string $number) => trim($number))
                    ->filter();

                if ($receptionNumbers->isEmpty()) {
                    return;
                }

                Reception::query()
                    ->whereIn('reception_number', $receptionNumbers)
                    ->whereNull('converted_supplier_invoice_id')
                    ->update([
                        'converted_supplier_invoice_id' => $invoice->id,
                        'converted_at' => $invoice->created_at,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_supplier_invoice_id');
            $table->dropColumn('converted_at');
        });
    }
};
