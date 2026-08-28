<?php

namespace App\Services;

use App\Models\Reception;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\Warehouse;
use App\Support\LineItemCalculator;
use Illuminate\Support\Facades\DB;

class PurchaseReceptionCreationService
{
    public function __construct(
        protected PurchaseReceiptService $purchaseReceipts,
        protected PurchaseStockReceiptService $purchaseStockReceipt,
        protected ProductPurchasePriceService $purchasePriceSync,
    ) {}

    /**
     * Crée un BR, applique l’entrée de stock unique et relie les documents liés.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Reception
    {
        $warehouse = $this->purchaseStockReceipt->resolveDefaultWarehouse(
            isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
            $validated['stock_location'] ?? null
        );

        return DB::transaction(function () use ($validated, $warehouse) {
            $this->assertNotOverReceiving($validated);

            $reception = Reception::create([
                'reception_number' => $validated['reception_number'],
                'supplier_id' => $validated['supplier_id'],
                'supplier_purchase_order_id' => $validated['supplier_purchase_order_id'] ?? null,
                'supplier_delivery_note_id' => $validated['supplier_delivery_note_id'] ?? null,
                'source_supplier_invoice_id' => $validated['source_supplier_invoice_id'] ?? null,
                'reception_date' => $validated['reception_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'stock_location' => $warehouse->name,
                'warehouse_id' => $warehouse->id,
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item, 'purchase');

                $reception->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'ref' => $item['ref'] ?? null,
                    'designation' => $item['designation'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $computed['discount'],
                    'discount_type' => $computed['discount_type'],
                    'line_total' => $computed['line_total'],
                ]);
                $subtotal += $computed['line_total'];
            }

            $reception->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            $this->purchasePriceSync->syncLastPurchasePrices($validated['items']);
            $reception->load('supplier');
            $this->purchaseStockReceipt->applyIfNeeded($reception, $validated['items'], $warehouse);

            return $reception;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function assertNotOverReceiving(array $validated): void
    {
        if (! empty($validated['supplier_purchase_order_id'])) {
            $this->purchaseReceipts->assertNotOverReceiving(
                SupplierPurchaseOrder::findOrFail($validated['supplier_purchase_order_id']),
                $validated['items']
            );
        }

        if (! empty($validated['supplier_delivery_note_id'])) {
            $this->purchaseReceipts->assertNotOverReceiving(
                SupplierDeliveryNote::findOrFail($validated['supplier_delivery_note_id']),
                $validated['items']
            );
        }

        if (! empty($validated['source_supplier_invoice_id'])) {
            $this->purchaseReceipts->assertNotOverReceiving(
                SupplierInvoice::findOrFail($validated['source_supplier_invoice_id']),
                $validated['items']
            );
        }
    }
}
