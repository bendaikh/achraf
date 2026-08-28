<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\ManagedDocument;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Documents\DocumentArchiveExportService;
use App\Services\Documents\DocumentAttachmentService;
use App\Services\Documents\DocumentNamingService;
use App\Services\Documents\DocumentPdfMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_and_auto_names_a_document(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $expense = Expense::create([
            'designation' => 'Filtres',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-05',
            'amount' => 100,
            'currency' => 'dh - MAD',
            'reference' => 'FSI-2026-000125',
        ]);

        $service = app(DocumentAttachmentService::class);
        $file = UploadedFile::fake()->create('scan001.pdf', 120, 'application/pdf');

        $document = $service->store('expenses-with-invoice', $expense, $file, [
            'source' => 'scan',
        ]);

        $this->assertSame('FSI-2026-000125.pdf', $document->display_name);
        $this->assertSame('FSI-2026-000125', $document->reference);
        $this->assertSame('2026-08-05', $document->document_date->format('Y-m-d'));
        $this->assertNotNull($document->currentVersion);
        Storage::disk('local')->assertExists($document->currentVersion->path);
    }

    public function test_multiple_files_for_same_reference_get_suffixes(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $expense = Expense::create([
            'designation' => 'Filtres',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-05',
            'amount' => 100,
            'currency' => 'dh - MAD',
            'reference' => 'FSI-2026-000125',
        ]);

        $service = app(DocumentAttachmentService::class);
        $first = $service->store('expenses-with-invoice', $expense, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
        $second = $service->store('expenses-with-invoice', $expense, UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'));

        $this->assertSame('FSI-2026-000125.pdf', $first->display_name);
        $this->assertSame('FSI-2026-000125_02.pdf', $second->display_name);
    }

    public function test_replace_keeps_previous_version(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $expense = Expense::create([
            'designation' => 'Filtres',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-05',
            'amount' => 100,
            'currency' => 'dh - MAD',
            'reference' => 'FSI-2026-000125',
        ]);

        $service = app(DocumentAttachmentService::class);
        $document = $service->store('expenses-with-invoice', $expense, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
        $service->replace($document, UploadedFile::fake()->create('b.pdf', 12, 'application/pdf'));

        $document->refresh()->load('versions');
        $this->assertCount(2, $document->versions);
        $this->assertSame(2, $document->currentVersion->version_number);
    }

    public function test_export_sorts_by_document_date_then_reference(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $service = app(DocumentAttachmentService::class);

        $a = Expense::create([
            'designation' => 'A',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-02',
            'amount' => 10,
            'currency' => 'dh - MAD',
            'reference' => 'FAC-008',
        ]);
        $b = Expense::create([
            'designation' => 'B',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-01',
            'amount' => 10,
            'currency' => 'dh - MAD',
            'reference' => 'FAC-002',
        ]);
        $c = Expense::create([
            'designation' => 'C',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-01',
            'amount' => 10,
            'currency' => 'dh - MAD',
            'reference' => 'FAC-001',
        ]);

        $service->store('expenses-with-invoice', $a, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
        $service->store('expenses-with-invoice', $b, UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'));
        $service->store('expenses-with-invoice', $c, UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'));

        $export = app(DocumentArchiveExportService::class);
        $inspection = $export->inspect('2026-08-01', '2026-08-31', ['expenses-with-invoice']);

        $this->assertSame(
            ['FAC-001', 'FAC-002', 'FAC-008'],
            $inspection['present']->pluck('reference')->all()
        );
        $this->assertSame(0, $inspection['missing_count']);
    }

    public function test_naming_service_builds_export_prefix(): void
    {
        $naming = new DocumentNamingService;
        $this->assertSame(
            '001_2026-08-01_FAC-001.pdf',
            $naming->exportPrefix(1, '2026-08-01', 'FAC-001', 'pdf')
        );
    }

    public function test_supplier_invoice_reference_is_used_for_naming(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $supplier = \App\Models\Supplier::create([
            'name' => 'AUTO PARTS MAROC',
            'email' => 'parts@example.com',
        ]);

        $invoice = SupplierInvoice::create([
            'invoice_number' => 'FSI-2026-000125',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-05',
            'currency' => 'MAD',
            'subtotal' => 100,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 100,
        ]);

        $document = app(DocumentAttachmentService::class)->store(
            'supplier-invoices',
            $invoice,
            UploadedFile::fake()->create('raw.jpg', 20, 'image/jpeg')
        );

        $this->assertSame('FSI-2026-000125.jpg', $document->display_name);
        $this->assertInstanceOf(ManagedDocument::class, $document);
        $this->assertTrue(class_exists(DocumentPdfMergeService::class));
    }

    public function test_legacy_file_is_ingested_into_managed_documents(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $path = UploadedFile::fake()->create('old.pdf', 15, 'application/pdf')->store('expenses/invoices', 'public');

        $expense = Expense::create([
            'designation' => 'Ancien',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-12',
            'amount' => 50,
            'currency' => 'dh - MAD',
            'reference' => 'DEP-LEGACY',
            'invoice_file_path' => $path,
        ]);

        $document = app(DocumentAttachmentService::class)->ingestLegacyIfNeeded('expenses-with-invoice', $expense);

        $this->assertNotNull($document);
        $this->assertSame('DEP-LEGACY.pdf', $document->display_name);
    }

    public function test_deactivate_hides_attachment_without_deleting_record(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $invoice = SupplierInvoice::create([
            'invoice_number' => 'FA2026-0118',
            'supplier_id' => \App\Models\Supplier::create([
                'name' => 'SODIREP',
                'email' => 'sodirep@example.com',
            ])->id,
            'invoice_date' => '2026-08-05',
            'currency' => 'MAD',
            'subtotal' => 100,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 100,
        ]);

        $service = app(DocumentAttachmentService::class);
        $first = $service->store('supplier-invoices', $invoice, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'));
        $second = $service->store('supplier-invoices', $invoice, UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'));

        $this->assertSame('FA2026-0118.pdf', $first->display_name);
        $this->assertSame('FA2026-0118_02.pdf', $second->display_name);

        $user = User::factory()->create();
        $service->deactivate($second, ['user_id' => $user->id]);

        $this->assertDatabaseHas('supplier_invoices', ['id' => $invoice->id]);
        $this->assertFalse($second->fresh()->is_active);
        $this->assertNotNull($second->fresh()->deleted_at);
        $this->assertCount(1, $service->listFor('supplier-invoices', $invoice));
        $this->assertTrue($service->listFor('supplier-invoices', $invoice)->first()->is($first->fresh()));
        $this->assertTrue($second->fresh()->versions()->where('source', 'deletion')->exists());
        Storage::disk('local')->assertExists($second->currentVersion->path);
    }
}
