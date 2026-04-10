<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PurchaseOrder;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $purchaseOrders = PurchaseOrder::with('client')->latest()->paginate(15);
        return view('sales.purchase-orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        $clients = Client::all();
        $products = Product::all();
        $reference = 'REF-' . date('Y') . '-' . str_pad(PurchaseOrder::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT);
        
        return view('sales.purchase-orders.create', compact('clients', 'products', 'reference'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'order_date' => 'required|date',
            'expiry_date' => 'nullable|date',
            'currency' => 'required|string',
            'status' => 'required|string',
            'model' => 'nullable|string',
            'matricule' => 'nullable|string',
            'remarks' => 'nullable|string',
            'conditions' => 'nullable|string',
            'items' => 'required|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.ref' => 'nullable|string',
            'items.*.designation' => 'required|string',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $purchaseOrder = PurchaseOrder::create([
                'reference' => 'REF-' . date('Y') . '-' . str_pad(PurchaseOrder::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'client_id' => $validated['client_id'],
                'order_date' => $validated['order_date'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'model' => $validated['model'] ?? null,
                'matricule' => $validated['matricule'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
                'subtotal' => 0,
                'discount' => 0,
                'adjustment' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $lineTotal -= $discount;
                $lineTotal += $lineTotal * ($item['tax_rate'] / 100);
                
                $purchaseOrder->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'ref' => $item['ref'] ?? null,
                    'designation' => $item['designation'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $purchaseOrder->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();
            return redirect()->route('purchase-orders.index')->with('success', 'Bon de commande créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la création du bon de commande: ' . $e->getMessage());
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('client', 'items');
        return view('sales.purchase-orders.show', compact('purchaseOrder'));
    }

    public function print(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('client', 'items');
        return view('sales.purchase-orders.print', compact('purchaseOrder'));
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->delete();
        return redirect()->route('purchase-orders.index')->with('success', 'Bon de commande supprimé avec succès!');
    }
}
