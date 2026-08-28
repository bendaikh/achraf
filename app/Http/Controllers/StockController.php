<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\StockMovementService;
use App\Support\IntelligentSearch;
use App\Support\StockSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected \App\Services\MarketplaceStockSyncService $marketplaceStockSync,
        protected \App\Services\ShopifyInventorySyncService $shopifyInventory,
        protected StockMovementService $stockMovement
    ) {}

    private function applyStockFilters($query, Request $request, string $stockField = 'stock_quantity'): void
    {
        if ($request->filled('q') && ! $request->filled('search')) {
            $request->merge(['search' => $request->input('q')]);
        }

        $this->applyTableSearch($query, $request, IntelligentSearch::PRODUCT_COLUMNS);

        if ($request->get('filter') === 'low') {
            $query->lowStock();
        }
    }

    private function lowStockCountQuery(?\Closure $scope = null): int
    {
        $query = Product::query()->lowStock();
        if ($scope) {
            $scope($query);
        }

        return $query->count();
    }

    public function index(Request $request)
    {
        return redirect()->route('stock.inventory.index');
    }

    public function inventory(Request $request)
    {
        $query = ProductStock::query()
            ->with(['product.primarySupplier', 'warehouse', 'location'])
            ->whereHas('product', fn ($q) => $q->tracksStock());

        if ($request->filled('search')) {
            $query->whereHas('product', function ($q) use ($request) {
                IntelligentSearch::constrain($q, IntelligentSearch::PRODUCT_COLUMNS, (string) $request->input('search'), false);
            });
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('warehouse_location_id')) {
            $query->where('warehouse_location_id', $request->integer('warehouse_location_id'));
        }

        if ($request->get('filter') === 'low') {
            $threshold = StockSettings::lowThreshold();
            $query->whereRaw('GREATEST(0, quantity - reserved) > 0')
                ->whereRaw('GREATEST(0, quantity - reserved) <= COALESCE(
                    (SELECT minimum_alert_stock FROM products WHERE products.id = product_stocks.product_id),
                    (SELECT minimum_safety_stock FROM products WHERE products.id = product_stocks.product_id),
                    ?
                )', [$threshold]);
        }

        $stocks = $query->orderByDesc('updated_at')->paginate($request->integer('per_page') ?: 20)->withQueryString();

        $lowStockCount = Product::lowStock()->count();
        $warehouses = Warehouse::active()->orderByDesc('is_primary')->orderBy('name')->get();
        $locations = WarehouseLocation::active()
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->orderBy('code')
            ->get();

        return view('stock.inventory', compact('stocks', 'lowStockCount', 'warehouses', 'locations'));
    }

    public function alerts(Request $request)
    {
        $status = $request->input('status', 'all'); // all | low_stock | out_of_stock

        $query = Product::query()
            ->tracksStock()
            ->with(['warehouse', 'warehouseLocation', 'primarySupplier']);

        if ($status === 'low_stock') {
            $query->lowStock();
        } elseif ($status === 'out_of_stock') {
            $query->outOfStock();
        } else {
            $available = Product::availableStockSql();
            $threshold = Product::alertThresholdSql();
            $query->whereRaw("{$available} <= {$threshold}");
        }

        $this->applyTableSearch($query, $request, IntelligentSearch::PRODUCT_COLUMNS);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        $products = $this->paginateTable($query->orderBy('name'), $request, 20);
        $lowCount = Product::lowStock()->count();
        $outCount = Product::outOfStock()->count();
        $warehouses = Warehouse::active()->orderBy('name')->pluck('name', 'id');

        return view('stock.alerts', compact('products', 'lowCount', 'outCount', 'warehouses', 'status'));
    }

    public function movements(Request $request)
    {
        $query = StockMovement::query()
            ->with(['product', 'warehouse', 'location', 'user', 'documents'])
            ->orderByDesc('moved_at')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $query->whereHas('product', function ($q) use ($request) {
                IntelligentSearch::constrain($q, IntelligentSearch::PRODUCT_COLUMNS, (string) $request->input('search'), false);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('moved_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('moved_at', '<=', $request->input('date_to'));
        }

        $movements = $query->paginate($request->integer('per_page') ?: 25)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->pluck('name', 'id');
        $types = StockMovement::TYPES;

        return view('stock.movements', compact('movements', 'warehouses', 'types'));
    }

    public function transferForm()
    {
        $warehouses = Warehouse::active()->with(['locations' => fn ($q) => $q->active()])->orderByDesc('is_primary')->orderBy('name')->get();
        $products = Product::query()->tracksStock()->orderBy('name')->limit(500)->get(['id', 'name', 'ref', 'warehouse_id', 'warehouse_location_id']);

        return view('stock.transfer', compact('warehouses', 'products'));
    }

    public function transferStore(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'from_location_id' => 'nullable|exists:warehouse_locations,id',
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'to_location_id' => 'nullable|exists:warehouse_locations,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validated['from_location_id']) {
            $fromLoc = WarehouseLocation::findOrFail($validated['from_location_id']);
            if ((int) $fromLoc->warehouse_id !== (int) $validated['from_warehouse_id']) {
                return back()->withInput()->with('error', 'L’emplacement source n’appartient pas au dépôt source.');
            }
        }
        if ($validated['to_location_id']) {
            $toLoc = WarehouseLocation::findOrFail($validated['to_location_id']);
            if ((int) $toLoc->warehouse_id !== (int) $validated['to_warehouse_id']) {
                return back()->withInput()->with('error', 'L’emplacement destination n’appartient pas au dépôt destination.');
            }
        }

        try {
            $product = Product::findOrFail($validated['product_id']);
            $this->stockMovement->transfer(
                $product,
                (int) $validated['quantity'],
                (int) $validated['from_warehouse_id'],
                $validated['from_location_id'] ? (int) $validated['from_location_id'] : null,
                (int) $validated['to_warehouse_id'],
                $validated['to_location_id'] ? (int) $validated['to_location_id'] : null,
                $validated['notes'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stock.movements.index')
            ->with('success', 'Transfert effectué : sortie + entrée enregistrées.');
    }

    public function edit(Product $product)
    {
        if (! $product->tracksStock()) {
            abort(404);
        }

        return view('stock.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        if (! $product->tracksStock()) {
            abort(404);
        }

        $validated = $request->validate([
            'stock_quantity' => 'required|integer|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($product, $validated) {
            $this->stockMovement->setQuantity(
                $product,
                (int) $validated['stock_quantity'],
                isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : $product->warehouse_id,
                isset($validated['warehouse_location_id']) ? (int) $validated['warehouse_location_id'] : $product->warehouse_location_id,
                $validated['notes'] ?? null
            );
        });

        return redirect()->route('stock.inventory.index')
            ->with('success', 'Stock mis à jour pour « '.$product->name.' ».');
    }

    public function indexEnligne(Request $request)
    {
        $query = Product::query()
            ->tracksStock()
            ->where('source', 'shopify')
            ->orderBy('name');

        $this->applyStockFilters($query, $request, 'stock_enligne');

        $products = $this->paginateTable($query, $request, 20);

        $lowStockCount = $this->lowStockCountQuery(fn ($q) => $q->where('source', 'shopify'));

        return view('stock.enligne.index', compact('products', 'lowStockCount'));
    }

    public function editEnligne(Product $product)
    {
        if ($product->source !== 'shopify' || ! $product->tracksStock()) {
            abort(404);
        }

        return view('stock.enligne.edit', compact('product'));
    }

    public function updateEnligne(Request $request, Product $product)
    {
        if ($product->source !== 'shopify' || ! $product->tracksStock()) {
            abort(404);
        }

        $validated = $request->validate([
            'stock_enligne' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($product, $validated) {
            $this->stockMovement->setQuantity(
                $product,
                (int) $validated['stock_enligne'],
                $product->warehouse_id,
                $product->warehouse_location_id,
                'Ajustement stock enligne',
                'enligne'
            );
        });

        $product->refresh();
        $this->marketplaceStockSync->pushProductStockToJumia($product);
        $this->shopifyInventory->pushProductStock($product);

        return redirect()->route('stock.enligne.index')
            ->with('success', 'Stock enligne mis à jour pour « '.$product->name.' ».');
    }

    public function indexMagasin(Request $request)
    {
        return redirect()->route('stock.inventory.index', $request->query());
    }

    public function editMagasin(Product $product)
    {
        if (! $product->tracksStock()) {
            abort(404);
        }

        $warehouses = Warehouse::active()->with(['locations' => fn ($q) => $q->active()])->orderByDesc('is_primary')->orderBy('name')->get();

        return view('stock.magasin.edit', compact('product', 'warehouses'));
    }

    public function updateMagasin(Request $request, Product $product)
    {
        if (! $product->tracksStock()) {
            abort(404);
        }

        $validated = $request->validate([
            'stock_magasin' => 'required|integer|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
        ]);

        DB::transaction(function () use ($product, $validated) {
            $warehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : $product->warehouse_id;
            $locationId = array_key_exists('warehouse_location_id', $validated)
                ? ($validated['warehouse_location_id'] ? (int) $validated['warehouse_location_id'] : null)
                : $product->warehouse_location_id;

            $this->stockMovement->syncProductFromWarehouseAssignment($product, $warehouseId, $locationId);
            $this->stockMovement->setQuantity(
                $product,
                (int) $validated['stock_magasin'],
                $warehouseId,
                $locationId,
                'Ajustement stock magasin',
                'magasin'
            );
            $product->save();
        });

        return redirect()->route('stock.inventory.index')
            ->with('success', 'Stock magasin mis à jour pour « '.$product->name.' ».');
    }
}
