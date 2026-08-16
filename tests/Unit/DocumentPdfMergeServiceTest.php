<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\ManagedDocument;
use App\Services\Documents\DocumentAttachmentService;
use App\Services\Documents\DocumentPdfMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPdfMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_merges_png_attachments_into_a_single_pdf(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $service = app(DocumentAttachmentService::class);

        $first = Expense::create([
            'designation' => 'Doc 1',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-01',
            'amount' => 10,
            'currency' => 'dh - MAD',
            'reference' => 'FAC-001',
        ]);
        $second = Expense::create([
            'designation' => 'Doc 2',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-02',
            'amount' => 20,
            'currency' => 'dh - MAD',
            'reference' => 'FAC-002',
        ]);

        $docs = collect([
            $service->store('expenses-with-invoice', $first, UploadedFile::fake()->image('one.png', 200, 280)),
            $service->store('expenses-with-invoice', $second, UploadedFile::fake()->image('two.png', 200, 280)),
        ])->each(fn (ManagedDocument $doc) => $doc->load('currentVersion'));

        $pdf = app(DocumentPdfMergeService::class)->merge($docs);

        $this->assertNotSame('', $pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
