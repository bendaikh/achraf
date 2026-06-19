<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Services\CommercialDocumentTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialDocumentTotalsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculates_old_invoice_with_double_counted_ttc_totals(): void
    {
        Setting::set('shopify_price_type', 'ttc');

        $client = Client::create(['name' => 'Test Client']);
        $invoice = Invoice::create([
            'invoice_number' => 'FAC-TEST-001',
            'client_id' => $client->id,
            'invoice_date' => now(),
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => 840,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 840,
            'payment_status' => Invoice::PAYMENT_UNPAID,
        ]);

        InvoiceItem::create([
            'itemable_type' => Invoice::class,
            'itemable_id' => $invoice->id,
            'designation' => 'Produit test',
            'quantity' => 1,
            'unit_price' => 700,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 840,
        ]);

        $service = app(CommercialDocumentTotalsService::class);
        $changed = $service->recalculateInvoice($invoice->fresh('items'));

        $this->assertTrue($changed);
        $invoice->refresh()->load('items');

        $this->assertEquals(700.0, (float) $invoice->items->first()->line_total);
        $this->assertEquals(583.33, (float) $invoice->items->first()->unit_price);
        $this->assertEquals(700.0, (float) $invoice->total);
        $this->assertEquals(700.0, $invoice->computed_total);
    }
}
