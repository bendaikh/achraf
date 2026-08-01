<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Supplier;
use App\Models\Reception;
use App\Models\Product;
use App\Models\SupplierInvoice;
use App\Services\DocumentNumberService;
use App\Services\ProductPurchasePriceService;
use App\Services\StockMovementService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReceptionController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function __construct(
        protected StockMovementService $stockMovement,
        protected ProductPurchasePriceService $purchasePriceSync,
    ) {}

    public function index(Request $request)
    {
        $query = Reception::with(['supplier', 'convertedSupplierInvoice']);

        $this->applyTableSearch($query, $request, ['reception_number', 'reference', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'reception_date');
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableSort($query, $request, [
            'reception_date' => 'reception_date',
            'delivery_date' => 'delivery_date',
        ], 'reception_date', 'desc');

        $receptions = $this->paginateTable($query, $request);

        return view('purchases.receptions.index', compact('receptions'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $receptionNumber = DocumentNumberService::preview('bon_reception');
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('purchases.receptions.create', compact('suppliers', 'products', 'receptionNumber', 'pricesAreTtc'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reception_number' => 'required|string|unique:receptions,reception_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'reception_date' => 'required|date',
            'delivery_date' => 'nullable|date',
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

        DB::beginTransaction();
        try {
            $reception = Reception::create([
                'reception_number' => $validated['reception_number'],
                'supplier_id' => $validated['supplier_id'],
                'reception_date' => $validated['reception_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item, 'purchase');

                $reception->items()->create([
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

            $reception->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            $this->purchasePriceSync->syncLastPurchasePrices($validated['items']);

            $reception->load('items');
            $this->stockMovement->increaseFromItems(
                $reception->items,
                $validated['stock_location']
            );

            DB::commit();
            return redirect()->route('receptions.index')->with('success', 'Bon de réception créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    public function show(Reception $reception)
    {
        $reception->load('supplier', 'items');
        return view('purchases.receptions.show', compact('reception'));
    }

    public function edit(Reception $reception)
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $reception->load('items');
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('purchases.receptions.edit', compact('reception', 'suppliers', 'products', 'pricesAreTtc'));
    }

    public function update(Request $request, Reception $reception)
    {
        $validated = $request->validate([
            'reception_number' => 'required|string|unique:receptions,reception_number,' . $reception->id,
            'supplier_id' => 'required|exists:suppliers,id',
            'reception_date' => 'required|date',
            'delivery_date' => 'nullable|date',
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

        DB::beginTransaction();
        try {
            $reception->update([
                'reception_number' => $validated['reception_number'],
                'supplier_id' => $validated['supplier_id'],
                'reception_date' => $validated['reception_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $reception->items()->delete();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item, 'purchase');

                $reception->items()->create([
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

            $reception->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            $this->purchasePriceSync->syncLastPurchasePrices($validated['items']);

            DB::commit();
            return redirect()->route('receptions.show', $reception)->with('success', 'Bon de réception mis à jour avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    public function destroy(Reception $reception)
    {
        if ($reception->document_file_path) {
            Storage::disk('public')->delete($reception->document_file_path);
        }

        $reception->delete();
        return redirect()->route('receptions.index')->with('success', 'Bon de réception supprimé!');
    }

    public function print(Reception $reception)
    {
        if ($reception->document_file_path && Storage::disk('public')->exists($reception->document_file_path)) {
            $path = Storage::disk('public')->path($reception->document_file_path);
            $filename = $reception->reception_number.'.'.pathinfo($path, PATHINFO_EXTENSION);

            return response()->file($path, [
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        $reception->load('supplier', 'items');
        $printData = $this->printViewData($reception, $reception->items);

        return view('purchases.receptions.print', array_merge(
            CommercialDocumentView::forReception($reception, $printData['taxes']),
            $printData,
            compact('reception'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(Reception $reception)
    {
        if ($reception->document_file_path && Storage::disk('public')->exists($reception->document_file_path)) {
            $path = Storage::disk('public')->path($reception->document_file_path);
            $filename = $reception->reception_number.'.'.pathinfo($path, PATHINFO_EXTENSION);

            return response()->download($path, $filename);
        }

        $reception->load('supplier', 'items');
        $printData = $this->printViewData($reception, $reception->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forReception($reception, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'bon-reception',
            $reception->reception_number
        );
    }

    public function bulkConvert(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:receptions,id',
            'mode' => 'required|in:separate,combined',
        ]);

        $receptions = Reception::with('items')
            ->whereIn('id', $validated['ids'])
            ->orderBy('reception_date')
            ->get();

        $alreadyConverted = $receptions->filter(fn (Reception $reception) => $reception->isConverted());
        if ($alreadyConverted->isNotEmpty()) {
            return response()->json([
                'message' => 'Un ou plusieurs bons de réception sélectionnés ont déjà été convertis en facture fournisseur.',
            ], 422);
        }

        if ($validated['mode'] === 'combined' && $receptions->pluck('supplier_id')->unique()->count() > 1) {
            return response()->json([
                'message' => 'Les bons sélectionnés doivent appartenir au même fournisseur pour créer une seule facture.',
            ], 422);
        }

        $createdInvoices = DB::transaction(function () use ($receptions, $validated) {
            if ($validated['mode'] === 'combined') {
                return collect([$this->createSupplierInvoiceFromReceptions($receptions)]);
            }

            return $receptions->map(fn (Reception $reception) => $this->createSupplierInvoiceFromReceptions(collect([$reception])));
        });

        return response()->json([
            'message' => $createdInvoices->count().' facture(s) fournisseur créée(s) avec succès.',
            'redirect_url' => route('supplier-invoices.index'),
            'invoice_ids' => $createdInvoices->pluck('id')->values(),
        ]);
    }

    protected function createSupplierInvoiceFromReceptions($receptions): SupplierInvoice
    {
        /** @var Reception $first */
        $first = $receptions->first();
        $originLabels = $receptions
            ->map(fn (Reception $reception) => $this->receptionOriginLabel($reception))
            ->values();
        $referenceInvoice = $receptions
            ->pluck('reception_number')
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
            'remarks' => 'Générée depuis Bon(s) de Réception: '.$originLabels->implode(', '),
            'conditions' => null,
            'subtotal' => 0,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 0,
        ]);

        $subtotal = 0;

        foreach ($receptions as $reception) {
            foreach ($reception->items as $item) {
                $invoice->items()->create([
                    'product_id' => $item->product_id,
                    'ref' => $item->ref,
                    'designation' => $item->designation,
                    'description' => $item->description,
                    'source_document_reference' => $this->receptionOriginLabel($reception),
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

        foreach ($receptions as $reception) {
            $reception->update([
                'converted_supplier_invoice_id' => $invoice->id,
                'converted_at' => now(),
            ]);
        }

        return $invoice;
    }

    protected function receptionOriginLabel(Reception $reception): string
    {
        if ($reception->reference) {
            return $reception->reception_number.' / '.$reception->reference;
        }

        return $reception->reception_number;
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
