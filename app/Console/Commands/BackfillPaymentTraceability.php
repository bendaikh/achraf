<?php

namespace App\Console\Commands;

use App\Services\PaymentTraceabilityService;
use Illuminate\Console\Command;

class BackfillPaymentTraceability extends Command
{
    protected $signature = 'payments:backfill-traceability {--limit= : Optional max number of payments to scan}';

    protected $description = 'Recover delivery fees, net received, tracking and references from import rows onto invoice payments';

    public function handle(PaymentTraceabilityService $service): int
    {
        $limit = $this->option('limit');
        $this->info('Backfilling payment traceability details...');

        $result = $service->backfillMissingDetails(
            $limit !== null && $limit !== '' ? (int) $limit : null
        );

        $this->info("Scanned {$result['scanned']} payment(s).");
        $this->info("Updated {$result['updated']} payment(s).");
        if (isset($result['batches'])) {
            $this->info("Grouped {$result['batches']} import batch(es).");
        }
        if (isset($result['import_lines'])) {
            $this->info("Updated {$result['import_lines']} import line(s) with fees/net.");
        }

        return self::SUCCESS;
    }
}
