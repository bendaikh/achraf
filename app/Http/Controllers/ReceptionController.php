<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Reception;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceptionController extends Controller
{
    public function index()
    {
        $receptions = Reception::with('supplier')->latest()->paginate(15);
        return view('purchases.receptions.index', compact('receptions'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $receptionNumber = 'BR' . str_pad(Reception::count() + 1, 6, '0', STR_PAD_LEFT);
        return view('purchases.receptions.create', compact('suppliers', 'products', 'receptionNumber'));
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
        ]);

        DB::beginTransaction();
        try {
            $reception = Reception::create([
                'reception_number' => 'BR' . str_pad(Reception::count() + 1, 6, '0', STR_PAD_LEFT),
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
                $lineTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                $lineTotal += $lineTotal * (($item['tax_rate'] ?? 20) / 100);
                
                $reception->items()->create([
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

            $reception->update(['subtotal' => $subtotal, 'total' => $subtotal]);

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
                $lineTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                $lineTotal += $lineTotal * (($item['tax_rate'] ?? 20) / 100);
                
                $reception->items()->create([
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
