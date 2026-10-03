<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\OrderPhysicalStockService;
use App\Services\SalesStockIssueService;
use App\Services\StockMovementService;
use App\Support\OrderSource;
use App\Support\StockSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commande = pas de sortie → Picking = réservation → BL validé / Valider sortie = UNE sortie
 * physique → Facture issue du BL = aucune nouvelle sortie.
 */
class OrderToDeliveryNoteSingleStockExitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now()->subMinute());
        $this->user = User::factory()->create();
    }

    public function test_bl_validation_consumes_picking_reservation_and_invoice_from_bl_adds_no_exit(): void
    {
        $product = $this->stockedProduct('FLOW-A');
        $this->declarePhysicalStock($product, 10);
        $order = $this->orderWithLine($product, 2, 'CMD-FLOW-A');

        // Commande + picking : réservation uniquement.
        app(OrderPhysicalStockService::class)->ensureAllocated($order->fresh());
        $this->assertSame(0, $this->exitMovements($product));
        $this->assertSame(10, $this->quantity($product));
        $this->assertSame(2, $this->reserved($product));

        // Commande → BL (Gestion ventes › Commandes › Convertir).
        $note = $this->convertOrderToDeliveryNote($order);
        $this->assertSame($order->id, (int) $note->pos_sale_id);
        $this->assertSame(0, $this->exitMovements($product), 'BL en cours : pas de sortie.');

        // Validation du BL = la sortie physique (consomme la réservation).
        $this->validateDeliveryNote($note);
        $this->assertSame(1, $this->exitMovements($product));
        $this->assertSame(8, $this->quantity($product));
        $this->assertSame(0, $this->reserved($product));
        $this->assertNotNull($order->fresh()->physical_stock_processed_at);
        $this->assertSame(0, StockReservation::query()->active()->where('source_id', $order->id)->count());

        // Le picking ne ressort plus la commande.
        app(OrderPhysicalStockService::class)->ensureAllocated($order->fresh());
        app(OrderPhysicalStockService::class)->validateExit($order->fresh());
        $this->assertSame(1, $this->exitMovements($product));
        $this->assertSame(8, $this->quantity($product));

        // Facture depuis le BL : aucune nouvelle sortie.
        $invoice = $this->convertDeliveryNoteToInvoice($note);
        $this->assertNotNull($invoice->stock_applied_at);
        $this->assertSame(1, $this->exitMovements($product));
        $this->assertSame(8, $this->quantity($product));
    }

    public function test_picking_exit_then_bl_validation_does_not_exit_twice(): void
    {
        $product = $this->stockedProduct('FLOW-B');
        $this->declarePhysicalStock($product, 10);
        $order = $this->orderWithLine($product, 3, 'CMD-FLOW-B');
        app(OrderPhysicalStockService::class)->ensureAllocated($order->fresh());

        // Picking « Valider sortie ».
        $this->actingAs($this->user)->post(route('sales.picking.validate-exit'), ['order_ids' => [$order->id]])->assertRedirect();
        $this->assertSame(1, $this->exitMovements($product));
        $this->assertSame(7, $this->quantity($product));

        $note = $this->convertOrderToDeliveryNote($order);
        $this->validateDeliveryNote($note);
        $this->assertNotNull($note->fresh()->stock_applied_at);
        $this->assertSame(1, $this->exitMovements($product), 'BL validé après sortie picking : pas de 2e sortie.');
        $this->assertSame(7, $this->quantity($product));

        $this->convertDeliveryNoteToInvoice($note);
        $this->assertSame(1, $this->exitMovements($product));
        $this->assertSame(7, $this->quantity($product));
    }

    public function test_manual_bl_without_order_still_exits_once_on_validation(): void
    {
        $product = $this->stockedProduct('FLOW-C');
        $this->declarePhysicalStock($product, 5);
        $client = Client::create(['name' => 'Client BL direct']);
        $note = DeliveryNote::create([
            'delivery_number' => 'BL-DIRECT-1', 'client_id' => $client->id, 'delivery_date' => now(),
            'currency' => 'MAD', 'status' => 'En cours', 'stock_location' => 'Magasin Belvédère', 'subtotal' => 10, 'total' => 10,
        ]);
        $note->items()->create([
            'product_id' => $product->id, 'ref' => $product->ref, 'designation' => $product->name,
            'quantity' => 2, 'unit_price' => 5, 'tax_rate' => 20, 'discount' => 0, 'line_total' => 10,
        ]);

        $this->validateDeliveryNote($note);
        $this->validateDeliveryNote($note);
        $this->assertSame(1, $this->exitMovements($product));
        $this->assertSame(3, $this->quantity($product));
    }

    // ---------------------------------------------------------------- helpers

    private function convertOrderToDeliveryNote(PosSale $order): DeliveryNote
    {
        $this->actingAs($this->user)
            ->postJson(route('orders.bulk-convert'), ['order_ids' => [$order->id], 'type' => 'bon_livraison'])
            ->assertOk()->assertJson(['success' => true]);

        return DeliveryNote::query()->where('pos_sale_id', $order->id)->latest('id')->firstOrFail();
    }

    private function validateDeliveryNote(DeliveryNote $note): void
    {
        $note->update(['status' => 'livré']);
        app(SalesStockIssueService::class)->applyForDeliveryNoteIfNeeded($note->fresh('items'));
    }

    private function convertDeliveryNoteToInvoice(DeliveryNote $note): Invoice
    {
        $this->actingAs($this->user)
            ->postJson(route('delivery-notes.bulk-convert'), ['ids' => [$note->id], 'mode' => 'separate', 'target' => 'invoice'])
            ->assertOk();

        return Invoice::query()->findOrFail($note->fresh()->converted_invoice_id);
    }

    private function exitMovements(Product $product): int
    {
        return StockMovement::query()
            ->where('product_id', $product->id)
            ->whereIn('type', [StockMovement::TYPE_SALE, StockMovement::TYPE_ORDER_OUT])
            ->count();
    }

    private function quantity(Product $product): int
    {
        return (int) ProductStock::query()->where('product_id', $product->id)
            ->where('warehouse_id', Warehouse::fulfillmentWarehouse()->id)->sum('quantity');
    }

    private function reserved(Product $product): int
    {
        return (int) ProductStock::query()->where('product_id', $product->id)
            ->where('warehouse_id', Warehouse::fulfillmentWarehouse()->id)->sum('reserved');
    }

    private function stockedProduct(string $ref): Product
    {
        return Product::create([
            'name' => 'Produit '.$ref, 'ref' => $ref, 'item_kind' => Product::KIND_STOCKED,
            'stock_quantity' => 0, 'stock_enligne' => 0, 'stock_magasin' => 0,
            'cost_price_ht' => 20, 'vat_category' => 'TVA (20%)',
        ]);
    }

    private function declarePhysicalStock(Product $product, int $qty): void
    {
        $belvedere = Warehouse::fulfillmentWarehouse();
        $location = $belvedere->locations()->first() ?? WarehouseLocation::create([
            'warehouse_id' => $belvedere->id, 'code' => 'BEL-STOCK', 'name' => 'Bel Stock', 'status' => 'active',
        ]);
        app(StockMovementService::class)->adjustPhysicalStock(
            $product, $qty, (int) $belvedere->id, (int) $location->id, StockMovement::REASON_INVENTORY_CORRECTION
        );
    }

    private function orderWithLine(Product $product, int $qty, string $ticket): PosSale
    {
        $client = Client::create(['name' => 'Client '.$ticket]);
        $order = PosSale::create([
            'ticket_number' => $ticket, 'client_id' => $client->id, 'user_id' => $this->user->id,
            'sold_at' => now(), 'status' => 'pending', 'fulfillment_status' => 'unfulfilled',
            'currency' => 'MAD', 'subtotal' => 10 * $qty, 'total' => 10 * $qty,
            'payment_method' => 'cash', 'source' => OrderSource::SHOPIFY,
        ]);
        PosSaleItem::create([
            'pos_sale_id' => $order->id, 'product_id' => $product->id, 'ref' => $product->ref,
            'designation' => $product->name, 'quantity' => $qty, 'unit_price' => 10, 'tax_rate' => 20,
            'discount' => 0, 'line_total' => 10 * $qty,
        ]);

        return $order;
    }
}
