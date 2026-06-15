<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\Product;
use App\Services\StockMovementService;
use App\Support\LineItemCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierCreditNoteController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected StockMovementService $stockMovement
    ) {}

    public function index(Request $request)
    {
        $query = SupplierCreditNote::with('supplier')->latest();

        $this->applyTableSearch($query, $request, ['credit_note_number', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'credit_note_date');

        $supplierCreditNotes = $query->paginate(15)->withQueryString();

        return view('purchases.supplier-credit-notes.index', compact('supplierCreditNotes'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $creditNoteNumber = 'AVOIR-FOUR N°' . str_pad(SupplierCreditNote::count() + 1, 6, '0', STR_PAD_LEFT);
        $pricesAreTtc = \App\Models\Setting::getShopifyPriceType() === 'ttc';

        return view('purchases.supplier-credit-notes.create', compact('suppliers', 'products', 'creditNoteNumber', 'pricesAreTtc'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
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
                'credit_note_number' => 'AVOIR-FOUR N°' . str_pad(SupplierCreditNote::count() + 1, 6, '0', STR_PAD_LEFT),
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
                $computed = LineItemCalculator::compute($item);

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
            return back()->withInput()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    public function show(SupplierCreditNote $supplierCreditNote)
    {
        $supplierCreditNote->load(['supplier', 'items']);
        return view('purchases.supplier-credit-notes.show', compact('supplierCreditNote'));
    }

    public function destroy(SupplierCreditNote $supplierCreditNote)
    {
        $supplierCreditNote->delete();
        return redirect()->route('supplier-credit-notes.index')->with('success', 'Avoir supprimé!');
    }
}
