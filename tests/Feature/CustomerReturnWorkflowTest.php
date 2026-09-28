<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnLine;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerReturnService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReturnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_draft_does_not_touch_stock(): void
    {
        Setting::set('stock_customer_returns_enabled', '1');
        $user = User::factory()->create();
        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        app(StockMovementService::class)->declarePhysicalStock(
            $product,
            5,
            (int) $belvedere->id,
            null,
            \App\Models\StockMovement::REASON_PURCHASE
        );

        $order = $this->orderWithProduct($product, 2);
        $before = $product->fresh()->physicalStock();

        $return = app(CustomerReturnService::class)->createDraft($order);

        $this->assertTrue($return->isDraft());
        $this->assertSame($before, $product->fresh()->physicalStock());
        $this->assertDatabaseHas('customer_return_lines', [
            'customer_return_id' => $return->id,
            'product_id' => $product->id,
            'quantity_received' => 2,
        ]);
    }

    public function test_validate_vendable_restocks_available_and_is_idempotent(): void
    {
        Setting::set('stock_customer_returns_enabled', '1');
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $service = app(StockMovementService::class);
        $service->declarePhysicalStock($product, 1, (int) $belvedere->id, null, \App\Models\StockMovement::REASON_PURCHASE);

        $order = $this->orderWithProduct($product, 3);
        $invoice = $this->invoiceForOrder($order, $product, 3);

        $returns = app(CustomerReturnService::class);
        $draft = $returns->createDraft($order);
        $draft = $returns->updateDraft($draft, [
            $draft->lines->first()->id => [
                'quantity_received' => 3,
                'condition' => CustomerReturnLine::CONDITION_VENDABLE,
                'warehouse_id' => $belvedere->id,
            ],
        ]);

        $validated = $returns->validate($draft);
        $this->assertTrue($validated->isValidated());
        $this->assertSame(4, $product->fresh()->physicalStock());
        $this->assertSame(4, $product->fresh()->availableStock());
        $this->assertNotNull($validated->credit_note_id);

        // Idempotent second validate
        $again = $returns->validate($validated);
        $this->assertSame(4, $product->fresh()->physicalStock());
        $this->assertSame($validated->credit_note_id, $again->credit_note_id);
    }

    public function test_damaged_goes_to_sav_not_available(): void
    {
        Setting::set('stock_customer_returns_enabled', '1');
        $this->actingAs(User::factory()->create());

        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $sav = Warehouse::savWarehouse();
        $this->assertNotNull($sav);
        $this->assertFalse($sav->isSellable());

        app(StockMovementService::class)->declarePhysicalStock(
            $product,
            2,
            (int) $belvedere->id,
            null,
            \App\Models\StockMovement::REASON_PURCHASE
        );

        $order = $this->orderWithProduct($product, 1);
        $returns = app(CustomerReturnService::class);
        $draft = $returns->createDraft($order);
        $lineId = $draft->lines->first()->id;
        $returns->updateDraft($draft, [
            $lineId => [
                'quantity_received' => 1,
                'condition' => CustomerReturnLine::CONDITION_DAMAGED,
                'warehouse_id' => $sav->id,
            ],
        ]);

        $returns->validate($draft->fresh());

        $product->refresh();
        $this->assertSame(3, $product->physicalStock());
        $this->assertSame(2, $product->availableStock());
        $this->assertSame(1, app(StockMovementService::class)->quantityAtWarehouse($product, (int) $sav->id));
    }

    public function test_carrier_returned_status_alone_does_not_create_return(): void
    {
        // Searching without selecting an order must not create stock.
        Setting::set('stock_customer_returns_enabled', '1');
        $matches = app(CustomerReturnService::class)->searchOrders('RETURNED-FAKE-TRACKING');
        $this->assertSame([], $matches);
        $this->assertSame(0, CustomerReturn::query()->count());
    }

    private function stockedProduct(): Product
    {
        return Product::create([
            'name' => 'Produit retour',
            'ref' => 'RET-SKU',
            'item_kind' => Product::KIND_STOCKED,
            'stock_quantity' => 0,
            'stock_magasin' => 0,
            'stock_sellable' => 0,
            'stock_enligne' => 0,
            'cost_price_ht' => 10,
            'vat_category' => 'TVA (20%)',
        ]);
    }

    private function orderWithProduct(Product $product, int $qty): PosSale
    {
        $client = Client::create(['name' => 'Client Retour']);
        $user = User::factory()->create();
        $order = PosSale::create([
            'ticket_number' => 'CMD-RET-'.$product->id.'-'.$qty,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'sold_at' => now(),
            'status' => 'pending',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'MAD',
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'transfer',
            'source' => 'libromart',
        ]);
        PosSaleItem::create([
            'pos_sale_id' => $order->id,
            'product_id' => $product->id,
            'ref' => $product->ref,
            'designation' => $product->name,
            'quantity' => $qty,
            'unit_price' => 100,
            'tax_rate' => 20,
            'discount' => 0,
            'line_total' => 100 * $qty,
        ]);

        return $order->fresh(['items', 'client']);
    }

    private function invoiceForOrder(PosSale $order, Product $product, int $qty): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => 'FA-RET-'.$order->id,
            'client_id' => $order->client_id,
            'pos_sale_id' => $order->id,
            'invoice_date' => now()->toDateString(),
            'currency' => 'MAD',
            'subtotal' => 100 * $qty,
            'total' => 120 * $qty,
            'payment_status' => 'unpaid',
        ]);
        InvoiceItem::create([
            'itemable_type' => Invoice::class,
            'itemable_id' => $invoice->id,
            'product_id' => $product->id,
            'ref' => $product->ref,
            'designation' => $product->name,
            'quantity' => $qty,
            'unit_price' => 100,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 120 * $qty,
        ]);

        return $invoice;
    }
}
