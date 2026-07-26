<?php

namespace App\Console\Commands;

use App\Models\JumiaIntegration;
use App\Services\Jumia\JumiaApiClient;
use App\Services\Jumia\JumiaStockSyncLogEntry;
use App\Services\Jumia\JumiaStockSyncService;
use Illuminate\Console\Command;

class SyncJumiaStock extends Command
{
    protected $signature = 'jumia:sync-stock
                            {--sku= : Sync a single product by SKU (ref)}
                            {--all : Also discover/sync products not yet linked (slower, more API calls)}
                            {--batch-size=50 : Number of products to process per batch}
                            {--delay=300 : Delay in milliseconds between batches}
                            {--dry-run : Compare/plan updates without sending them to Jumia}
                            {--retries=5 : Maximum retry attempts for failed API calls}';

    protected $description = 'Synchronize product stock quantities from the application to Jumia';

    public function handle(): int
    {
        $integration = JumiaIntegration::query()->first();

        if (! $integration) {
            $this->error('No Jumia integration configured. Go to /integrations/jumia to set it up.');

            return self::FAILURE;
        }

        if (! $integration->enabled) {
            $this->warn('Jumia integration is disabled.');

            return self::FAILURE;
        }

        if (! $integration->isConfigured()) {
            $this->error('Jumia API credentials are incomplete.');

            return self::FAILURE;
        }

        $client = new JumiaApiClient($integration);

        try {
            if (! $client->testConnection()) {
                $integration->update(['last_error' => 'Connection test failed.']);
                $this->error('Failed to connect to Jumia API. Check your credentials and API URL.');

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $integration->update(['last_error' => $e->getMessage()]);
            $this->error('Connection test failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $linkedOnly = ! (bool) $this->option('all') && $this->option('sku') === null;

        $this->info('Starting Jumia stock synchronization...');
        if ($linkedOnly) {
            $this->line('Mode: linked Jumia products only (fast push by Seller SKU / variation id).');
        } elseif ($this->option('all')) {
            $this->warn('Mode: full catalog discovery — may take longer and hit API rate limits.');
        }
        if ($dryRun) {
            $this->warn('Dry run mode — no updates will be sent to Jumia.');
        }

        $service = JumiaStockSyncService::makeFromIntegration($integration);

        try {
            $result = $service->sync([
                'sku' => $this->option('sku'),
                'batch_size' => (int) $this->option('batch-size'),
                'delay_ms' => (int) $this->option('delay'),
                'dry_run' => $dryRun,
                'max_retries' => (int) $this->option('retries'),
                'linked_only' => $linkedOnly,
                'discover' => $this->option('sku') === null,
            ]);

            if ($result->entries !== []) {
                $this->newLine();
                $rows = array_map(static function (JumiaStockSyncLogEntry $entry): array {
                    return [
                        $entry->sku,
                        mb_strimwidth($entry->productName, 0, 40, '…'),
                        $entry->localStock,
                        $entry->jumiaStock ?? '—',
                        $entry->status,
                        $entry->message ?? '',
                    ];
                }, $result->entries);

                // Keep console output readable on large linked syncs.
                $this->table(
                    ['SKU', 'Product', 'Local Stock', 'Jumia Stock', 'Status', 'Message'],
                    array_slice($rows, 0, 50)
                );

                if (count($rows) > 50) {
                    $this->line('… '.(count($rows) - 50).' more row(s) omitted from table.');
                }
            } else {
                $this->warn('No products matched the sync criteria.');
            }

            $this->newLine();
            $this->info('Summary');
            $this->line("  Total products checked: {$result->totalChecked}");
            $this->line("  Total products updated: {$result->totalUpdated}");
            $this->line("  Total already synchronized: {$result->totalAlreadySynced}");
            $this->line("  Total products not found on Jumia: {$result->totalNotFound}");
            $this->line("  Total errors: {$result->totalErrors}");

            $integration->forceFill(['last_error' => $result->totalErrors > 0
                ? "Stock sync completed with {$result->totalErrors} error(s)."
                : null,
            ])->save();

            return $result->totalErrors > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $integration->update(['last_error' => $e->getMessage()]);
            $this->error('Stock sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
