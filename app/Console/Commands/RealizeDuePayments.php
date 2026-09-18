<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\SupplierInvoicePayment;
use App\Services\FinancialMovementService;
use Illuminate\Console\Command;

class RealizeDuePayments extends Command
{
    protected $signature = 'payments:realize-due {--days=7 : Fenêtre de rattrapage en jours}';

    protected $description = 'Matérialise en trésorerie et met à jour les statuts des paiements programmés dont la date est atteinte';

    public function handle(FinancialMovementService $movements): int
    {
        $days = max(1, (int) $this->option('days'));
        $from = now()->subDays($days)->toDateString();
        $today = now()->toDateString();

        $clientCount = 0;
        InvoicePayment::query()
            ->with('invoice')
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $today)
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($movements, &$clientCount) {
                foreach ($payments as $payment) {
                    $movements->syncFromInvoicePayment($payment);
                    if ($payment->invoice) {
                        $payment->invoice->syncPaymentStatus();
                    }
                    $clientCount++;
                }
            });

        $supplierCount = 0;
        SupplierInvoicePayment::query()
            ->with('supplierInvoice')
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $today)
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($movements, &$supplierCount) {
                foreach ($payments as $payment) {
                    $movements->syncFromSupplierPayment($payment);
                    $supplierCount++;
                }
            });

        $this->info("Paiements clients traités : {$clientCount}");
        $this->info("Paiements fournisseurs traités : {$supplierCount}");

        return self::SUCCESS;
    }
}
