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

    public function test_archive_export_page_is_reachable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documents.archive.index'))
            ->assertOk()
            ->assertSee('Export documents pour le comptable');
    }
}
