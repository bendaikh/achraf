<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibromartGeneratedPdfNeverServesAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_invoice_pdf_is_generated_even_when_a_scan_exists(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur Test']);
        $invoice = SupplierInvoice::create([
            'invoice_number' => 'FSI-2026-00125',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-20',
            'currency' => 'dh - MAD',
            'stock_location' => 'DEPOT',
            'subtotal' => 100,
            'total' => 120,
            'invoice_file_path' => 'managed-documents/supplier-invoices/scan-original.pdf',
        ]);
        $invoice->items()->create([
            'designation' => 'Article Libromart',
            'quantity' => 1,
            'unit_price' => 120,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 120,
        ]);

        Storage::disk('public')->put($invoice->invoice_file_path, '%PDF-1.4 SCAN-ORIGINAL-NEVER-USE');

        $response = $this->actingAs($user)->get(route('supplier-invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringNotContainsString('SCAN-ORIGINAL-NEVER-USE', $response->getContent());
        $this->assertStringContainsString('%PDF', $response->getContent());
        $this->assertStringContainsString('fsi-2026-00125', strtolower($response->headers->get('content-disposition') ?? ''));
    }

    public function test_supplier_invoice_print_renders_libromart_template_not_the_scan(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur Test']);
        $invoice = SupplierInvoice::create([
            'invoice_number' => 'FSI-2026-00126',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-20',
            'currency' => 'dh - MAD',
            'stock_location' => 'DEPOT',
            'subtotal' => 100,
            'total' => 120,
            'invoice_file_path' => 'managed-documents/supplier-invoices/scan-original.pdf',
        ]);
        $invoice->items()->create([
            'designation' => 'Article Libromart',
            'quantity' => 1,
            'unit_price' => 120,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 120,
        ]);

        Storage::disk('public')->put($invoice->invoice_file_path, '%PDF-1.4 SCAN-ORIGINAL-NEVER-USE');

        $response = $this->actingAs($user)->get(route('supplier-invoices.print', $invoice).'?no_print=1');

        $response->assertOk();
        $response->assertSee('FACTURE FOURNISSEUR', false);
        $response->assertSee('FSI-2026-00126', false);
        $response->assertDontSee('SCAN-ORIGINAL-NEVER-USE', false);
    }
}
