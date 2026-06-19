<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\Product;
use App\Services\DocumentNumberService;
use App\Support\LineItemCalculator;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPurchaseOrderController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = SupplierPurchaseOrder::with('supplier')->latest();

        $this->applyTableSearch($query, $request, ['order_number', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'order_date');

        $orders = $query->paginate(15)->withQueryString();

        return view('purchases.supplier-purchase-orders.index', compact('orders'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $orderNumber = DocumentNumberService::preview('bc_fournisseur');
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('purchases.supplier-purchase-orders.create', compact('suppliers', 'products', 'orderNumber', 'pricesAreTtc'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'due_date' => 'nullable|date',
            'reference_invoice' => 'nullable|string',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
            'model' => 'nullable|string',
            'remarks' => 'nullable|string',
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
            $order = SupplierPurchaseOrder::create([
                'order_number' => DocumentNumberService::generate('bc_fournisseur'),
                'supplier_id' => $validated['supplier_id'],
                'order_date' => $validated['order_date'],
                'due_date' => $validated['due_date'] ?? null,
                'reference_invoice' => $validated['reference_invoice'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item, 'purchase');

                $order->items()->create([
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

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            DB::commit();
            return redirect()->route('supplier-purchase-orders.index')->with('success', 'BC fournisseur créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    public function show(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        $supplierPurchaseOrder->load(['supplier', 'items.product']);
        return view('purchases.supplier-purchase-orders.show', compact('supplierPurchaseOrder'));
    }

    public function edit(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        $supplierPurchaseOrder->load(['supplier', 'items.product']);
        $suppliers = Supplier::all();
        $products = Product::all();
        return view('purchases.supplier-purchase-orders.edit', compact('supplierPurchaseOrder', 'suppliers', 'products'));
    }

    public function update(Request $request, SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'due_date' => 'nullable|date',
            'reference_invoice' => 'nullable|string',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
            'model' => 'nullable|string',
            'remarks' => 'nullable|string',
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
            $supplierPurchaseOrder->update([
                'supplier_id' => $validated['supplier_id'],
                'order_date' => $validated['order_date'],
                'due_date' => $validated['due_date'] ?? null,
                'reference_invoice' => $validated['reference_invoice'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $supplierPurchaseOrder->items()->delete();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item, 'purchase');

                $supplierPurchaseOrder->items()->create([
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

            $supplierPurchaseOrder->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            DB::commit();
            return redirect()->route('supplier-purchase-orders.index')->with('success', 'BC fournisseur modifié avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    public function destroy(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        $supplierPurchaseOrder->delete();
        return redirect()->route('supplier-purchase-orders.index')->with('success', 'BC supprimé!');
    }
}
