<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPurchaseOrderController extends Controller
{
    public function index()
    {
        $orders = SupplierPurchaseOrder::with('supplier')->latest()->paginate(15);
        return view('purchases.supplier-purchase-orders.index', compact('orders'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $orderNumber = 'BC-' . str_pad(SupplierPurchaseOrder::count() + 1, 6, '0', STR_PAD_LEFT);
        return view('purchases.supplier-purchase-orders.create', compact('suppliers', 'products', 'orderNumber'));
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
        ]);

        DB::beginTransaction();
        try {
            $order = SupplierPurchaseOrder::create([
                'order_number' => 'BC-' . str_pad(SupplierPurchaseOrder::count() + 1, 6, '0', STR_PAD_LEFT),
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
                $lineTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                $lineTotal += $lineTotal * (($item['tax_rate'] ?? 20) / 100);
                
                $order->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'ref' => $item['ref'] ?? null,
                    'designation' => $item['designation'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'tax_rate' => $item['tax_rate'] ?? 20,
                    'line_total' => $lineTotal,
                ]);
                $subtotal += $lineTotal;
            }

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            DB::commit();
            return redirect()->route('supplier-purchase-orders.index')->with('success', 'BC fournisseur créé avec succès!');
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
