<?php

namespace App\Console\Commands;

use App\Models\PosSale;
use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixShopifyOrderNumbers extends Command
{
    protected $signature = 'shopify:fix-order-numbers';

    protected $description = 'Update existing Shopify orders to use the correct order names (e.g., FTC8807) by batch-fetching from Shopify';

    public function handle(): int
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration) {
            $this->error('No Shopify integration configured.');
            return self::FAILURE;
        }

        $accessToken = $integration->oauth_access_token ?? $integration->api_access_token;

        if (! $accessToken || ! $integration->shop_name) {
            $this->error('Shopify API credentials not configured.');
            return self::FAILURE;
        }

        $this->info("Fixing Shopify order numbers for shop: {$integration->shop_name}");

        // Get all orders that need fixing (either SHOPIFY- prefix or just numeric)
        $totalCount = PosSale::query()
            ->where('source', 'shopify')
            ->where(function($query) {
                $query->where('ticket_number', 'like', 'SHOPIFY-%')
                      ->orWhereRaw("ticket_number REGEXP '^[0-9]+$'");
            })
            ->count();

        if ($totalCount === 0) {
            $this->info('No orders need fixing. All order numbers are already correct.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} orders to fix.");
        $this->info("Batch-fetching orders from Shopify (250 per page)...");

        try {
            $client = new ShopifyApiClient($integration);

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            $fixed = 0;
            $failed = 0;
            $page = 0;

            // Fetch ALL orders from Shopify in batches of 250
            foreach ($client->getAllOrders(['status' => 'any', 'limit' => 250]) as $orders) {
                $page++;

                foreach ($orders as $order) {
                    try {
                        $externalId = (string) ($order['id'] ?? '');
                        if ($externalId === '') {
                            continue;
                        }

                        $orderName = (string) ($order['name'] ?? $order['order_number'] ?? $externalId);
                        // Remove the "#" prefix if present (Shopify names usually come as "#FTC8807")
                        $ticketNumber = ltrim($orderName, '#');

                        // Update the matching order in database
                        $updated = DB::table('pos_sales')
                            ->where('source', 'shopify')
                            ->where('external_id', $externalId)
                            ->where(function($query) {
                                $query->where('ticket_number', 'like', 'SHOPIFY-%')
                                      ->orWhereRaw("ticket_number REGEXP '^[0-9]+$'");
                            })
                            ->update(['ticket_number' => $ticketNumber]);

                        if ($updated > 0) {
                            $fixed++;
                            $progressBar->advance();
                        }
                    } catch (\Exception $e) {
                        $failed++;
                    }
                }
            }

            $progressBar->finish();
            $this->newLine(2);
            $this->info('═══════════════════════════════════════');
            $this->info("Fix completed!");
            $this->line("  - Pages fetched from Shopify: {$page}");
            $this->line("  - Orders fixed: {$fixed}");
            if ($failed > 0) {
                $this->line("  - Failed: {$failed}");
            }
            $this->info('═══════════════════════════════════════');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Fix failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
