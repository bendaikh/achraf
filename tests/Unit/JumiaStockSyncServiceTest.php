<?php

namespace Tests\Unit;

use App\Models\JumiaIntegration;
use App\Models\Product;
use App\Services\Jumia\JumiaApiClient;
use App\Services\Jumia\JumiaStockSyncLogEntry;
use App\Services\Jumia\JumiaStockSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class JumiaStockSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_skips_products_already_matching_jumia_stock(): void
    {
        Product::create([
            'name' => 'Matched Product',
            'ref' => 'SKU-MATCH',
            'stock_enligne' => 12,
        ]);

        $integration = JumiaIntegration::create([
            'integration_name' => 'Jumia',
            'client_id' => 'client-id',
            'refresh_token' => 'refresh-token',
            'enabled' => true,
        ]);

        $client = Mockery::mock(JumiaApiClient::class);
        $client->shouldReceive('getStockForSellerSku')
            ->once()
            ->with('SKU-MATCH')
            ->andReturn(['stock' => 12, 'variation_id' => 'var-1']);

        $client->shouldNotReceive('updateProductStockBatch');

        $service = new JumiaStockSyncService($client, $integration);
        $result = $service->sync([
            'batch_size' => 10,
            'delay_ms' => 0,
            'max_retries' => 1,
            'linked_only' => false,
        ]);

        $this->assertSame(1, $result->totalChecked);
        $this->assertSame(1, $result->totalAlreadySynced);
        $this->assertSame(0, $result->totalUpdated);
        $this->assertSame(JumiaStockSyncLogEntry::STATUS_ALREADY_SYNCED, $result->entries[0]->status);
    }

    public function test_sync_updates_products_with_different_stock(): void
    {
        $product = Product::create([
            'name' => 'Outdated Product',
            'ref' => 'SKU-UPDATE',
            'stock_enligne' => 8,
        ]);

        $integration = JumiaIntegration::create([
            'integration_name' => 'Jumia',
            'client_id' => 'client-id',
            'refresh_token' => 'refresh-token',
            'enabled' => true,
        ]);

        $client = Mockery::mock(JumiaApiClient::class);
        $client->shouldReceive('getStockForSellerSku')
            ->once()
            ->with('SKU-UPDATE')
            ->andReturn(['stock' => 3, 'variation_id' => 'var-99']);

        $client->shouldReceive('updateProductStockBatch')
            ->once()
            ->with([
                [
                    'sellerSku' => 'SKU-UPDATE',
                    'stock' => 8,
                    'variationId' => 'var-99',
                ],
            ])
            ->andReturn(['feedId' => 'feed-123']);

        $service = new JumiaStockSyncService($client, $integration);
        $result = $service->sync([
            'batch_size' => 10,
            'delay_ms' => 0,
            'max_retries' => 1,
            'linked_only' => false,
        ]);

        $this->assertSame(1, $result->totalUpdated);
        $this->assertSame(JumiaStockSyncLogEntry::STATUS_UPDATED, $result->entries[0]->status);

        $product->refresh();
        $this->assertSame('var-99', $product->jumia_product_sid);
        $this->assertNotNull($product->jumia_stock_synced_at);
    }

    public function test_sync_marks_missing_jumia_products_as_not_found(): void
    {
        Product::create([
            'name' => 'Missing Product',
            'ref' => 'SKU-MISSING',
            'stock_enligne' => 5,
        ]);

        $integration = JumiaIntegration::create([
            'integration_name' => 'Jumia',
            'client_id' => 'client-id',
            'refresh_token' => 'refresh-token',
            'enabled' => true,
        ]);

        $client = Mockery::mock(JumiaApiClient::class);
        $client->shouldReceive('getStockForSellerSku')
            ->once()
            ->with('SKU-MISSING')
            ->andReturn(null);

        $service = new JumiaStockSyncService($client, $integration);
        $result = $service->sync([
            'batch_size' => 10,
            'delay_ms' => 0,
            'max_retries' => 1,
            'linked_only' => false,
        ]);

        $this->assertSame(1, $result->totalNotFound);
        $this->assertSame(JumiaStockSyncLogEntry::STATUS_NOT_FOUND, $result->entries[0]->status);
    }

    public function test_sync_dry_run_does_not_push_updates(): void
    {
        Product::create([
            'name' => 'Dry Run Product',
            'ref' => 'SKU-DRY',
            'stock_enligne' => 20,
        ]);

        $integration = JumiaIntegration::create([
            'integration_name' => 'Jumia',
            'client_id' => 'client-id',
            'refresh_token' => 'refresh-token',
            'enabled' => true,
        ]);

        $client = Mockery::mock(JumiaApiClient::class);
        $client->shouldReceive('getStockForSellerSku')
            ->once()
            ->with('SKU-DRY')
            ->andReturn(['stock' => 1, 'variation_id' => 'var-dry']);

        $client->shouldNotReceive('updateProductStockBatch');

        $service = new JumiaStockSyncService($client, $integration);
        $result = $service->sync([
            'batch_size' => 10,
            'delay_ms' => 0,
            'max_retries' => 1,
            'dry_run' => true,
            'linked_only' => false,
        ]);

        $this->assertSame(1, $result->totalUpdated);
        $this->assertStringContainsString('Dry run', (string) $result->entries[0]->message);
    }

    public function test_linked_only_sync_pushes_without_catalog_lookup(): void
    {
        $product = Product::create([
            'name' => 'Linked Product',
            'ref' => 'SKU-LINKED',
            'stock_enligne' => 4,
            'jumia_product_sid' => 'var-linked',
        ]);

        Product::create([
            'name' => 'Unlinked Product',
            'ref' => 'SKU-UNLINKED',
            'stock_enligne' => 9,
        ]);

        $integration = JumiaIntegration::create([
            'integration_name' => 'Jumia',
            'client_id' => 'client-id',
            'refresh_token' => 'refresh-token',
            'enabled' => true,
        ]);

        $client = Mockery::mock(JumiaApiClient::class);
        $client->shouldNotReceive('getStockForSellerSku');
        $client->shouldReceive('updateProductStockBatch')
            ->once()
            ->with([
                [
                    'sellerSku' => 'SKU-LINKED',
                    'stock' => 4,
                    'variationId' => 'var-linked',
                ],
            ])
            ->andReturn(['feedId' => 'feed-linked']);

        $service = new JumiaStockSyncService($client, $integration);
        $result = $service->sync([
            'batch_size' => 10,
            'delay_ms' => 0,
            'max_retries' => 1,
            'linked_only' => true,
        ]);

        $this->assertSame(1, $result->totalUpdated);
        $product->refresh();
        $this->assertNotNull($product->jumia_stock_synced_at);
    }
}
