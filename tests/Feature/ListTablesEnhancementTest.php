<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListTablesEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_notes_default_to_newest_date_first(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client Test', 'email' => 'client-'.uniqid().'@test.com']);

        DeliveryNote::create($this->deliveryNotePayload($client->id, 'BL-OLD', '2026-08-05'));
        DeliveryNote::create($this->deliveryNotePayload($client->id, 'BL-NEW', '2026-08-08'));
        DeliveryNote::create($this->deliveryNotePayload($client->id, 'BL-MID', '2026-08-06'));

        $this->actingAs($user)
            ->get(route('delivery-notes.index'))
            ->assertOk()
            ->assertSeeInOrder(['08/08/2026', '06/08/2026', '05/08/2026']);
    }

    public function test_delivery_notes_can_be_sorted_oldest_date_first(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client Test', 'email' => 'client-'.uniqid().'@test.com']);

        DeliveryNote::create($this->deliveryNotePayload($client->id, 'BL-OLD', '2026-08-05'));
        DeliveryNote::create($this->deliveryNotePayload($client->id, 'BL-NEW', '2026-08-08'));
        DeliveryNote::create($this->deliveryNotePayload($client->id, 'BL-MID', '2026-08-06'));

        $this->actingAs($user)
            ->get(route('delivery-notes.index', [
                'sort' => 'delivery_date',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSeeInOrder(['05/08/2026', '06/08/2026', '08/08/2026']);
    }

    public function test_invoice_list_exposes_date_sort_headers(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sort=invoice_date', $html);
        $this->assertStringContainsString('sort=due_date', $html);
    }

    public function test_bulk_destroy_deletes_allowed_receptions_and_blocks_converted_ones(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create([
            'name' => 'Fournisseur Test',
            'email' => 'fournisseur@test.com',
        ]);
        $invoice = SupplierInvoice::create([
            'invoice_number' => 'FF-001',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'DEPOT',
            'subtotal' => 0,
            'total' => 0,
        ]);

        $free = Reception::create($this->receptionPayload($supplier->id, 'BR-FREE', '2026-08-07'));
        $converted = Reception::create(array_merge(
            $this->receptionPayload($supplier->id, 'BR-CONV', '2026-08-08'),
            [
                'converted_supplier_invoice_id' => $invoice->id,
                'converted_at' => now(),
            ]
        ));

        $response = $this->actingAs($user)->postJson(route('table.bulk-destroy'), [
            'type' => 'receptions',
            'ids' => [$free->id, $converted->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('blocked.0.label', 'BR-CONV');

        $this->assertDatabaseMissing('receptions', ['id' => $free->id]);
        $this->assertDatabaseHas('receptions', ['id' => $converted->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryNotePayload(int $clientId, string $number, string $date): array
    {
        return [
            'delivery_number' => $number,
            'client_id' => $clientId,
            'delivery_date' => $date,
            'currency' => 'dh - MAD',
            'status' => 'brouillon',
            'stock_location' => 'DEPOT',
            'subtotal' => 0,
            'total' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receptionPayload(int $supplierId, string $number, string $date): array
    {
        return [
            'reception_number' => $number,
            'supplier_id' => $supplierId,
            'reception_date' => $date,
            'currency' => 'dh - MAD',
            'status' => 'brouillon',
            'stock_location' => 'DEPOT',
            'subtotal' => 0,
            'total' => 0,
        ];
    }
}
