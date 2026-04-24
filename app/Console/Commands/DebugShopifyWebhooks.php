<?php

namespace App\Console\Commands;

use App\Models\ShopifyIntegration;
use App\Services\ShopifyApiClient;
use Illuminate\Console\Command;

class DebugShopifyWebhooks extends Command
{
    protected $signature = 'shopify:debug-webhooks';

    protected $description = 'Debug Shopify webhook configuration and show diagnostic info';

    public function handle(): int
    {
        $integration = ShopifyIntegration::query()->first();

        if (!$integration) {
            $this->error('No Shopify integration configured.');
            return self::FAILURE;
        }

        $this->info('═══════════════════════════════════════');
        $this->info('  Shopify Integration Diagnostic');
        $this->info('═══════════════════════════════════════');
        $this->newLine();

        // Check integration config
        $this->info('Integration Configuration:');
        $this->line('  Shop name: ' . ($integration->shop_name ?: 'NOT SET'));
        $this->line('  Enabled: ' . ($integration->enabled ? 'Yes' : 'No'));
        $this->line('  API version: ' . ($integration->api_version ?: 'default'));
        $this->line('  Has OAuth token: ' . ($integration->oauth_access_token ? 'Yes' : 'No'));
        $this->line('  Has API token: ' . ($integration->api_access_token ? 'Yes' : 'No'));
        $this->line('  Has OAuth client secret: ' . ($integration->oauth_client_secret ? 'Yes' : 'No'));
        $this->line('  Has webhook secret: ' . ($integration->webhook_secret ? 'Yes' : 'No'));
        $this->line('  Last sync: ' . ($integration->last_sync_at ?: 'Never'));
        $this->newLine();

        // Check APP_URL
        $appUrl = config('app.url');
        $this->info('Application URL:');
        $this->line('  APP_URL: ' . $appUrl);
        
        if (str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            $this->warn('  ⚠ WARNING: APP_URL is localhost. Shopify cannot reach localhost!');
            $this->warn('  ⚠ You must set APP_URL to your public production domain.');
        }
        $this->newLine();

        // Test connection
        $this->info('Testing Shopify API connection...');
        $client = new ShopifyApiClient($integration);
        
        if (!$client->testConnection()) {
            $this->error('  ✗ Connection failed. Check your credentials.');
            return self::FAILURE;
        }
        $this->info('  ✓ Connection successful');
        $this->newLine();

        // List registered webhooks
        $this->info('Registered Webhooks in Shopify:');
        $webhooks = $client->getWebhooks();
        
        if (empty($webhooks)) {
            $this->error('  ✗ No webhooks registered!');
            $this->warn('  Run: php artisan shopify:register-webhooks');
            return self::FAILURE;
        }

        $headers = ['Topic', 'Address'];
        $rows = [];
        foreach ($webhooks as $webhook) {
            $rows[] = [
                $webhook['topic'],
                $webhook['address'],
            ];
        }
        $this->table($headers, $rows);

        // Check if required webhooks are registered
        $requiredTopics = ['orders/create', 'orders/updated'];
        $registeredTopics = array_column($webhooks, 'topic');
        $missing = array_diff($requiredTopics, $registeredTopics);

        if (!empty($missing)) {
            $this->newLine();
            $this->error('Missing webhooks: ' . implode(', ', $missing));
            $this->warn('Run: php artisan shopify:register-webhooks');
        } else {
            $this->newLine();
            $this->info('✓ All required webhooks are registered');
        }

        // Check if webhook addresses match current APP_URL
        $this->newLine();
        $this->info('Checking webhook URLs match APP_URL...');
        $mismatches = [];
        foreach ($webhooks as $webhook) {
            if (!str_starts_with($webhook['address'], $appUrl)) {
                $mismatches[] = $webhook;
            }
        }

        if (!empty($mismatches)) {
            $this->warn('⚠ Some webhook URLs don\'t match your APP_URL:');
            foreach ($mismatches as $wh) {
                $this->line("  - {$wh['topic']}: {$wh['address']}");
            }
            $this->warn('Run: php artisan shopify:register-webhooks --force');
        } else {
            $this->info('✓ All webhook URLs match APP_URL');
        }

        // Recent logs
        $this->newLine();
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $this->info('Recent Shopify-related log entries:');
            $lines = [];
            $handle = fopen($logPath, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    if (stripos($line, 'shopify') !== false || stripos($line, 'webhook') !== false) {
                        $lines[] = $line;
                    }
                }
                fclose($handle);
            }
            
            $recent = array_slice($lines, -10);
            if (empty($recent)) {
                $this->line('  No Shopify-related log entries found.');
            } else {
                foreach ($recent as $line) {
                    $this->line('  ' . trim($line));
                }
            }
        }

        return self::SUCCESS;
    }
}
