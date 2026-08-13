<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\ProductPurchaseHistoryService;
use App\Services\ProductPurchasePriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPurchaseHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_supplier_comes_from_most_recent_purchase_document(): void
    {
        $product = Product::create([
            'name' => 'Produit X',
            'ref' => 'PX-001',
            'item_kind' => Product::KIND_STOCKED,
        ]);

        $older = Supplier::create([
            'name' => 'CAR SHOP',
            'email' => 'carshop@example.com',
            'code' => 'CAR',
        ]);
        $newer = Supplier::create([
            'name' => 'AZMI FRERES',
            'email' => 'azmi@example.com',
            'code' => 'AZMI',
        ]);

        $oldInvoice = SupplierInvoice::create([
            'invoice_number' => 'FSI-2026/000001',
            'supplier_id' => $older->id,
            'invoice_date' => '2026-08-02',
            'total' => 850,
        ]);
        PurchaseItem::create([
            'purchaseable_type' => SupplierInvoice::class,
            'purchaseable_id' => $oldInvoice->id,
            'product_id' => $product->id,
            'ref' => $product->ref,
            'designation' => $product->name,
            'quantity' => 5,
            'unit_price' => 170,
            'tax_rate' => 20,
            'line_total' => 850,
        ]);

        $newInvoice = SupplierInvoice::create([
            'invoice_number' => 'FSI-2026/000002',
            'supplier_id' => $newer->id,
            'invoice_date' => '2026-08-11',
            'total' => 1600,
        ]);
        PurchaseItem::create([
            'purchaseable_type' => SupplierInvoice::class,
            'purchaseable_id' => $newInvoice->id,
            'product_id' => $product->id,
            'ref' => $product->ref,
            'designation' => $product->name,
            'quantity' => 10,
            'unit_price' => 160,
            'tax_rate' => 20,
            'line_total' => 1600,
        ]);

        $service = app(ProductPurchaseHistoryService::class);

        $last = $service->lastSuppliersForProducts([$product->id]);
        $this->assertSame('AZMI FRERES', $last[$product->id]['name']);
        $this->assertSame($newer->id, $last[$product->id]['id']);

        $history = $service->historyForProduct((int) $product->id);
        $this->assertCount(2, $history);
        $this->assertSame('11/08/2026', $history[0]['date_formatted']);
        $this->assertSame('AZMI FRERES', $history[0]['supplier_name']);
        $this->assertSame(10, $history[0]['quantity']);
        $this->assertSame(160.0, $history[0]['unit_price']);
        $this->assertSame('FSI-2026/000002', $history[0]['document_number']);
        $this->assertNotEmpty($history[0]['document_url']);
        $this->assertSame('02/08/2026', $history[1]['date_formatted']);
        $this->assertSame('CAR SHOP', $history[1]['supplier_name']);
    }

    public function test_does_not_alter_last_purchase_price_sync(): void
    {
        $product = Product::create([
            'name' => 'Produit Y',
            'ref' => 'PY-001',
            'last_purchase_price' => null,
        ]);

        app(ProductPurchasePriceService::class)->syncLastPurchasePrices([
            ['product_id' => $product->id, 'unit_price' => 199.99],
        ]);

        $this->assertEquals(199.99, (float) $product->fresh()->last_purchase_price);
    }
}
