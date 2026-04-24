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

    protected $description = 'Register webhooks in Shopify for real-time order and product sync';

    public function handle(): int
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
            $this->error('Shopify API credentials not configured.');
            return self::FAILURE;
        }

        $client = new ShopifyApiClient($integration);

        if (!$client->testConnection()) {
            $this->error('Failed to connect to Shopify API. Please check your credentials.');
            return self::FAILURE;
        }

        $this->info("Connected to Shopify shop: {$integration->shop_name}");

        // Get existing webhooks
        $existingWebhooks = $client->getWebhooks();
        
        if ($this->option('list')) {
            $this->listWebhooks($existingWebhooks);
            return self::SUCCESS;
        }

        // Define webhooks to register
        $baseUrl = config('app.url');
        $webhooksToRegister = [
            'orders/create' => "{$baseUrl}/api/webhooks/shopify/orders/create",
            'orders/updated' => "{$baseUrl}/api/webhooks/shopify/orders/updated",
            'products/create' => "{$baseUrl}/api/webhooks/shopify/products/create",
            'products/update' => "{$baseUrl}/api/webhooks/shopify/products/update",
            'products/delete' => "{$baseUrl}/api/webhooks/shopify/products/delete",
        ];

        $this->info("Base URL: {$baseUrl}");
        $this->newLine();

        // If --force, delete existing webhooks first
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

        // Build a map of existing webhooks by topic
        $existingTopics = [];
        foreach ($existingWebhooks as $webhook) {
            $existingTopics[$webhook['topic']] = $webhook['address'];
        }

        $this->info('Registering webhooks...');
        $this->newLine();

        $registered = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($webhooksToRegister as $topic => $address) {
            // Check if webhook already exists
            if (isset($existingTopics[$topic])) {
                if ($existingTopics[$topic] === $address) {
                    $this->line("  ⏭ Skipped (already registered): {$topic}");
                    $skipped++;
                    continue;
                }
                $this->warn("  ⚠ Different URL registered for {$topic}: {$existingTopics[$topic]}");
            }

            $result = $client->createWebhook($topic, $address);
            
            if ($result) {
                $this->info("  ✓ Registered: {$topic} → {$address}");
                $registered++;
            } else {
                $this->error("  ✗ Failed: {$topic}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->line("  Registered: {$registered}");
        $this->line("  Skipped: {$skipped}");
        if ($failed > 0) {
            $this->line("  Failed: {$failed}");
        }
        $this->info('═══════════════════════════════════════');

        if ($failed > 0) {
            $this->newLine();
            $this->warn('Some webhooks failed to register. Make sure:');
            $this->line('  1. Your APP_URL is publicly accessible (not localhost)');
            $this->line('  2. Your Shopify app has the required permissions');
            $this->line('  3. The webhook endpoints are not blocked by CSRF protection');
        }

        if ($registered > 0 || $skipped > 0) {
            $this->newLine();
            $this->info('✓ Webhooks are now set up for real-time sync!');
            $this->line('When orders are created or updated in Shopify, they will sync automatically.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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
