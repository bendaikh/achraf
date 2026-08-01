<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Services\DocumentNumberService;
use App\Services\ProductPurchasePriceService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SupplierDeliveryNoteController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function __construct(
        protected ProductPurchasePriceService $purchasePriceSync,
    ) {}

    public function index(Request $request)
    {
        $query = SupplierDeliveryNote::with(['supplier', 'convertedSupplierInvoice']);

        $this->applyTableSearch($query, $request, ['delivery_number', 'reference', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'delivery_date');
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableSort($query, $request, [
            'delivery_date' => 'delivery_date',
            'expected_reception_date' => 'expected_reception_date',
        ], 'delivery_date', 'desc');

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
                'delivery_number' => $validated['delivery_number'],
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
        $validated = $this->validateSupplierDeliveryNote($request, $supplierDeliveryNote);

        DB::beginTransaction();
        try {
            $supplierDeliveryNote->update([
                'delivery_number' => $validated['delivery_number'],
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

    protected function validateSupplierDeliveryNote(Request $request, ?SupplierDeliveryNote $supplierDeliveryNote = null): array
    {
        $uniqueRule = 'unique:supplier_delivery_notes,delivery_number';
        if ($supplierDeliveryNote) {
            $uniqueRule .= ','.$supplierDeliveryNote->id;
        }

        return $request->validate([
            'delivery_number' => 'required|string|'.$uniqueRule,
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

    public function bulkConvert(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:supplier_delivery_notes,id',
            'mode' => 'required|in:separate,combined',
        ]);

        $deliveryNotes = SupplierDeliveryNote::with('items')
            ->whereIn('id', $validated['ids'])
            ->orderBy('delivery_date')
            ->get();

        $alreadyConverted = $deliveryNotes->filter(fn (SupplierDeliveryNote $note) => $note->isConverted());
        if ($alreadyConverted->isNotEmpty()) {
            return response()->json([
                'message' => 'Un ou plusieurs bons de livraison sélectionnés ont déjà été convertis en facture fournisseur.',
            ], 422);
        }

        if ($validated['mode'] === 'combined' && $deliveryNotes->pluck('supplier_id')->unique()->count() > 1) {
            return response()->json([
                'message' => 'Les bons sélectionnés doivent appartenir au même fournisseur pour créer une seule facture.',
            ], 422);
        }

        $createdInvoices = DB::transaction(function () use ($deliveryNotes, $validated) {
            if ($validated['mode'] === 'combined') {
                return collect([$this->createSupplierInvoiceFromDeliveryNotes($deliveryNotes)]);
            }

            return $deliveryNotes->map(
                fn (SupplierDeliveryNote $note) => $this->createSupplierInvoiceFromDeliveryNotes(collect([$note]))
            );
        });

        return response()->json([
            'message' => $createdInvoices->count().' facture(s) fournisseur créée(s) avec succès.',
            'redirect_url' => route('supplier-invoices.index'),
            'invoice_ids' => $createdInvoices->pluck('id')->values(),
        ]);
    }

    protected function createSupplierInvoiceFromDeliveryNotes($deliveryNotes): SupplierInvoice
    {
        /** @var SupplierDeliveryNote $first */
        $first = $deliveryNotes->first();
        $originLabels = $deliveryNotes
            ->map(fn (SupplierDeliveryNote $note) => $this->deliveryNoteOriginLabel($note))
            ->values();
        $referenceInvoice = $deliveryNotes
            ->pluck('delivery_number')
            ->implode(', ');

        $invoice = SupplierInvoice::create([
            'invoice_number' => $this->nextSupplierInvoiceNumber(),
            'supplier_id' => $first->supplier_id,
            'invoice_date' => now()->toDateString(),
            'due_date' => null,
            'reference_invoice' => $referenceInvoice,
            'currency' => $first->currency,
            'stock_location' => $first->stock_location,
            'model' => $first->model,
            'remarks' => 'Générée depuis Bon(s) de Livraison: '.$originLabels->implode(', '),
            'conditions' => null,
            'subtotal' => 0,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 0,
        ]);

        $subtotal = 0;

        foreach ($deliveryNotes as $note) {
            foreach ($note->items as $item) {
                $invoice->items()->create([
                    'product_id' => $item->product_id,
                    'ref' => $item->ref,
                    'designation' => $item->designation,
                    'description' => $item->description,
                    'source_document_reference' => $this->deliveryNoteOriginLabel($note),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'discount' => $item->discount,
                    'discount_type' => $item->discount_type,
                    'line_total' => $item->line_total,
                ]);

                $subtotal += (float) $item->line_total;
            }
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);

        $invoice->load('items');
        $this->purchasePriceSync->syncLastPurchasePrices($invoice->items);

        foreach ($deliveryNotes as $note) {
            $note->update([
                'converted_supplier_invoice_id' => $invoice->id,
                'converted_at' => now(),
            ]);
        }

        return $invoice;
    }

    protected function deliveryNoteOriginLabel(SupplierDeliveryNote $note): string
    {
        if ($note->reference) {
            return $note->delivery_number.' / '.$note->reference;
        }

        return $note->delivery_number;
    }

    protected function nextSupplierInvoiceNumber(): string
    {
        $year = date('Y');
        $next = SupplierInvoice::whereYear('created_at', $year)->count() + 1;

        do {
            $number = 'FSI-'.$year.'/'.str_pad($next, 6, '0', STR_PAD_LEFT);
            $next++;
        } while (SupplierInvoice::where('invoice_number', $number)->exists());

        return $number;
    }
}
