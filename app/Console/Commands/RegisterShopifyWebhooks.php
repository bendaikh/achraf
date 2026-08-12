<?php

namespace App\Console\Commands;

use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use Illuminate\Console\Command;

class RegisterShopifyWebhooks extends Command
{
    protected $signature = 'shopify:register-webhooks 
                            {--force : Delete existing webhooks and re-register}
                            {--list : Only list currently registered webhooks}';

    protected $description = 'Register Shopify webhooks for real-time order, fulfillment and product sync';

    public function handle(): int
    {
        $integration = ShopifyIntegration::query()->first();

        if (! $integration) {
            $this->error('No Shopify integration configured. Please set it up first.');

            return self::FAILURE;
        }

        if (! $integration->enabled) {
            $this->warn('Shopify integration is disabled.');

            return self::SUCCESS;
        }

        $accessToken = $integration->oauth_access_token ?? $integration->api_access_token;

        if (! $integration->shop_name || ! $accessToken) {
            $this->error('Shopify API credentials not configured.');

            return self::FAILURE;
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $isLocalUrl = $this->isLocalUrl($baseUrl);

        if ($isLocalUrl && ! $this->option('list') && ! $this->option('force')) {
            $this->warn("APP_URL is local ({$baseUrl}). Skipping webhook registration (Shopify needs a public URL).");

            return self::SUCCESS;
        }

        $client = new ShopifyApiClient($integration);

        if (! $client->testConnection()) {
            $this->error('Failed to connect to Shopify API. Please check your credentials.');

            return self::FAILURE;
        }

        $this->info("Connected to Shopify shop: {$integration->shop_name}");

        $existingWebhooks = $client->getWebhooks();

        if ($this->option('list')) {
            $this->listWebhooks($existingWebhooks);

            return self::SUCCESS;
        }

        $webhooksToRegister = [
            'orders/create' => "{$baseUrl}/api/webhooks/shopify/orders/create",
            'orders/updated' => "{$baseUrl}/api/webhooks/shopify/orders/updated",
            'fulfillments/create' => "{$baseUrl}/api/webhooks/shopify/fulfillments/create",
            'fulfillments/update' => "{$baseUrl}/api/webhooks/shopify/fulfillments/update",
            'products/create' => "{$baseUrl}/api/webhooks/shopify/products/create",
            'products/update' => "{$baseUrl}/api/webhooks/shopify/products/update",
            'products/delete' => "{$baseUrl}/api/webhooks/shopify/products/delete",
            'inventory_levels/update' => "{$baseUrl}/api/webhooks/shopify/inventory-levels/update",
        ];

        $this->info("Base URL: {$baseUrl}");
        $this->newLine();

        if ($this->option('force')) {
            $this->warn('Deleting existing webhooks...');
            foreach ($existingWebhooks as $webhook) {
                $deleted = $client->deleteWebhook((string) $webhook['id']);
                $status = $deleted ? '✓ Deleted' : '✗ Failed';
                $this->line("  {$status}: {$webhook['topic']} → {$webhook['address']}");
            }
            $this->newLine();
            $existingWebhooks = [];
        }

        $existingByTopic = [];
        foreach ($existingWebhooks as $webhook) {
            $existingByTopic[$webhook['topic']] = $webhook;
        }

        $this->info('Registering webhooks...');
        $this->newLine();

        $registered = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($webhooksToRegister as $topic => $address) {
            $existing = $existingByTopic[$topic] ?? null;

            if ($existing) {
                if (($existing['address'] ?? '') === $address) {
                    $this->line("  ⏭ Skipped (already registered): {$topic}");
                    $skipped++;

                    continue;
                }

                // Replace outdated URL for this topic only (safe for cron / new topics)
                $this->warn("  ↻ Updating URL for {$topic}");
                $this->line('     old: '.($existing['address'] ?? '—'));
                $this->line("     new: {$address}");
                $client->deleteWebhook((string) $existing['id']);
            }

            $result = $client->createWebhook($topic, $address);

            if ($result) {
                if ($existing) {
                    $this->info("  ✓ Updated: {$topic} → {$address}");
                    $updated++;
                } else {
                    $this->info("  ✓ Registered: {$topic} → {$address}");
                    $registered++;
                }
            } else {
                $this->error("  ✗ Failed: {$topic}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->line("  Registered: {$registered}");
        $this->line("  Updated: {$updated}");
        $this->line("  Skipped: {$skipped}");
        if ($failed > 0) {
            $this->line("  Failed: {$failed}");
        }
        $this->info('═══════════════════════════════════════');

        if ($failed > 0) {
            $this->newLine();
            $this->warn('Some webhooks failed to register. Make sure:');
            $this->line('  1. Your APP_URL is publicly accessible (not localhost)');
            $this->line('  2. Your Shopify app has the required permissions (read_fulfillments, etc.)');
            $this->line('  3. The webhook endpoints are not blocked by CSRF protection');
        }

        if ($registered > 0 || $updated > 0 || $skipped > 0) {
            $this->newLine();
            $this->info('✓ Webhooks are set up for real-time sync (orders, fulfillments/tracking, products).');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isLocalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;

        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with(strtolower($host), '.local')
            || str_ends_with(strtolower($host), '.test');
    }

    private function listWebhooks(array $webhooks): void
    {
        if (empty($webhooks)) {
            $this->warn('No webhooks currently registered.');

            return;
        }

        $this->info('Currently registered webhooks:');
        $this->newLine();

        $headers = ['ID', 'Topic', 'Address', 'Created At'];
        $rows = [];

        foreach ($webhooks as $webhook) {
            $rows[] = [
                $webhook['id'],
                $webhook['topic'],
                $webhook['address'],
                $webhook['created_at'] ?? 'N/A',
            ];
        }

        $this->table($headers, $rows);
    }
}
