<?php

namespace App\Console\Commands;

use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use App\Services\ShopifyProductImporter;
use Illuminate\Console\Command;

class SyncShopifyProducts extends Command
{
    protected $signature = 'shopify:sync-products
                            {--limit= : Limit the number of products to fetch per page}
                            {--status= : Filter by product status (active, draft, archived)}';

    protected $description = 'Sync products from Shopify using the Admin API with pagination';

    public function handle(ShopifyProductImporter $importer): int
    {
        $integration = ShopifyIntegration::query()->first();

        if (!$integration) {
            $this->error('No Shopify integration configured. Please set it up first.');
            return self::FAILURE;
        }

        if (!$integration->enabled) {
            $this->warn('Shopify integration is disabled.');
            return self::FAILURE;
        }

        $accessToken = $integration->oauth_access_token ?? $integration->api_access_token;

        if (!$integration->shop_name || !$accessToken) {
            $this->error('Shopify API credentials not configured. Please configure your integration first.');
            $this->line('Go to /integrations/shopify to set up your Shopify connection.');
            return self::FAILURE;
        }

        $this->info("Starting Shopify product sync for shop: {$integration->shop_name}");

        try {
            $client = new ShopifyApiClient($integration);

            if (!$client->testConnection()) {
                $this->error('Failed to connect to Shopify API. Please check your credentials.');
                return self::FAILURE;
            }

            $this->info('Connection to Shopify API verified.');

            $params = [];

            if ($this->option('limit')) {
                $params['limit'] = (int) $this->option('limit');
            }

            if ($this->option('status')) {
                $params['status'] = $this->option('status');
            }

            $this->info('Fetching products from Shopify...');
            $this->line('This may take a while for large product catalogs.');

            $totalImported = 0;
            $totalUpdated = 0;
            $totalFailed = 0;
            $pageNumber = 0;

            foreach ($client->getAllProducts($params) as $products) {
                $pageNumber++;
                $batchSize = count($products);

                $this->line("Processing page {$pageNumber} ({$batchSize} products)...");

                $results = $importer->importBatch($products);

                $totalImported += $results['imported'];
                $totalUpdated += $results['updated'];
                $totalFailed += $results['failed'];

                $this->info("  → Imported: {$results['imported']}, Updated: {$results['updated']}, Failed: {$results['failed']}");

                if (!empty($results['errors'])) {
                    foreach ($results['errors'] as $error) {
                        $this->warn("  ⚠ Product {$error['product_id']}: {$error['error']}");
                    }
                }
            }

            $this->newLine();
            $this->info('✓ Product synchronization completed!');
            $this->line("Total new products: {$totalImported}");
            $this->line("Total updated products: {$totalUpdated}");
            
            if ($totalFailed > 0) {
                $this->warn("Total failed: {$totalFailed}");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Product sync failed: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
