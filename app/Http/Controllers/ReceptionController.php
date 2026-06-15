<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Supplier;
use App\Models\Reception;
use App\Models\Product;
use App\Services\DocumentNumberService;
use App\Services\StockMovementService;
use App\Support\LineItemCalculator;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceptionController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected StockMovementService $stockMovement
    ) {}

    public function index(Request $request)
    {
        $query = Reception::with('supplier')->latest();

        $this->applyTableSearch($query, $request, ['reception_number', 'reference', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'reception_date');
        $this->applyTableFilter($query, $request, 'status', 'status');

        $receptions = $query->paginate(15)->withQueryString();

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
            'supplier_id' => 'required|exists:suppliers,id',
            'reception_date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'reference' => 'nullable|string',
            'currency' => 'required|string',
            'status' => 'required|string',
            'stock_location' => 'required|string',
            'model' => 'nullable|string',
            'items' => 'required|array',
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
                'reception_number' => DocumentNumberService::generate('bon_reception'),
                'supplier_id' => $validated['supplier_id'],
                'reception_date' => $validated['reception_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item);

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
        return view('purchases.receptions.edit', compact('reception', 'suppliers', 'products'));
    }

    public function update(Request $request, Reception $reception)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'reception_date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'reference' => 'nullable|string',
            'currency' => 'required|string',
            'status' => 'required|string',
            'stock_location' => 'required|string',
            'model' => 'nullable|string',
            'items' => 'required|array',
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
                'supplier_id' => $validated['supplier_id'],
                'reception_date' => $validated['reception_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
            ]);

            $reception->items()->delete();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item);

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

            DB::commit();
            return redirect()->route('receptions.show', $reception)->with('success', 'Bon de réception mis à jour avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    public function destroy(Reception $reception)
    {
        $reception->delete();
        return redirect()->route('receptions.index')->with('success', 'Bon de réception supprimé!');
    }
}
