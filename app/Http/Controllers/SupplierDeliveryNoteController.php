<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierDeliveryNote;
use App\Services\DocumentNumberService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SupplierDeliveryNoteController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function index(Request $request)
    {
        $query = SupplierDeliveryNote::with('supplier')->latest();

        $this->applyTableSearch($query, $request, ['delivery_number', 'reference', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'delivery_date');
        $this->applyTableFilter($query, $request, 'status', 'status');

        $supplierDeliveryNotes = $this->paginateTable($query, $request);

        return view('purchases.supplier-delivery-notes.index', compact('supplierDeliveryNotes'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $deliveryNumber = DocumentNumberService::preview('bon_livraison_fournisseur');
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('purchases.supplier-delivery-notes.create', compact('suppliers', 'products', 'deliveryNumber', 'pricesAreTtc'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateSupplierDeliveryNote($request);

        DB::beginTransaction();
        try {
            $deliveryNote = SupplierDeliveryNote::create([
                'delivery_number' => DocumentNumberService::generate('bon_livraison_fournisseur'),
                'supplier_id' => $validated['supplier_id'],
                'delivery_date' => $validated['delivery_date'],
                'expected_reception_date' => $validated['expected_reception_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'subtotal' => 0,
                'discount' => 0,
                'adjustment' => 0,
                'total' => 0,
            ]);

            $subtotal = $this->syncItems($deliveryNote, $validated['items']);
            $deliveryNote->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            DB::commit();

            return redirect()->route('supplier-delivery-notes.index')->with('success', 'Bon de livraison créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur: '.$e->getMessage());
        }
    }

    public function show(SupplierDeliveryNote $supplierDeliveryNote)
    {
        $supplierDeliveryNote->load('supplier', 'items');

        return view('purchases.supplier-delivery-notes.show', compact('supplierDeliveryNote'));
    }

    public function edit(SupplierDeliveryNote $supplierDeliveryNote)
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $supplierDeliveryNote->load('items');
        $existingItems = $supplierDeliveryNote->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'ref' => $item->ref,
            'designation' => $item->designation,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
            'discount' => $item->discount,
            'discount_type' => $item->discount_type ?? 'fixed',
        ])->values();
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('purchases.supplier-delivery-notes.edit', compact('supplierDeliveryNote', 'suppliers', 'products', 'existingItems', 'pricesAreTtc'));
    }

    public function update(Request $request, SupplierDeliveryNote $supplierDeliveryNote)
    {
        $validated = $this->validateSupplierDeliveryNote($request);

        DB::beginTransaction();
        try {
            $supplierDeliveryNote->update([
                'supplier_id' => $validated['supplier_id'],
                'delivery_date' => $validated['delivery_date'],
                'expected_reception_date' => $validated['expected_reception_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $supplierDeliveryNote->items()->delete();
            $subtotal = $this->syncItems($supplierDeliveryNote, $validated['items']);
            $supplierDeliveryNote->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            DB::commit();

            return redirect()->route('supplier-delivery-notes.show', $supplierDeliveryNote)->with('success', 'Bon de livraison mis à jour avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur: '.$e->getMessage());
        }
    }

    public function destroy(SupplierDeliveryNote $supplierDeliveryNote)
    {
        if ($supplierDeliveryNote->document_file_path) {
            Storage::disk('public')->delete($supplierDeliveryNote->document_file_path);
        }

        $supplierDeliveryNote->delete();

        return redirect()->route('supplier-delivery-notes.index')->with('success', 'Bon de livraison supprimé!');
    }

    public function print(SupplierDeliveryNote $supplierDeliveryNote)
    {
        if ($supplierDeliveryNote->document_file_path && Storage::disk('public')->exists($supplierDeliveryNote->document_file_path)) {
            $path = Storage::disk('public')->path($supplierDeliveryNote->document_file_path);
            $filename = $supplierDeliveryNote->delivery_number.'.'.pathinfo($path, PATHINFO_EXTENSION);

            return response()->file($path, [
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        $supplierDeliveryNote->load('supplier', 'items');
        $printData = $this->printViewData($supplierDeliveryNote, $supplierDeliveryNote->items);

        return view('purchases.supplier-delivery-notes.print', array_merge(
            CommercialDocumentView::forSupplierDeliveryNote($supplierDeliveryNote, $printData['taxes']),
            $printData,
            compact('supplierDeliveryNote'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(SupplierDeliveryNote $supplierDeliveryNote)
    {
        if ($supplierDeliveryNote->document_file_path && Storage::disk('public')->exists($supplierDeliveryNote->document_file_path)) {
            $path = Storage::disk('public')->path($supplierDeliveryNote->document_file_path);
            $filename = $supplierDeliveryNote->delivery_number.'.'.pathinfo($path, PATHINFO_EXTENSION);

            return response()->download($path, $filename);
        }

        $supplierDeliveryNote->load('supplier', 'items');
        $printData = $this->printViewData($supplierDeliveryNote, $supplierDeliveryNote->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forSupplierDeliveryNote($supplierDeliveryNote, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'bon-livraison-fournisseur',
            $supplierDeliveryNote->delivery_number
        );
    }

    protected function validateSupplierDeliveryNote(Request $request): array
    {
        return $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'delivery_date' => 'required|date',
            'expected_reception_date' => 'nullable|date',
            'reference' => 'nullable|string',
            'currency' => 'required|string',
            'status' => 'required|string',
            'stock_location' => 'required|string',
            'model' => 'nullable|string',
            'remarks' => 'nullable|string',
            'items' => 'required|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.ref' => 'nullable|string',
            'items.*.designation' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percent',
        ]);
    }

    protected function syncItems(SupplierDeliveryNote $deliveryNote, array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $computed = LineItemCalculator::compute($item, 'purchase');

            $deliveryNote->items()->create([
                'product_id' => $item['product_id'] ?? null,
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

        return $subtotal;
    }
}
