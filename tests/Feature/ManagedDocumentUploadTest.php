<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagedDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_document_on_expense(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create();
        $expense = Expense::create([
            'designation' => 'Huile',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-10',
            'amount' => 250,
            'currency' => 'dh - MAD',
            'reference' => 'FSI-2026-000200',
        ]);

        $response = $this->actingAs($user)->post(
            route('document-files.store', ['type' => 'expenses-with-invoice', 'id' => $expense->id]),
            [
                'document_file' => UploadedFile::fake()->create('whatever.pdf', 50, 'application/pdf'),
                'source' => 'upload',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('managed_documents', [
            'section_key' => 'expenses-with-invoice',
            'documentable_id' => $expense->id,
            'display_name' => 'FSI-2026-000200.pdf',
            'reference' => 'FSI-2026-000200',
        ]);
    }

    public function test_creating_expense_with_invoice_file_uses_managed_documents(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expenses-with-invoice.store'), [
            'designation' => 'Huile moteur',
            'expense_date' => '2026-08-10',
            'amount' => 250,
            'currency' => 'dh - MAD',
            'tax_type' => 'NO TAXE',
            'reference' => 'FAC-888',
            'invoice_file' => UploadedFile::fake()->create('scan001.pdf', 40, 'application/pdf'),
        ])->assertRedirect(route('expenses-with-invoice.index'));

        $this->assertDatabaseHas('managed_documents', [
            'section_key' => 'expenses-with-invoice',
            'display_name' => 'FAC-888.pdf',
            'reference' => 'FAC-888',
        ]);
    }

    public function test_archive_export_page_is_reachable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documents.archive.index'))
            ->assertOk()
            ->assertSee('Export documents pour le comptable');
    }

    public function test_authenticated_user_can_delete_imported_document_without_deleting_expense(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create();
        $expense = Expense::create([
            'designation' => 'Huile',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-10',
            'amount' => 250,
            'currency' => 'dh - MAD',
            'reference' => 'FSI-2026-000200',
        ]);

        $this->actingAs($user)->post(
            route('document-files.store', ['type' => 'expenses-with-invoice', 'id' => $expense->id]),
            [
                'document_file' => UploadedFile::fake()->create('whatever.pdf', 50, 'application/pdf'),
                'source' => 'upload',
            ]
        )->assertRedirect();

        $document = \App\Models\ManagedDocument::query()
            ->where('documentable_id', $expense->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->delete(route('managed-documents.destroy', $document))
            ->assertRedirect();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
        $this->assertFalse($document->fresh()->is_active);
        $this->assertSame($user->id, $document->fresh()->deleted_by);
    }
}
