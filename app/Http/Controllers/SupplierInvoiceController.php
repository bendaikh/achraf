<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttachesManagedDocuments;
use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Http\Controllers\Concerns\SyncsDocumentAdjustments;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Product;
use App\Models\SupplierPurchaseOrder;
use App\Models\Warehouse;
use App\Services\ProductPurchasePriceService;
use App\Services\PurchaseReceiptService;
use App\Services\PurchaseStockReceiptService;
use App\Services\StockMovementService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SupplierInvoiceController extends Controller
{
    use AttachesManagedDocuments, FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView, SyncsDocumentAdjustments;

    public function __construct(
        protected StockMovementService $stockMovement,
        protected ProductPurchasePriceService $purchasePriceSync,
        protected PurchaseStockReceiptService $purchaseStockReceipt,
        protected PurchaseReceiptService $purchaseReceipts,
    ) {}

    public function index(Request $request)
    {
        $query = SupplierInvoice::with(['supplier', 'adjustments']);

        $this->applyTableSearch($query, $request, ['invoice_number', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'invoice_date');
        $this->applyTableSort($query, $request, [
            'invoice_date' => 'invoice_date',
            'due_date' => 'due_date',
        ], 'invoice_date', 'desc');

        $invoices = $this->paginateTable($query, $request);

        return view('purchases.supplier-invoices.index', compact('invoices'));
    }

    public function bySupplier(Supplier $supplier)
    {
        $invoices = SupplierInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('invoice_date')
            ->get(['id', 'invoice_number'])
            ->map(fn (SupplierInvoice $invoice) => [
                'id' => $invoice->id,
                'label' => $invoice->invoice_number,
            ]);

        return response()->json(['invoices' => $invoices]);
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = collect();
        $invoiceNumber = 'FSI-' . date('Y') . '/' . str_pad(SupplierInvoice::whereYear('created_at', date('Y'))->count() + 1, 6, '0', STR_PAD_LEFT);
        
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';
        $warehouses = Warehouse::query()->active()->orderByDesc('is_fulfillment_default')->orderBy('name')->get();
        $purchaseOrders = SupplierPurchaseOrder::query()->with('supplier')->orderByDesc('order_date')->limit(200)->get();

        return view('purchases.supplier-invoices.create', compact('suppliers', 'products', 'invoiceNumber', 'pricesAreTtc', 'warehouses', 'purchaseOrders'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:supplier_invoices,invoice_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'required|string',
            'stock_location' => 'nullable|string',
            'commercial_contact' => 'nullable|string',
            'model' => 'nullable|string',
            'remarks' => 'nullable|string',
            'conditions' => 'nullable|string',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'items' => 'required|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.ref' => 'nullable|string',
            'items.*.designation' => 'required|string',
            'items.*.description' => 'nullable|string',
            'items.*.source_document_reference' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percent',
        ] + $this->purchaseStockReceipt->validationRules() + $this->adjustmentValidationRules());

        $warehouse = $this->purchaseStockReceipt->resolveDefaultWarehouse(
            isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
            $validated['stock_location'] ?? null
        );

        DB::beginTransaction();
        try {
            if (! empty($validated['supplier_purchase_order_id'])) {
                $this->purchaseReceipts->assertNotOverReceiving(
                    SupplierPurchaseOrder::findOrFail($validated['supplier_purchase_order_id']),
                    $validated['items']
                );
            }

            $invoice = SupplierInvoice::create([
                'invoice_number' => $validated['invoice_number'],
                'supplier_id' => $validated['supplier_id'],
                'supplier_purchase_order_id' => $validated['supplier_purchase_order_id'] ?? null,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $warehouse->name,
                'warehouse_id' => $warehouse->id,
                'commercial_contact' => $validated['commercial_contact'] ?? null,
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
                'invoice_file_path' => null,
                'subtotal' => 0,
                'discount' => 0,
                'adjustment' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item, 'purchase');

                $invoice->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'ref' => $item['ref'] ?? null,
                    'designation' => $item['designation'],
                    'description' => $item['description'] ?? null,
                    'source_document_reference' => $item['source_document_reference'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $computed['discount'],
                    'discount_type' => $computed['discount_type'],
                    'line_total' => $computed['line_total'],
                ]);

                $subtotal += $computed['line_total'];
            }

            $this->persistDocumentTotals($invoice, $subtotal, $validated['adjustments'] ?? []);

            $invoice->load('items', 'supplier', 'adjustments');
            $this->purchasePriceSync->syncLastPurchasePrices($validated['items']);
            // Pas d'entrée de stock automatique à la création de facture.
            // Si un BR a déjà réceptionné : stock_applied_at sera posé à la conversion.
            // Sinon : action manuelle « Réceptionner / Entrer en stock » (une seule fois).

            DB::commit();
            $this->attachManagedDocument('supplier-invoices', $invoice, $request->file('invoice_file'));

            return redirect()->route('supplier-invoices.show', $invoice)->with('success', 'Facture fournisseur créée. Utilisez « Réceptionner / Entrer en stock » si aucun BR n’a encore alimenté le stock.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la création de la Facture fournisseur: ' . $e->getMessage());
        }
    }

    public function show(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load(['supplier', 'items', 'adjustments', 'payments.supplierPayment', 'creditNoteAllocations.creditNote', 'warehouse', 'stockAllocations.warehouse', 'stockAllocations.location']);
        $trace = app(\App\Services\SupplierAccountService::class)->invoiceTrace($supplierInvoice);
        $documentChain = app(\App\Services\PurchaseDocumentChainService::class)->forInvoice($supplierInvoice);
        $warehouses = Warehouse::query()->active()->with('locations')->orderByDesc('is_fulfillment_default')->orderBy('name')->get();

        return view('purchases.supplier-invoices.show', compact('supplierInvoice', 'trace', 'documentChain', 'warehouses'));
    }

    /**
     * Entrée en stock unique pour une facture créée sans BR.
     */
    public function receiveStock(Request $request, SupplierInvoice $supplierInvoice)
    {
        if ($supplierInvoice->stock_applied_at) {
            return back()->with('error', 'Le stock de cette facture a déjà été réceptionné.');
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
            'items.*.allocations' => 'nullable|array',
            'items.*.allocations.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.allocations.*.warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
            'items.*.allocations.*.quantity' => 'nullable|integer|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ] + $this->purchaseStockReceipt->validationRules());

        $warehouse = $this->purchaseStockReceipt->resolveDefaultWarehouse(
            isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : ($supplierInvoice->warehouse_id ? (int) $supplierInvoice->warehouse_id : null),
            $supplierInvoice->stock_location
        );

        DB::beginTransaction();
        try {
            if ($supplierInvoice->supplier_purchase_order_id) {
                $this->purchaseReceipts->assertNotOverReceiving(
                    SupplierPurchaseOrder::findOrFail($supplierInvoice->supplier_purchase_order_id),
                    $validated['items']
                );
            }

            $supplierInvoice->load('supplier');
            $this->purchaseStockReceipt->applyIfNeeded($supplierInvoice, $validated['items'], $warehouse);

            DB::commit();

            return redirect()->route('supplier-invoices.show', $supplierInvoice)
                ->with('success', 'Stock réceptionné ✓ — entrée unique enregistrée.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur: '.$e->getMessage());
        }
    }

    public function edit(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load('supplier', 'items', 'adjustments');
        $suppliers = Supplier::all();
        $products = collect();
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('purchases.supplier-invoices.edit', compact('supplierInvoice', 'suppliers', 'products', 'pricesAreTtc'));
    }

    public function update(Request $request, SupplierInvoice $supplierInvoice)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:supplier_invoices,invoice_number,' . $supplierInvoice->id,
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
            'commercial_contact' => 'nullable|string',
            'model' => 'nullable|string',
            'remarks' => 'nullable|string',
            'conditions' => 'nullable|string',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'items' => 'required|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.ref' => 'nullable|string',
            'items.*.designation' => 'required|string',
            'items.*.description' => 'nullable|string',
            'items.*.source_document_reference' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percent',
        ] + $this->adjustmentValidationRules());

        DB::beginTransaction();
        try {
            if ($request->hasFile('invoice_file')) {
                $this->attachManagedDocument('supplier-invoices', $supplierInvoice, $request->file('invoice_file'));
            }

            $supplierInvoice->update([
                'invoice_number' => $validated['invoice_number'],
                'supplier_id' => $validated['supplier_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'commercial_contact' => $validated['commercial_contact'] ?? null,
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
            ]);

            $supplierInvoice->items()->delete();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item, 'purchase');

                $supplierInvoice->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'ref' => $item['ref'] ?? null,
                    'designation' => $item['designation'],
                    'description' => $item['description'] ?? null,
                    'source_document_reference' => $item['source_document_reference'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $computed['discount'],
                    'discount_type' => $computed['discount_type'],
                    'line_total' => $computed['line_total'],
                ]);

                $subtotal += $computed['line_total'];
            }

            $this->persistDocumentTotals($supplierInvoice, $subtotal, $validated['adjustments'] ?? []);

            $this->purchasePriceSync->syncLastPurchasePrices($validated['items']);

            DB::commit();
            return redirect()->route('supplier-invoices.index')->with('success', 'Facture fournisseur modifiée avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la modification de la Facture fournisseur: ' . $e->getMessage());
        }
    }

    public function print(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load('supplier', 'items', 'adjustments');
        $printData = $this->printViewData($supplierInvoice, $supplierInvoice->items);

        return view('purchases.supplier-invoices.print', array_merge(
            CommercialDocumentView::forSupplierInvoice($supplierInvoice, $printData['taxes']),
            $printData,
            compact('supplierInvoice'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load('supplier', 'items', 'adjustments');
        $printData = $this->printViewData($supplierInvoice, $supplierInvoice->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forSupplierInvoice($supplierInvoice, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'facture-fournisseur',
            $supplierInvoice->invoice_number
        );
    }

    public function destroy(SupplierInvoice $supplierInvoice)
    {
        if ($supplierInvoice->invoice_file_path) {
            Storage::disk('public')->delete($supplierInvoice->invoice_file_path);
        }
        $supplierInvoice->delete();
        return redirect()->route('supplier-invoices.index')->with('success', 'Facture fournisseur supprimée avec succès!');
    }
}
