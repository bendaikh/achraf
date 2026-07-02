<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Product;
use App\Services\Jumia\JumiaApiClient;
use App\Services\Jumia\JumiaOrderImporter;
use App\Services\Jumia\JumiaStatusMapper;
use App\Services\MarketplaceStockSyncService;
use App\Services\OrderToInvoiceConverter;
use App\Support\OrderSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class JumiaOrderImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_maps_vendor_center_nested_items_and_local_prices(): void
    {
        $product = Product::create([
            'name' => 'Tapis Audi',
            'ref' => 'FAST-TAP-PRO-042',
            'sale_price' => 980,
            'vat_category' => 'TVA (20%)',
        ]);

        $client = Mockery::mock(JumiaApiClient::class);
        $client->shouldReceive('getOrderItems')
            ->once()
            ->with('30e51bbf-4ce1-4a2f-a9bb-ebfc5ec0387e')
            ->andReturn([
                [
                    'orderId' => '30e51bbf-4ce1-4a2f-a9bb-ebfc5ec0387e',
                    'orderNumber' => '349314828',
                    'items' => [
                        [
                            'id' => 'f882331d-ff5d-488e-94a3-fce7aa4e0018',
                            'status' => 'SHIPPED',
                            'itemPriceLocal' => 980,
                            'paidPriceLocal' => 980,
                            'shippingAmountLocal' => 14,
                            'taxAmount' => 163.33,
                            'country' => ['currencyCode' => 'MAD'],
                            'product' => [
                                'name' => 'Tapis de sol Audi A6 C8 2019 et plus',
                                'sellerSku' => 'FAST-TAP-PRO-042',
                            ],
                            'shippingAddress' => [
                                'firstName' => 'Moulay',
                                'lastName' => 'Alaoui',
                                'phone' => '0612345678',
                                'city' => 'TEMARA',
                                'countryName' => 'Morocco',
                            ],
                        ],
                    ],
                ],
            ]);

        $importer = new JumiaOrderImporter(
            $client,
            new JumiaStatusMapper,
            app(OrderToInvoiceConverter::class),
            app(MarketplaceStockSyncService::class),
        );

        $sale = $importer->import([
            'id' => '30e51bbf-4ce1-4a2f-a9bb-ebfc5ec0387e',
            'number' => '349314828',
            'status' => 'SHIPPED',
            'createdAt' => '2026-07-01T10:00:00Z',
        ]);

        $sale->refresh()->load('items');

        $this->assertSame(OrderSource::JUMIA, $sale->source);
        $this->assertEquals(994.0, (float) $sale->total);
        $this->assertCount(1, $sale->items);
        $this->assertSame($product->id, $sale->items->first()->product_id);
        $this->assertSame('FAST-TAP-PRO-042', $sale->items->first()->ref);
        $this->assertEquals(994.0, (float) $sale->items->first()->line_total);
        $this->assertNotSame('Article Jumia', $sale->items->first()->designation);
        $this->assertInstanceOf(Client::class, $sale->client);
    }
}
