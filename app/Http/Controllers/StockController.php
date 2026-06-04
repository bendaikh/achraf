<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Product;
use Illuminate\Http\Request;

class StockController extends Controller
{
    use FiltersIndexTables;

    private function applyStockFilters($query, Request $request, string $stockField = 'stock_quantity'): void
    {
        if ($request->filled('q') && ! $request->filled('search')) {
            $request->merge(['search' => $request->input('q')]);
        }

        $this->applyTableSearch($query, $request, ['name', 'ref', 'barcode']);

        if ($request->get('filter') === 'low') {
            $query->where(function ($q) use ($stockField) {
                $q->where(function ($q2) use ($stockField) {
                    $q2->whereNotNull('minimum_alert_stock')
                        ->whereColumn($stockField, '<=', 'minimum_alert_stock');
                })->orWhere(function ($q2) use ($stockField) {
                    $q2->whereNull('minimum_alert_stock')
                        ->whereNotNull('minimum_safety_stock')
                        ->whereColumn($stockField, '<=', 'minimum_safety_stock');
                });
            });
        }
    }

    private function lowStockCountQuery(string $stockField, ?\Closure $scope = null): int
    {
        $query = Product::query();
        if ($scope) {
            $scope($query);
        }

        return $query->where(function ($q) use ($stockField) {
            $q->where(function ($q2) use ($stockField) {
                $q2->whereNotNull('minimum_alert_stock')
                    ->whereColumn($stockField, '<=', 'minimum_alert_stock');
            })->orWhere(function ($q2) use ($stockField) {
                $q2->whereNull('minimum_alert_stock')
                    ->whereNotNull('minimum_safety_stock')
                    ->whereColumn($stockField, '<=', 'minimum_safety_stock');
            });
        })->count();
    }

    public function index(Request $request)
    {
        $query = Product::query()->orderBy('name');

        $this->applyStockFilters($query, $request);

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

    public function indexEnligne(Request $request)
    {
        $query = Product::query()
            ->where('source', 'shopify')
            ->orderBy('name');

        $this->applyStockFilters($query, $request, 'stock_enligne');

        $products = $query->paginate(20)->withQueryString();

        $lowStockCount = $this->lowStockCountQuery('stock_enligne', fn ($q) => $q->where('source', 'shopify'));

        return view('stock.enligne.index', compact('products', 'lowStockCount'));
    }

    public function editEnligne(Product $product)
    {
        if ($product->source !== 'shopify') {
            abort(404);
        }
        return view('stock.enligne.edit', compact('product'));
    }

    public function updateEnligne(Request $request, Product $product)
    {
        if ($product->source !== 'shopify') {
            abort(404);
        }

        $validated = $request->validate([
            'stock_enligne' => 'required|integer|min:0',
        ]);

        $product->update([
            'stock_enligne' => $validated['stock_enligne'],
            'stock_quantity' => $validated['stock_enligne'],
        ]);

        return redirect()->route('stock.enligne.index')
            ->with('success', 'Stock enligne mis à jour pour « '.$product->name.' ».');
    }

    public function indexMagasin(Request $request)
    {
        $query = Product::query()
            ->where(function ($q) {
                $q->whereNull('source')
                    ->orWhere('source', '!=', 'shopify');
            })
            ->orderBy('name');

        $this->applyStockFilters($query, $request, 'stock_magasin');

        $products = $query->paginate(20)->withQueryString();

        $lowStockCount = $this->lowStockCountQuery('stock_magasin', fn ($q) => $q->where(function ($sub) {
            $sub->whereNull('source')->orWhere('source', '!=', 'shopify');
        }));

        return view('stock.magasin.index', compact('products', 'lowStockCount'));
    }

    public function editMagasin(Product $product)
    {
        if ($product->source === 'shopify') {
            abort(404);
        }
        return view('stock.magasin.edit', compact('product'));
    }

    public function updateMagasin(Request $request, Product $product)
    {
        if ($product->source === 'shopify') {
            abort(404);
        }

        $validated = $request->validate([
            'stock_magasin' => 'required|integer|min:0',
        ]);

        $product->update([
            'stock_magasin' => $validated['stock_magasin'],
            'stock_quantity' => $validated['stock_magasin'],
        ]);

        return redirect()->route('stock.magasin.index')
            ->with('success', 'Stock magasin mis à jour pour « '.$product->name.' ».');
    }
}
