<?php

namespace App\Console\Commands;

use App\Models\PosSale;
use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use App\Services\ShopifyOrderImporter;
use Illuminate\Console\Command;

class FixShopifyAnonymousClients extends Command
{
    protected $signature = 'shopify:fix-anonymous-clients 
                            {--dry-run : Show what would be fixed without making changes}
                            {--limit=100 : Maximum number of orders to process}';

    protected $description = 'Fix Shopify orders that have no client (Client anonyme) by re-fetching from Shopify';

    public function handle(ShopifyOrderImporter $importer): int
    {
        $integration = ShopifyIntegration::query()->where('enabled', true)->first();

        if (! $integration) {
            $this->error('No active Shopify integration found.');
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info('Finding Shopify orders without clients...');

        $orders = PosSale::query()
            ->where('source', 'shopify')
            ->whereNull('client_id')
            ->whereNotNull('external_id')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No anonymous Shopify orders found. All orders have clients!');
            return self::SUCCESS;
        }

        $this->info("Found {$orders->count()} orders without clients.");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made.');
        }

        $client = new ShopifyApiClient($integration);
        $fixed = 0;
        $failed = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        foreach ($orders as $sale) {
            $bar->advance();

            try {
                $orderData = $client->getOrder($sale->external_id);

                if (! $orderData) {
                    $this->newLine();
                    $this->warn("  Order {$sale->ticket_number} (ID: {$sale->external_id}) not found in Shopify.");
                    $skipped++;
                    continue;
                }

                $hasCustomerData = $this->hasCustomerData($orderData);

                if (! $hasCustomerData) {
                    $this->newLine();
                    $this->line("  Order {$sale->ticket_number}: No customer data in Shopify either.");
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $customerInfo = $this->extractCustomerInfo($orderData);
                    $this->newLine();
                    $this->info("  Would fix {$sale->ticket_number}: {$customerInfo}");
                    $fixed++;
                    continue;
                }

                $importer->import($orderData);
                $fixed++;

            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("  Error processing {$sale->ticket_number}: {$e->getMessage()}");
                $failed++;
            }

            usleep(250000);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Results:");
        $this->line("  Fixed: {$fixed}");
        $this->line("  Skipped (no data): {$skipped}");
        $this->line("  Failed: {$failed}");

        if ($dryRun && $fixed > 0) {
            $this->newLine();
            $this->info("Run without --dry-run to apply these fixes.");
        }

        return self::SUCCESS;
    }

    private function hasCustomerData(array $order): bool
    {
        if (! empty($order['email'])) {
            return true;
        }

        if (! empty($order['phone'])) {
            return true;
        }

        $customer = $order['customer'] ?? [];
        if (is_array($customer)) {
            if (! empty($customer['email']) || ! empty($customer['phone'])) {
                return true;
            }
            $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
            if ($name !== '') {
                return true;
            }
        }

        $billing = $order['billing_address'] ?? [];
        if (is_array($billing) && ! empty($billing['name'])) {
            return true;
        }

        $shipping = $order['shipping_address'] ?? [];
        if (is_array($shipping) && ! empty($shipping['name'])) {
            return true;
        }

        if (is_array($billing) && ! empty($billing['phone'])) {
            return true;
        }

        if (is_array($shipping) && ! empty($shipping['phone'])) {
            return true;
        }

        return false;
    }

    private function extractCustomerInfo(array $order): string
    {
        $parts = [];

        $email = $order['email'] ?? $order['customer']['email'] ?? null;
        if ($email) {
            $parts[] = "email: {$email}";
        }

        $phone = $order['phone'] 
            ?? $order['billing_address']['phone'] 
            ?? $order['shipping_address']['phone'] 
            ?? $order['customer']['phone'] 
            ?? null;
        if ($phone) {
            $parts[] = "phone: {$phone}";
        }

        $name = $order['billing_address']['name'] 
            ?? $order['shipping_address']['name'] 
            ?? null;
        if (! $name && isset($order['customer'])) {
            $name = trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''));
        }
        if ($name) {
            $parts[] = "name: {$name}";
        }

        return $parts ? implode(', ', $parts) : 'Unknown';
    }
}
