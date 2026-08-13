<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPurchaseHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_history_endpoint_returns_rows_newest_first(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Produit X',
            'ref' => 'PX-100',
            'item_kind' => Product::KIND_STOCKED,
            'status' => 'Activer',
        ]);
        $supplier = Supplier::create([
            'name' => 'AZMI FRERES',
            'email' => 'azmi-hist@example.com',
        ]);
        $invoice = SupplierInvoice::create([
            'invoice_number' => 'FSI-2026/000099',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-11',
            'total' => 1600,
        ]);
        PurchaseItem::create([
            'purchaseable_type' => SupplierInvoice::class,
            'purchaseable_id' => $invoice->id,
            'product_id' => $product->id,
            'ref' => $product->ref,
            'designation' => $product->name,
            'quantity' => 10,
            'unit_price' => 160,
            'tax_rate' => 20,
            'line_total' => 1600,
        ]);

        $response = $this->actingAs($user)->getJson(route('products.purchase-history', $product));

        $response->assertOk()
            ->assertJsonPath('last_supplier.name', 'AZMI FRERES')
            ->assertJsonPath('history.0.document_number', 'FSI-2026/000099')
            ->assertJsonPath('history.0.quantity', 10)
            ->assertJsonPath('history.0.unit_price', 160);
    }
}
