<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCreditNoteEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_is_available_and_update_persists_changes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $supplier = Supplier::create(['name' => 'FOURNISSEUR TEST']);
        $creditNote = SupplierCreditNote::create([
            'credit_note_number' => 'AVOIR-FOUR N°000001',
            'supplier_id' => $supplier->id,
            'credit_note_date' => '2026-08-02',
            'currency' => 'dh - MAD',
            'stock_location' => 'DEPOT',
            'remarks' => 'Ancienne remarque',
            'subtotal' => 100,
            'total' => 100,
        ]);

        $creditNote->items()->create([
            'ref' => 'REF-1',
            'designation' => 'Article initial',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 100,
        ]);

        $this->get(route('supplier-credit-notes.edit', $creditNote))
            ->assertOk()
            ->assertSee('Modifier un avoir fournisseur')
            ->assertSee('AVOIR-FOUR N°000001');

        $this->get(route('supplier-credit-notes.index'))
            ->assertOk()
            ->assertSee(route('supplier-credit-notes.edit', $creditNote), false);

        $response = $this->put(route('supplier-credit-notes.update', $creditNote), [
            'credit_note_number' => 'AVOIR-FOUR N°000001',
            'supplier_id' => $supplier->id,
            'credit_note_date' => '2026-09-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'DEPOT',
            'remarks' => 'Remarque mise à jour',
            'items' => [
                [
                    'ref' => 'REF-2',
                    'designation' => 'Article modifié',
                    'quantity' => 2,
                    'unit_price' => 50,
                    'tax_rate' => 20,
                    'discount' => 0,
                    'discount_type' => 'fixed',
                ],
            ],
        ]);

        $response->assertRedirect(route('supplier-credit-notes.index'));

        $creditNote->refresh();
        $this->assertSame('2026-09-01', $creditNote->credit_note_date->format('Y-m-d'));
        $this->assertSame('Remarque mise à jour', $creditNote->remarks);
        $this->assertSame(1, $creditNote->items()->count());
        $this->assertSame('Article modifié', $creditNote->items()->first()->designation);
    }
}
