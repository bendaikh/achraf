<?php

namespace App\Console\Commands;

use App\Models\JumiaIntegration;
use App\Services\Jumia\JumiaApiClient;
use App\Services\Jumia\JumiaOrderImporter;
use App\Services\Jumia\JumiaStatusMapper;
use App\Services\OrderToInvoiceConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncJumiaOrders extends Command
{
    protected $signature = 'jumia:sync-orders
                            {--days= : Fetch orders from the last N days}
                            {--all : Fetch all orders (ignore last sync date)}';

    protected $description = 'Sync orders from Jumia Vendor API';

    public function handle(OrderToInvoiceConverter $orderToInvoiceConverter): int
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
        $importer = new JumiaOrderImporter($client, new JumiaStatusMapper, $orderToInvoiceConverter);

        try {
            if (! $client->testConnection()) {
                $integration->update(['last_error' => 'Connection test failed.']);
                $this->error('Failed to connect to Jumia API. Check your credentials and API URL.');

                return self::FAILURE;
            }

            $filters = ['Limit' => '100'];

            if ($this->option('all')) {
                $this->info('Fetching all Jumia orders...');
            } elseif ($this->option('days') !== null) {
                $filters['UpdatedAfter'] = now()->subDays((int) $this->option('days'))->format('c');
                $this->info('Fetching Jumia orders updated in the last '.$this->option('days').' days...');
            } elseif ($integration->last_sync_at) {
                $filters['UpdatedAfter'] = $integration->last_sync_at->format('c');
                $this->info('Fetching orders updated since '.$integration->last_sync_at->format('Y-m-d H:i:s'));
            } else {
                $this->info('First sync: fetching recent Jumia orders...');
            }

            $imported = 0;
            $updated = 0;
            $failed = 0;
            $totalProcessed = 0;

            foreach ($client->getAllOrders($filters) as $orders) {
                $progressBar = $this->output->createProgressBar(count($orders));
                $progressBar->start();

                foreach ($orders as $order) {
                    try {
                        DB::beginTransaction();

                        $externalId = (string) ($order['OrderId'] ?? $order['OrderNumber'] ?? '');
                        $exists = DB::table('pos_sales')
                            ->where('source', 'jumia')
                            ->where('external_id', $externalId)
                            ->exists();

                        $importer->import($order);
                        $exists ? $updated++ : $imported++;

                        DB::commit();
                    } catch (\InvalidArgumentException $e) {
                        DB::rollBack();
                        $failed++;
                        $this->newLine();
                        $this->warn('Skipped order: '.$e->getMessage());
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        $failed++;
                        $this->newLine();
                        $this->error('Failed order '.$externalId.': '.$e->getMessage());
                    }

                    $totalProcessed++;
                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->newLine();
            }

            $integration->forceFill([
                'last_sync_at' => now(),
                'last_error' => null,
            ])->save();

            $this->info("Jumia sync done: {$totalProcessed} processed, {$imported} new, {$updated} updated, {$failed} failed.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $integration->update(['last_error' => $e->getMessage()]);
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
