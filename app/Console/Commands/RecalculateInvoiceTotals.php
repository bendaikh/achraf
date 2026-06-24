<?php

namespace App\Console\Commands;

use App\Services\CommercialDocumentTotalsService;
use Illuminate\Console\Command;

class RecalculateInvoiceTotals extends Command
{
    protected $signature = 'invoices:recalculate-totals';

    protected $description = 'Recalculate line totals and document totals for existing invoices (TTC/HT fix, including auto-generated from orders)';

    public function handle(CommercialDocumentTotalsService $service): int
    {
        $this->info('Recalculating invoice totals...');

        $result = $service->recalculateAllInvoices();

        $this->info("Processed {$result['processed']} invoice(s).");
        $this->info("Updated {$result['updated']} invoice(s).");

        return self::SUCCESS;
    }
}
