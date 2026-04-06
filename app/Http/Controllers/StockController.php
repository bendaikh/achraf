<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->orderBy('name');

        if ($request->filled('q')) {
            $search = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('ref', 'like', $search);
            });
        }

        if ($request->get('filter') === 'low') {
            $query->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('minimum_alert_stock')
                        ->whereColumn('stock_quantity', '<=', 'minimum_alert_stock');
                })->orWhere(function ($q2) {
                    $q2->whereNull('minimum_alert_stock')
                        ->whereNotNull('minimum_safety_stock')
                        ->whereColumn('stock_quantity', '<=', 'minimum_safety_stock');
                });
            });
        }

        $products = $query->paginate(20)->withQueryString();

        $lowStockCount = Product::query()->where(function ($q) {
            $q->where(function ($q2) {
                $q2->whereNotNull('minimum_alert_stock')
                    ->whereColumn('stock_quantity', '<=', 'minimum_alert_stock');
            })->orWhere(function ($q2) {
                $q2->whereNull('minimum_alert_stock')
                    ->whereNotNull('minimum_safety_stock')
                    ->whereColumn('stock_quantity', '<=', 'minimum_safety_stock');
            });
        })->count();

        return view('stock.index', compact('products', 'lowStockCount'));
    }

    public function edit(Product $product)
    {
        return view('stock.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'stock_quantity' => 'required|integer|min:0',
        ]);

        $product->update($validated);

        return redirect()->route('stock.index')
            ->with('success', 'Stock mis à jour pour « '.$product->name.' ».');
    }
}
