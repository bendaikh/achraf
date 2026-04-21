<?php

namespace App\Console\Commands;

use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use App\Services\ShopifyOrderImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncShopifyOrders extends Command
{
    protected $signature = 'shopify:sync-orders 
                            {--since-id= : Fetch orders after this Shopify order ID}
                            {--days= : Fetch orders from the last N days (default: all orders)}
                            {--all : Fetch ALL orders (ignore date limit)}';

    protected $description = 'Sync orders from Shopify using the Admin API with pagination';

    public function handle(ShopifyOrderImporter $importer): int
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration) {
            $this->error('No Shopify integration configured. Please set it up first.');

            return self::FAILURE;
        }

        if (! $integration->enabled) {
            $this->warn('Shopify integration is disabled.');

            return self::FAILURE;
        }

        $accessToken = $integration->oauth_access_token ?? $integration->api_access_token;

        if (! $accessToken || ! $integration->shop_name) {
            $this->error('Shopify API credentials not configured. Please configure your integration first.');
            $this->line('Go to /integrations/shopify to set up your Shopify connection.');

            return self::FAILURE;
        }

        $this->info("Starting Shopify order sync for shop: {$integration->shop_name}");

        try {
            $client = new ShopifyApiClient($integration);

            if (! $client->testConnection()) {
                $this->error('Failed to connect to Shopify API. Please check your credentials.');

                return self::FAILURE;
            }

            $this->info('Connection to Shopify API verified.');

            $sinceId = $this->option('since-id');
            $days = $this->option('days');
            $fetchAll = $this->option('all');

            $params = ['status' => 'any', 'limit' => 250];

            if ($sinceId) {
                $params['since_id'] = $sinceId;
                $this->info("Fetching orders since ID: {$sinceId}");
            } elseif ($days !== null) {
                $dateTime = now()->subDays((int) $days)->toIso8601String();
                $params['created_at_min'] = $dateTime;
                $this->info("Fetching orders from the last {$days} days");
            } elseif ($fetchAll) {
                $this->info('Fetching ALL orders (this may take a while)...');
            } else {
                // Default: fetch orders updated since last sync, or all if never synced
                if ($integration->last_sync_at) {
                    $params['updated_at_min'] = $integration->last_sync_at->toIso8601String();
                    $this->info("Fetching orders updated since: {$integration->last_sync_at->format('Y-m-d H:i:s')}");
                } else {
                    $this->info('First sync: fetching ALL orders...');
                }
            }

            $imported = 0;
            $updated = 0;
            $failed = 0;
            $totalProcessed = 0;
            $page = 0;

            // Use generator for cursor-based pagination
            foreach ($client->getAllOrders($params) as $orders) {
                $page++;
                $batchCount = count($orders);
                $this->info("\nPage {$page}: Processing {$batchCount} orders...");

                $progressBar = $this->output->createProgressBar($batchCount);
                $progressBar->start();

                foreach ($orders as $order) {
                    try {
                        DB::beginTransaction();

                        $externalId = (string) ($order['id'] ?? '');
                        $existing = DB::table('pos_sales')
                            ->where('source', 'shopify')
                            ->where('external_id', $externalId)
                            ->exists();

                        $importer->import($order);

                        if ($existing) {
                            $updated++;
                        } else {
                            $imported++;
                        }

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $failed++;
                        $this->newLine();
                        $this->error("Failed to import order {$order['id']}: ".$e->getMessage());
                    }

                    $totalProcessed++;
                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->newLine();
            }

            if ($totalProcessed === 0) {
                $this->info('No new orders to sync.');
            }

            $this->newLine();
            $this->info('═══════════════════════════════════════');
            $this->info("Sync completed successfully!");
            $this->line("  - Total processed: {$totalProcessed}");
            $this->line("  - New orders imported: {$imported}");
            $this->line("  - Existing orders updated: {$updated}");
            if ($failed > 0) {
                $this->line("  - Failed: {$failed}");
            }
            $this->info('═══════════════════════════════════════');

            $integration->forceFill(['last_sync_at' => now()])->save();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
