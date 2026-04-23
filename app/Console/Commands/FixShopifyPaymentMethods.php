<?php

namespace App\Console\Commands;

use App\Models\PosSale;
use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixShopifyPaymentMethods extends Command
{
    protected $signature = 'shopify:fix-payment-methods';

    protected $description = 'Update existing Shopify orders to use the correct payment methods by batch-fetching from Shopify';

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

        $this->info("Fixing Shopify payment methods for shop: {$integration->shop_name}");

        // Get all Shopify orders
        $totalCount = PosSale::query()
            ->where('source', 'shopify')
            ->whereNotNull('external_id')
            ->count();

        if ($totalCount === 0) {
            $this->info('No Shopify orders found.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} Shopify orders to check.");
        $this->info("Batch-fetching orders from Shopify (250 per page)...");

        try {
            $client = new ShopifyApiClient($integration);

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            $fixed = 0;
            $unchanged = 0;
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

                        $paymentMethod = $this->mapPaymentMethod($order);

                        // Find the matching order in database
                        $sale = PosSale::query()
                            ->where('source', 'shopify')
                            ->where('external_id', $externalId)
                            ->first();

                        if ($sale) {
                            if ($sale->payment_method !== $paymentMethod) {
                                $sale->update(['payment_method' => $paymentMethod]);
                                $fixed++;
                            } else {
                                $unchanged++;
                            }
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
            $this->line("  - Payment methods updated: {$fixed}");
            $this->line("  - Already correct: {$unchanged}");
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

    private function mapPaymentMethod(array $order): string
    {
        // Check payment gateway names first (most reliable)
        $gateway = strtolower((string) ($order['gateway'] ?? ''));
        
        // Cash on Delivery detection
        if (str_contains($gateway, 'cod') || 
            str_contains($gateway, 'cash_on_delivery') || 
            str_contains($gateway, 'cash on delivery') ||
            str_contains($gateway, 'contre remboursement')) {
            return PosSale::PAYMENT_CASH;
        }
        
        // Bank transfer detection
        if (str_contains($gateway, 'bank') || 
            str_contains($gateway, 'transfer') || 
            str_contains($gateway, 'virement')) {
            return PosSale::PAYMENT_TRANSFER;
        }
        
        // Cheque detection
        if (str_contains($gateway, 'cheque') || str_contains($gateway, 'check')) {
            return PosSale::PAYMENT_CHEQUE;
        }
        
        // Manual payment (often used for cash)
        if (str_contains($gateway, 'manual')) {
            return PosSale::PAYMENT_CASH;
        }
        
        // Check payment gateway names in payment_gateway_names array
        $paymentGateways = $order['payment_gateway_names'] ?? [];
        if (is_array($paymentGateways)) {
            foreach ($paymentGateways as $gw) {
                $gwLower = strtolower((string) $gw);
                if (str_contains($gwLower, 'cod') || 
                    str_contains($gwLower, 'cash_on_delivery') ||
                    str_contains($gwLower, 'cash on delivery') ||
                    str_contains($gwLower, 'contre remboursement')) {
                    return PosSale::PAYMENT_CASH;
                }
                if (str_contains($gwLower, 'bank') || 
                    str_contains($gwLower, 'transfer') || 
                    str_contains($gwLower, 'virement')) {
                    return PosSale::PAYMENT_TRANSFER;
                }
                if (str_contains($gwLower, 'cheque') || str_contains($gwLower, 'check')) {
                    return PosSale::PAYMENT_CHEQUE;
                }
            }
        }
        
        // Check tags as fallback
        $tags = strtolower((string) ($order['tags'] ?? ''));
        if (str_contains($tags, 'cash') || str_contains($tags, 'cod')) {
            return PosSale::PAYMENT_CASH;
        }
        if (str_contains($tags, 'cheque') || str_contains($tags, 'check')) {
            return PosSale::PAYMENT_CHEQUE;
        }
        
        // Default to card for online payments
        return PosSale::PAYMENT_CARD;
    }
}
