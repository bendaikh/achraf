<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Services\StockMovementService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierCreditNoteController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function __construct(
        protected StockMovementService $stockMovement
    ) {}

    public function index(Request $request)
    {
        $query = SupplierCreditNote::with('supplier');

        $this->applyTableSearch($query, $request, ['credit_note_number', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'credit_note_date');
        $this->applyTableSort($query, $request, [
            'credit_note_date' => 'credit_note_date',
        ], 'credit_note_date', 'desc');

        $supplierCreditNotes = $this->paginateTable($query, $request);

        return view('purchases.supplier-credit-notes.index', compact('supplierCreditNotes'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = collect();
        $creditNoteNumber = 'AVOIR-FOUR N°'.str_pad(SupplierCreditNote::count() + 1, 6, '0', STR_PAD_LEFT);
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('purchases.supplier-credit-notes.create', compact('suppliers', 'products', 'creditNoteNumber', 'pricesAreTtc'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'credit_note_number' => 'required|string|unique:supplier_credit_notes,credit_note_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'credit_note_date' => 'required|date',
            'invoice' => 'nullable|string',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
            'model' => 'nullable|string',
            'remarks' => 'nullable|string',
            'items' => 'required|array',
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
            $creditNote = SupplierCreditNote::create([
                'credit_note_number' => $validated['credit_note_number'],
                'supplier_id' => $validated['supplier_id'],
                'credit_note_date' => $validated['credit_note_date'],
                'invoice' => $validated['invoice'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'subtotal' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item, 'purchase');

                $creditNote->items()->create([
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

            $creditNote->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            $creditNote->load('items');
            $this->stockMovement->decreaseFromItems(
                $creditNote->items,
                $validated['stock_location']
            );

            DB::commit();

            return redirect()->route('supplier-credit-notes.index')->with('success', 'Avoir fournisseur créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur: '.$e->getMessage());
        }
    }

    public function show(SupplierCreditNote $supplierCreditNote)
    {
        $supplierCreditNote->load(['supplier', 'items', 'allocations.invoice']);

        return view('purchases.supplier-credit-notes.show', compact('supplierCreditNote'));
    }

    public function edit(SupplierCreditNote $supplierCreditNote)
    {
        $supplierCreditNote->load(['supplier', 'items']);
        $suppliers = Supplier::all();
        $products = collect();
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';
        $existingItems = $supplierCreditNote->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'ref' => $item->ref,
            'designation' => $item->designation,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->display_unit_price_ttc,
            'tax_rate' => $item->tax_rate,
            'discount' => $item->discount,
            'discount_type' => $item->discount_type ?? 'fixed',
        ])->values();

        return view('purchases.supplier-credit-notes.edit', compact(
            'supplierCreditNote',
            'suppliers',
            'products',
            'pricesAreTtc',
            'existingItems'
        ));
    }

    public function update(Request $request, SupplierCreditNote $supplierCreditNote)
    {
        $validated = $request->validate([
            'credit_note_number' => 'required|string|unique:supplier_credit_notes,credit_note_number,'.$supplierCreditNote->id,
            'supplier_id' => 'required|exists:suppliers,id',
            'credit_note_date' => 'required|date',
            'invoice' => 'nullable|string',
            'currency' => 'required|string',
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
            $supplierCreditNote->update([
                'credit_note_number' => $validated['credit_note_number'],
                'supplier_id' => $validated['supplier_id'],
                'credit_note_date' => $validated['credit_note_date'],
                'invoice' => $validated['invoice'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $supplierCreditNote->items()->delete();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item, 'purchase');

                $supplierCreditNote->items()->create([
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

            $amountApplied = $supplierCreditNote->amount_applied;
            if ($subtotal + 0.0001 < $amountApplied) {
                throw new \RuntimeException(
                    'Le total de l\'avoir ('.number_format($subtotal, 2).') ne peut pas être inférieur au montant déjà affecté ('.number_format($amountApplied, 2).').'
                );
            }

            $supplierCreditNote->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            DB::commit();

            return redirect()->route('supplier-credit-notes.index')
                ->with('success', 'Avoir fournisseur modifié avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur: '.$e->getMessage());
        }
    }

    public function destroy(SupplierCreditNote $supplierCreditNote)
    {
        $supplierCreditNote->delete();

        return redirect()->route('supplier-credit-notes.index')->with('success', 'Avoir supprimé!');
    }

    public function print(SupplierCreditNote $supplierCreditNote)
    {
        $supplierCreditNote->load('supplier', 'supplierInvoice', 'items');
        $printData = $this->printViewData($supplierCreditNote, $supplierCreditNote->items);

        return view('purchases.supplier-credit-notes.print', array_merge(
            CommercialDocumentView::forSupplierCreditNote($supplierCreditNote, $printData['taxes']),
            $printData,
            compact('supplierCreditNote'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(SupplierCreditNote $supplierCreditNote)
    {
        $supplierCreditNote->load('supplier', 'supplierInvoice', 'items');
        $printData = $this->printViewData($supplierCreditNote, $supplierCreditNote->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forSupplierCreditNote($supplierCreditNote, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'avoir-fournisseur',
            $supplierCreditNote->credit_note_number
        );
    }
}
