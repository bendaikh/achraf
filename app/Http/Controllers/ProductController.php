<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopifyIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        // Filter by source
        if ($request->has('source')) {
            $source = $request->input('source');
            if ($source === 'shopify') {
                $query->where('source', 'shopify');
            } elseif ($source === 'manual') {
                $query->whereNull('source')->orWhere('source', '!=', 'shopify');
            }
        }

        // Search functionality
        if ($request->has('search') && $request->input('search') !== '') {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $products = $query->latest()->paginate(20);

        // Get statistics
        $totalProducts = Product::count();
        $totalShopifyProducts = Product::where('source', 'shopify')->count();
        $totalManualProducts = Product::whereNull('source')->orWhere('source', '!=', 'shopify')->count();
        $lowStockProducts = Product::where(function ($q) {
            $q->whereColumn('stock_quantity', '<=', 'minimum_alert_stock')
              ->orWhereColumn('stock_quantity', '<=', 'minimum_safety_stock');
        })->count();

        // Get Shopify integration status
        $shopifyIntegration = ShopifyIntegration::first();

        return view('products.index', compact(
            'products',
            'totalProducts',
            'totalShopifyProducts',
            'totalManualProducts',
            'lowStockProducts',
            'shopifyIntegration'
        ));
    }

    public function syncShopify()
    {
        try {
            $integration = ShopifyIntegration::first();

            if (!$integration || !$integration->enabled) {
                return redirect()
                    ->route('products.index')
                    ->with('error', 'Shopify integration is not configured or disabled.');
            }

            // Run the sync command in the background
            Artisan::call('shopify:sync-products');

            return redirect()
                ->route('products.index')
                ->with('success', 'Product synchronization started! This may take a few moments.');
        } catch (\Exception $e) {
            return redirect()
                ->route('products.index')
                ->with('error', 'Failed to sync products: ' . $e->getMessage());
        }
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ref' => 'required|string|max:255|unique:products',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cost_price_ht' => 'nullable|numeric|min:0',
            'cost_price_ttc' => 'nullable|numeric|min:0',
            'last_purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'minimum_safety_stock' => 'nullable|integer|min:0',
            'minimum_alert_stock' => 'nullable|integer|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'barcode' => 'nullable|string|max:255',
            'vat_category' => 'nullable|string|max:255',
            'element_type' => 'nullable|string|max:255',
            'tag' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'product_category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $validated['stock_quantity'] = (int) ($validated['stock_quantity'] ?? 0);

        Product::create($validated);

        return redirect()->route('products.index')
            ->with('success', 'Produit créé avec succès.');
    }

    public function show(Product $product)
    {
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ref' => 'required|string|max:255|unique:products,ref,' . $product->id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cost_price_ht' => 'nullable|numeric|min:0',
            'cost_price_ttc' => 'nullable|numeric|min:0',
            'last_purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'minimum_safety_stock' => 'nullable|integer|min:0',
            'minimum_alert_stock' => 'nullable|integer|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'barcode' => 'nullable|string|max:255',
            'vat_category' => 'nullable|string|max:255',
            'element_type' => 'nullable|string|max:255',
            'tag' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'product_category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $validated['stock_quantity'] = (int) ($validated['stock_quantity'] ?? $product->stock_quantity);

        $product->update($validated);

        return redirect()->route('products.index')
            ->with('success', 'Produit mis à jour avec succès.');
    }

    public function destroy(Product $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Produit supprimé avec succès.');
    }
}
