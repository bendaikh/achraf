<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDocumentConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_converts_separately_to_purchase_order_and_can_still_convert_to_invoice(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client A']);
        $quote = $this->makeQuote($client, 'DEV-1', [
            ['designation' => 'Produit A', 'quantity' => 10, 'unit_price' => 20, 'line_total' => 200],
        ]);

        $this->actingAs($user)->postJson(route('quotes.bulk-convert'), [
            'ids' => [$quote->id],
            'mode' => 'separate',
            'target' => 'purchase_order',
        ])->assertOk();

        $quote->refresh();
        $this->assertNotNull($quote->converted_purchase_order_id);
        $order = PurchaseOrder::query()->firstOrFail();
        $this->assertSame('DEV-1', $order->items()->first()->source_document_reference);
        $this->assertSame(10, (int) $order->items()->first()->quantity);

        $this->actingAs($user)->postJson(route('quotes.bulk-convert'), [
            'ids' => [$quote->id],
            'mode' => 'separate',
            'target' => 'purchase_order',
        ])->assertStatus(422);

        $this->actingAs($user)->postJson(route('quotes.bulk-convert'), [
            'ids' => [$quote->id],
            'mode' => 'separate',
            'target' => 'invoice',
        ])->assertOk();

        $this->assertNotNull($quote->fresh()->converted_invoice_id);
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_multiple_quotes_combine_into_one_delivery_note_with_origin_on_lines(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client B']);
        $quoteA = $this->makeQuote($client, 'DEV-A', [
            ['designation' => 'Produit A', 'quantity' => 4, 'unit_price' => 10, 'line_total' => 40],
        ]);
        $quoteB = $this->makeQuote($client, 'DEV-B', [
            ['designation' => 'Produit B', 'quantity' => 3, 'unit_price' => 10, 'line_total' => 30],
        ]);

        $this->actingAs($user)->postJson(route('quotes.bulk-convert'), [
            'ids' => [$quoteA->id, $quoteB->id],
            'mode' => 'combined',
            'target' => 'delivery_note',
        ])->assertOk();

        $this->assertSame(1, DeliveryNote::query()->count());
        $note = DeliveryNote::query()->firstOrFail();
        $this->assertCount(2, $note->items);
        $this->assertEqualsCanonicalizing(
            ['DEV-A', 'DEV-B'],
            $note->items->pluck('source_document_reference')->all()
        );
    }

    public function test_combined_conversion_requires_same_client(): void
    {
        $user = User::factory()->create();
        $clientA = Client::create(['name' => 'Client A']);
        $clientB = Client::create(['name' => 'Client B']);
        $quoteA = $this->makeQuote($clientA, 'DEV-X', [
            ['designation' => 'A', 'quantity' => 1, 'unit_price' => 10, 'line_total' => 10],
        ]);
        $quoteB = $this->makeQuote($clientB, 'DEV-Y', [
            ['designation' => 'B', 'quantity' => 1, 'unit_price' => 10, 'line_total' => 10],
        ]);

        $this->actingAs($user)->postJson(route('quotes.bulk-convert'), [
            'ids' => [$quoteA->id, $quoteB->id],
            'mode' => 'combined',
            'target' => 'invoice',
        ])->assertStatus(422);
    }

    public function test_purchase_orders_combine_into_one_invoice(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client C']);
        $orderA = $this->makePurchaseOrder($client, 'BC-A', [
            ['designation' => 'A', 'quantity' => 2, 'unit_price' => 15, 'line_total' => 30],
        ]);
        $orderB = $this->makePurchaseOrder($client, 'BC-B', [
            ['designation' => 'B', 'quantity' => 5, 'unit_price' => 8, 'line_total' => 40],
        ]);

        $this->actingAs($user)->postJson(route('purchase-orders.bulk-convert'), [
            'ids' => [$orderA->id, $orderB->id],
            'mode' => 'combined',
            'target' => 'invoice',
        ])->assertOk();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertCount(2, $invoice->items);
        $this->assertEqualsCanonicalizing(
            ['BC-A', 'BC-B'],
            $invoice->items->pluck('source_document_reference')->all()
        );
        $this->assertNotNull($orderA->fresh()->converted_invoice_id);
        $this->assertSame($invoice->id, $orderB->fresh()->converted_invoice_id);
    }

    public function test_delivery_notes_combine_into_one_invoice_keeping_bl_reference(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client D']);
        $blA = $this->makeDeliveryNote($client, 'BL-A', [
            ['designation' => 'A', 'quantity' => 4, 'unit_price' => 10, 'line_total' => 40],
        ]);
        $blB = $this->makeDeliveryNote($client, 'BL-B', [
            ['designation' => 'B', 'quantity' => 6, 'unit_price' => 10, 'line_total' => 60],
        ]);

        $this->actingAs($user)->postJson(route('delivery-notes.bulk-convert'), [
            'ids' => [$blA->id, $blB->id],
            'mode' => 'combined',
            'target' => 'invoice',
        ])->assertOk();

        $this->assertSame(1, Invoice::query()->count());
        $invoice = Invoice::query()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['BL-A', 'BL-B'],
            $invoice->items->pluck('source_document_reference')->all()
        );
        $this->assertSame(10, (int) $invoice->items->sum('quantity'));

        $this->actingAs($user)->postJson(route('delivery-notes.bulk-convert'), [
            'ids' => [$blA->id],
            'mode' => 'separate',
            'target' => 'invoice',
        ])->assertStatus(422);
    }

    /**
     * @param  list<array{designation:string, quantity:int, unit_price:float, line_total:float}>  $items
     */
    private function makeQuote(Client $client, string $number, array $items): Quote
    {
        $quote = Quote::create([
            'quote_number' => $number,
            'client_id' => $client->id,
            'quote_date' => '2026-09-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'DEPOT',
            'status' => 'accepté',
            'subtotal' => collect($items)->sum('line_total'),
            'total' => collect($items)->sum('line_total'),
        ]);
        $this->addItems($quote, $items);

        return $quote;
    }

    /**
     * @param  list<array{designation:string, quantity:int, unit_price:float, line_total:float}>  $items
     */
    private function makePurchaseOrder(Client $client, string $number, array $items): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'reference' => $number,
            'client_id' => $client->id,
            'order_date' => '2026-09-01',
            'currency' => 'dh - MAD',
            'status' => 'confirmé',
            'subtotal' => collect($items)->sum('line_total'),
            'total' => collect($items)->sum('line_total'),
        ]);
        $this->addItems($order, $items);

        return $order;
    }

    /**
     * @param  list<array{designation:string, quantity:int, unit_price:float, line_total:float}>  $items
     */
    private function makeDeliveryNote(Client $client, string $number, array $items): DeliveryNote
    {
        $note = DeliveryNote::create([
            'delivery_number' => $number,
            'client_id' => $client->id,
            'delivery_date' => '2026-09-01',
            'currency' => 'dh - MAD',
            'status' => 'livré',
            'stock_location' => 'DEPOT',
            'subtotal' => collect($items)->sum('line_total'),
            'total' => collect($items)->sum('line_total'),
        ]);
        $this->addItems($note, $items);

        return $note;
    }

    /**
     * @param  list<array{designation:string, quantity:int, unit_price:float, line_total:float}>  $items
     */
    private function addItems($document, array $items): void
    {
        foreach ($items as $item) {
            $document->items()->create([
                'designation' => $item['designation'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'line_total' => $item['line_total'],
            ]);
        }
    }
}
