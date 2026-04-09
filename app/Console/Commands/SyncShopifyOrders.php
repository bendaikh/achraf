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
                            {--limit=50 : Maximum number of orders to fetch}
                            {--days=7 : Fetch orders from the last N days (default: 7)}';

    protected $description = 'Sync orders from Shopify using the Admin API';

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

        if (! $integration->api_access_token || ! $integration->shop_name) {
            $this->error('Shopify API credentials not configured.');

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
            $limit = (int) $this->option('limit');
            $days = (int) $this->option('days');

            if ($sinceId) {
                $orders = $client->getOrdersSince($sinceId, $limit);
                $this->info("Fetching orders since ID: {$sinceId}");
            } else {
                $dateTime = now()->subDays($days)->toIso8601String();
                $orders = $client->getOrdersCreatedAfter($dateTime, $limit);
                $this->info("Fetching orders from the last {$days} days");
            }

            if (empty($orders)) {
                $this->info('No new orders to sync.');
                $integration->forceFill(['last_sync_at' => now()])->save();

                return self::SUCCESS;
            }

            $this->info('Found '.count($orders).' order(s) to process.');

            $imported = 0;
            $skipped = 0;

            $progressBar = $this->output->createProgressBar(count($orders));
            $progressBar->start();

            foreach ($orders as $order) {
                try {
                    DB::beginTransaction();

                    $externalId = (string) ($order['id'] ?? '');
                    $existing = DB::table('pos_sales')
                        ->where('source', 'shopify')
                        ->where('external_id', $externalId)
                        ->exists();

                    if ($existing) {
                        $skipped++;
                    } else {
                        $importer->import($order);
                        $imported++;
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("\nFailed to import order {$order['id']}: ".$e->getMessage());
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Import completed:");
            $this->line("  - Imported: {$imported}");
            $this->line("  - Skipped (already exists): {$skipped}");

            $integration->forceFill(['last_sync_at' => now()])->save();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

