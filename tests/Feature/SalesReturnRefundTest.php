<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PosSale;
use App\Models\User;
use App\Services\ClientRefundService;
use App\Services\InvoiceSituationService;
use App\Services\SalesReturnService;
use App\Support\InvoiceCommercialStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReturnRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_note_updates_invoice_commercial_status(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client retour']);
        $sale = PosSale::create([
            'client_id' => $client->id,
            'ticket_number' => 'SHOP-1001',
            'source' => 'shopify',
            'external_id' => '9001',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'total' => 120,
            'status' => PosSale::STATUS_COMPLETED,
            'payment_method' => PosSale::PAYMENT_CASH,
            'sold_at' => now(),
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'FA-TEST-001',
            'client_id' => $client->id,
            'pos_sale_id' => $sale->id,
            'invoice_date' => now(),
            'currency' => 'MAD',
            'stock_location' => 'Stock en ligne',
            'subtotal' => 100,
            'total' => 120,
            'payment_status' => 'paid',
            'commercial_status' => InvoiceCommercialStatus::NORMAL,
            'source' => 'shopify',
        ]);

        InvoiceItem::create([
            'itemable_type' => Invoice::class,
            'itemable_id' => $invoice->id,
            'designation' => 'Produit test',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 120,
        ]);

        $returns = app(SalesReturnService::class);
        $returns->createCreditNote([
            'invoice' => $invoice,
            'sale' => $sale,
            'lines' => [[
                'designation' => 'Produit test',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 20,
            ]],
            'source' => 'shopify',
            'return_type' => 'total_return',
            'credit_note_date' => now()->toDateString(),
            'actor' => $user,
        ]);

        $invoice->refresh();
        $this->assertEquals(InvoiceCommercialStatus::TOTAL_RETURN, $invoice->commercial_status);

        $situation = app(InvoiceSituationService::class)->forInvoice($invoice);
        $this->assertEquals(0, $situation['net_sale']);
        $this->assertGreaterThan(0, $situation['total_credits']);
    }

    public function test_client_refund_is_separate_from_credit_note(): void
    {
        $client = Client::create(['name' => 'Client remboursement']);
        $invoice = Invoice::create([
            'invoice_number' => 'FA-TEST-002',
            'client_id' => $client->id,
            'invoice_date' => now(),
            'currency' => 'MAD',
            'stock_location' => 'Stock magasin',
            'subtotal' => 500,
            'total' => 500,
            'payment_status' => 'paid',
            'commercial_status' => InvoiceCommercialStatus::TOTAL_RETURN,
            'source' => 'shopify',
        ]);

        CreditNote::create([
            'credit_note_number' => 'AV-TEST-001',
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'credit_note_date' => now(),
            'currency' => 'MAD',
            'stock_location' => 'Stock magasin',
            'subtotal' => 500,
            'total' => 500,
        ]);

        $refunds = app(ClientRefundService::class);
        $refund = $refunds->record([
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'refund_date' => now()->toDateString(),
            'amount' => 300,
            'payment_method' => 'virement',
            'source' => 'manual',
        ]);

        $situation = app(InvoiceSituationService::class)->forInvoice($invoice->fresh(['creditNotes', 'refunds']));
        $this->assertEquals(300, $situation['total_refunded']);
        $this->assertEquals(200, $situation['remaining_to_refund']);
        $this->assertNotNull($refund->refund_number);
    }
}
