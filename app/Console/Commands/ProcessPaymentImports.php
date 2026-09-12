<?php

namespace App\Console\Commands;

use App\Models\PaymentImport;
use App\Services\PaymentImportService;
use Illuminate\Console\Command;
use Throwable;

class ProcessPaymentImports extends Command
{
    protected $signature = 'payments:process-imports {--max=1 : Maximum imports to process per run}';

    protected $description = 'Process pending payment import files in the background';

    public function __construct(
        protected PaymentImportService $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $max = max(1, (int) $this->option('max'));

        // Reclaim imports stuck in "processing" (e.g. afterResponse killed mid-run).
        PaymentImport::query()
            ->where('status', PaymentImport::STATUS_PROCESSING)
            ->where('updated_at', '<', now()->subMinutes(20))
            ->update([
                'status' => PaymentImport::STATUS_PENDING,
                'progress' => 0,
                'error_message' => 'Reprise automatique après interruption du traitement.',
            ]);

        $imports = PaymentImport::query()
            ->where('status', PaymentImport::STATUS_PENDING)
            ->orderBy('id')
            ->limit($max)
            ->get();

        foreach ($imports as $import) {
            try {
                $this->importService->processQueuedImport($import);
                $this->info("Payment import #{$import->id} processed ({$import->fresh()->status}).");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Payment import #{$import->id} failed: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
