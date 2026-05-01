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

    public function indexEnligne(Request $request)
    {
        $query = Product::query()
            ->where('source', 'shopify')
            ->orderBy('name');

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

        $lowStockCount = Product::query()
            ->where('source', 'shopify')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('minimum_alert_stock')
                        ->whereColumn('stock_quantity', '<=', 'minimum_alert_stock');
                })->orWhere(function ($q2) {
                    $q2->whereNull('minimum_alert_stock')
                        ->whereNotNull('minimum_safety_stock')
                        ->whereColumn('stock_quantity', '<=', 'minimum_safety_stock');
                });
            })->count();

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
            'stock_quantity' => 'required|integer|min:0',
        ]);

        $product->update($validated);

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

        $lowStockCount = Product::query()
            ->where(function ($q) {
                $q->whereNull('source')
                    ->orWhere('source', '!=', 'shopify');
            })
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('minimum_alert_stock')
                        ->whereColumn('stock_quantity', '<=', 'minimum_alert_stock');
                })->orWhere(function ($q2) {
                    $q2->whereNull('minimum_alert_stock')
                        ->whereNotNull('minimum_safety_stock')
                        ->whereColumn('stock_quantity', '<=', 'minimum_safety_stock');
                });
            })->count();

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
            'stock_quantity' => 'required|integer|min:0',
        ]);

        $product->update($validated);

        return redirect()->route('stock.magasin.index')
            ->with('success', 'Stock magasin mis à jour pour « '.$product->name.' ».');
    }
}
