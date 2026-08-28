<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShopifyIntegration;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\ProductPurchaseHistoryService;
use App\Services\StockMovementService;
use App\Support\IntelligentSearch;
use App\Support\VariantCatalogSearch;
use App\Support\StockSettings;
use App\Support\VatCategoryHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected StockMovementService $stockMovement,
        protected ProductPurchaseHistoryService $purchaseHistoryService
    ) {}

    public function index(Request $request)
    {
        $query = Product::query()->with(['primarySupplier', 'variants', 'warehouse', 'warehouseLocation', 'stocks.warehouse']);

        $this->applyProductFilters($query, $request);

        $query->withCount('variants');
        $this->applyTableSort($query, $request, [
            'created_at' => 'created_at',
            'name' => 'name',
        ], 'created_at', 'desc');

        $products = $this->paginateTable($query, $request, 20);
        $this->purchaseHistoryService->attachLastSuppliers($products->getCollection());

        $stats = $this->productStats();
        $filterOptions = $this->filterOptions();
        $shopifyIntegration = ShopifyIntegration::first();

        return view('products.index', array_merge(
            compact('products', 'shopifyIntegration'),
            $stats,
            $filterOptions
        ));
    }

    /**
     * AJAX search for commercial document Select2 pickers.
     */
    public function searchForSelect(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;

        $query = Product::query()
            ->where(function (Builder $statusQuery) {
                $statusQuery->where('status', 'Activer')->orWhereNull('status');
            });

        if ($term !== '') {
            IntelligentSearch::constrain($query, IntelligentSearch::PRODUCT_COLUMNS, $term);
        }

        $query->orderBy('name');

        $paginator = $query->with('variants')->paginate(
            $perPage,
            [
                'id',
                'name',
                'ref',
                'barcode',
                'vat_category',
                'sale_price_ht',
                'sale_price',
                'cost_price_ht',
                'cost_price_ttc',
                'last_purchase_price',
            ],
            'page',
            $page
        );

        $priceMode = $request->input('price_mode', 'sale');
        $rows = VariantCatalogSearch::expandProducts($paginator->getCollection(), $priceMode);

        return response()->json([
            'results' => $rows->map(fn (array $row) => array_merge($row, [
                'id' => $row['id'],
            ]))->values(),
            'pagination' => [
                'more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Historique fournisseurs / achats d'un produit (données Gestion des achats).
     */
    public function purchaseHistory(Product $product)
    {
        $history = $this->purchaseHistoryService->historyForProduct((int) $product->id);
        $last = $history[0] ?? null;

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'ref' => $product->ref,
            ],
            'last_supplier' => $last ? [
                'id' => $last['supplier_id'],
                'name' => $last['supplier_name'],
            ] : null,
            'history' => $history,
        ]);
    }

    public function syncShopify()
    {
        try {
            $integration = ShopifyIntegration::first();

            if (! $integration || ! $integration->enabled) {
                return redirect()
                    ->route('products.index')
                    ->with('error', 'Shopify integration is not configured or disabled.');
            }

            Artisan::call('shopify:sync-products');

            return redirect()
                ->route('products.index')
                ->with('success', 'Product synchronization started! This may take a few moments.');
        } catch (\Exception $e) {
            return redirect()
                ->route('products.index')
                ->with('error', 'Failed to sync products: '.$e->getMessage());
        }
    }

    public function create(Request $request)
    {
        $data = $this->formData();
        $kind = $request->query('kind');
        if ($kind && array_key_exists($kind, Product::ITEM_KINDS)) {
            $data['preselectedKind'] = $kind;
        }

        return view('products.create', $data);
    }

    public function store(Request $request)
    {
        $validated = $this->validateProduct($request);
        $validated = $this->normalizeByItemKind($validated);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $warehouseId = $validated['warehouse_id'] ?? null;
        $locationId = $validated['warehouse_location_id'] ?? null;
        $qty = isset($validated['stock_quantity']) ? (int) $validated['stock_quantity'] : 0;

        $product = DB::transaction(function () use ($validated, $warehouseId, $locationId, $qty) {
            $product = Product::create($validated);
            if ($product->tracksStock()) {
                $this->stockMovement->syncProductFromWarehouseAssignment(
                    $product,
                    $warehouseId ? (int) $warehouseId : null,
                    $locationId ? (int) $locationId : null,
                    $qty
                );
                $product->save();
            }

            return $product;
        });

        return redirect()->route('products.index')
            ->with('success', 'Article créé avec succès.');
    }

    public function show(Product $product)
    {
        $product->load([
            'variants.stocks.warehouse',
            'variants.stocks.location',
            'primarySupplier',
            'warehouse',
            'warehouseLocation',
            'stocks.warehouse',
            'stocks.location',
            'stocks.variant',
        ]);

        $variantStockBreakdown = app(\App\Services\StockMovementService::class)
            ->variantLocationBreakdown($product);

        return view('products.show', compact('product', 'variantStockBreakdown'));
    }

    public function edit(Product $product)
    {
        return view('products.edit', array_merge($this->formData(), compact('product')));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $this->validateProduct($request, $product);
        $validated = $this->normalizeByItemKind($validated, $product);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $warehouseId = $validated['warehouse_id'] ?? null;
        $locationId = $validated['warehouse_location_id'] ?? null;
        $qty = array_key_exists('stock_quantity', $validated) ? (int) $validated['stock_quantity'] : null;

        DB::transaction(function () use ($product, $validated, $warehouseId, $locationId, $qty) {
            $previousWarehouseId = $product->warehouse_id ? (int) $product->warehouse_id : null;
            $previousLocationId = $product->warehouse_location_id ? (int) $product->warehouse_location_id : null;

            $product->fill($validated);
            if ($product->tracksStock()) {
                $this->stockMovement->syncProductFromWarehouseAssignment(
                    $product,
                    $warehouseId ? (int) $warehouseId : null,
                    $locationId ? (int) $locationId : null,
                    $qty,
                    $previousWarehouseId,
                    $previousLocationId
                );
            }
            $product->save();
        });

        return redirect()->route('products.index')
            ->with('success', 'Article mis à jour avec succès.');
    }

    public function categories()
    {
        return view('products.categories', [
            'productTypeCategories' => implode("\n", Setting::getList('product_type_categories', ['Électronique', 'Textile', 'Alimentaire', 'Service'])),
            'serviceCategories' => implode("\n", Setting::getList('service_categories', [
                'Installation', 'Montage', 'Lavage', 'Livraison', 'Diagnostic', 'Main d\'œuvre',
            ])),
        ]);
    }

    public function updateCategories(Request $request)
    {
        $request->validate([
            'product_type_categories' => 'nullable|string',
            'service_categories' => 'nullable|string',
        ]);

        $productCats = preg_split('/\r\n|\r|\n/', (string) $request->input('product_type_categories', '')) ?: [];
        $serviceCats = preg_split('/\r\n|\r|\n/', (string) $request->input('service_categories', '')) ?: [];
        Setting::setList('product_type_categories', $productCats, 'Catégories de type produit');
        Setting::setList('service_categories', $serviceCats, 'Catégories de services');

        return redirect()->route('products.categories')
            ->with('success', 'Catégories mises à jour.');
    }

    public function destroy(Product $product)
    {
        if ($product->invoiceItems()->exists()) {
            return redirect()->route('products.index')
                ->with('error', 'Impossible de supprimer cet article : des ventes ou factures y sont liées.');
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Article supprimé avec succès.');
    }

    public function toggleStatus(Product $product)
    {
        $product->update([
            'status' => $product->isActive() ? 'Désactiver' : 'Activer',
        ]);

        $label = $product->fresh()->isActive() ? 'activé' : 'désactivé';

        return redirect()->back()
            ->with('success', 'Article '.$label.' avec succès.');
    }

    public function duplicate(Product $product)
    {
        $copy = $product->replicate([
            'external_id',
            'shopify_status',
            'shopify_synced_at',
            'shopify_image_url',
            'jumia_product_sid',
            'jumia_stock_synced_at',
        ]);

        $copy->name = $product->name.' (copie)';
        $copy->ref = $this->uniqueCopyRef($product->ref);
        $copy->source = null;
        $copy->external_id = null;
        $copy->shopify_status = null;
        $copy->shopify_synced_at = null;
        $copy->status = 'Activer';
        $copy->stock_quantity = $product->tracksStock() ? 0 : 0;
        $copy->stock_reserved = 0;
        $copy->stock_magasin = 0;
        $copy->stock_enligne = 0;
        $copy->save();

        return redirect()->route('products.edit', $copy)
            ->with('success', 'Article dupliqué. Vous pouvez ajuster les informations.');
    }

    public function archive(Product $product)
    {
        $product->update(['status' => 'Désactiver']);

        return redirect()->back()
            ->with('success', 'Article archivé (désactivé).');
    }

    public function duplicateToManual(Request $request, Product $product)
    {
        if (! $product->isShopifyProduct()) {
            return redirect()->route('products.index')
                ->with('error', 'Seuls les produits Shopify peuvent être dupliqués en produits manuels.');
        }

        $validated = $request->validate([
            'initial_stock' => 'required|integer|min:0',
        ]);

        $manualRef = $product->ref.'-m';

        $manualProduct = Product::create([
            'name' => $product->name,
            'ref' => $manualRef,
            'image' => $product->image,
            'cost_price_ht' => $product->cost_price_ht,
            'last_purchase_price' => $product->last_purchase_price,
            'sale_price' => $product->sale_price,
            'sale_price_ht' => $product->sale_price_ht,
            'product_margin' => $product->product_margin,
            'minimum_safety_stock' => $product->minimum_safety_stock,
            'minimum_alert_stock' => $product->minimum_alert_stock,
            'maximum_stock' => $product->maximum_stock,
            'stock_quantity' => $validated['initial_stock'],
            'stock_reserved' => 0,
            'stock_magasin' => $validated['initial_stock'],
            'location' => $product->location,
            'depot' => $product->depot,
            'primary_supplier_id' => $product->primary_supplier_id,
            'barcode' => $product->barcode,
            'vat_category' => $product->vat_category,
            'element_type' => $product->element_type,
            'item_kind' => $product->item_kind ?? Product::KIND_STOCKED,
            'tag' => $product->tag,
            'status' => 'Activer',
            'product_category' => $product->product_category,
            'product_type_category' => $product->product_type_category,
            'description' => $product->description,
            'source' => null,
            'external_id' => null,
            'shopify_status' => null,
            'shopify_synced_at' => null,
        ]);

        $product->load('variants');

        foreach ($product->variants as $variant) {
            ProductVariant::create([
                'product_id' => $manualProduct->id,
                'shopify_variant_id' => null,
                'title' => $variant->title,
                'sku' => $variant->sku ? $variant->sku.'-m' : null,
                'price' => $variant->price,
                'compare_at_price' => $variant->compare_at_price,
                'barcode' => $variant->barcode,
                'inventory_quantity' => 0,
                'option1' => $variant->option1,
                'option2' => $variant->option2,
                'option3' => $variant->option3,
                'weight' => $variant->weight,
                'weight_unit' => $variant->weight_unit,
                'position' => $variant->position,
            ]);
        }

        return redirect()->route('products.show', $manualProduct)
            ->with('success', 'Produit manuel créé avec succès: '.$manualProduct->name.' (Réf: '.$manualProduct->ref.')');
    }

    /**
     * @param  Builder<Product>  $query
     */
    protected function applyProductFilters($query, Request $request): void
    {
        if ($request->filled('source')) {
            $source = $request->input('source');
            if ($source === 'shopify') {
                $query->shopify();
            } elseif ($source === 'manual') {
                $query->manual();
            }
        }

        if ($request->filled('item_kind')) {
            $query->where('item_kind', $request->input('item_kind'));
        }

        if ($request->filled('stock_status')) {
            match ($request->input('stock_status')) {
                Product::STOCK_STATUS_IN_STOCK => $query->inStock(),
                Product::STOCK_STATUS_LOW => $query->lowStock(),
                Product::STOCK_STATUS_OUT => $query->outOfStock(),
                Product::STOCK_STATUS_NO_TRACKING => $query->noStockTracking(),
                default => null,
            };
        }

        $this->applyTableSearch($query, $request, \App\Support\IntelligentSearch::PRODUCT_COLUMNS);
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableFilter($query, $request, 'product_type_category', 'category');
        $this->applyTableFilter($query, $request, 'product_category', 'subcategory');
        $this->applyTableFilter($query, $request, 'service_category', 'service_category');
        if ($request->filled('supplier_id')) {
            $this->purchaseHistoryService->constrainProductsBoughtFromSupplier(
                $query,
                (int) $request->input('supplier_id')
            );
        }
        $this->applyTableFilter($query, $request, 'warehouse_id', 'warehouse_id');
        $this->applyTableFilter($query, $request, 'warehouse_location_id', 'warehouse_location_id');
        // Legacy free-text fallback
        if ($request->filled('depot') && ! $request->filled('warehouse_id')) {
            $this->applyTableFilter($query, $request, 'depot', 'depot');
        }
        if ($request->filled('location') && ! $request->filled('warehouse_location_id')) {
            $this->applyTableFilter($query, $request, 'location', 'location');
        }
        $this->applyTableFilter($query, $request, 'vat_category', 'vat_category');
        $this->applyTableDateRange($query, $request, 'created_at', 'date_from', 'date_to');

        if ($request->filled('price_min')) {
            $query->where('sale_price', '>=', (float) $request->input('price_min'));
        }
        if ($request->filled('price_max')) {
            $query->where('sale_price', '<=', (float) $request->input('price_max'));
        }
    }

    /**
     * @return array<string, int>
     */
    protected function productStats(): array
    {
        $total = Product::count();
        $stocked = Product::stocked()->count();
        $nonStocked = Product::nonStocked()->count();
        $services = Product::services()->count();
        $inStock = Product::inStock()->count();
        $lowStock = Product::lowStock()->count();
        $outOfStock = Product::outOfStock()->count();

        return [
            'stats' => [
                'total' => $total,
                'stocked' => $stocked,
                'non_stocked' => $nonStocked,
                'services' => $services,
                'in_stock' => $inStock,
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
                'shopify' => Product::shopify()->count(),
                'manual' => Product::manual()->count(),
            ],
            'tabCounts' => [
                'all' => $total,
                'stocked' => $stocked,
                'non_stocked' => $nonStocked,
                'service' => $services,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterOptions(): array
    {
        return [
            'categories' => Product::query()
                ->whereNotNull('product_type_category')
                ->where('product_type_category', '!=', '')
                ->distinct()
                ->orderBy('product_type_category')
                ->pluck('product_type_category', 'product_type_category')
                ->all(),
            'subcategories' => Product::query()
                ->whereNotNull('product_category')
                ->where('product_category', '!=', '')
                ->distinct()
                ->orderBy('product_category')
                ->pluck('product_category', 'product_category')
                ->all(),
            'serviceCategories' => Product::query()
                ->whereNotNull('service_category')
                ->where('service_category', '!=', '')
                ->distinct()
                ->orderBy('service_category')
                ->pluck('service_category', 'service_category')
                ->all(),
            'depots' => Warehouse::query()->active()->orderByDesc('is_primary')->orderBy('name')->pluck('name', 'id')->all(),
            'locations' => WarehouseLocation::query()
                ->active()
                ->when(request()->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', request()->integer('warehouse_id')))
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn (WarehouseLocation $loc) => [$loc->id => $loc->displayLabel()])
                ->all(),
            'warehouses' => Warehouse::query()->active()->with(['locations' => fn ($q) => $q->active()])->orderByDesc('is_primary')->orderBy('name')->get(),
            'vatCategories' => VatCategoryHelper::all(),
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id')->all(),
            'stockStatuses' => Product::STOCK_STATUSES,
            'itemKinds' => Product::ITEM_KINDS,
            'lowStockThreshold' => StockSettings::lowThreshold(),
        ];
    }

    protected function uniqueCopyRef(string $baseRef): string
    {
        $candidate = $baseRef.'-copy';
        $i = 2;
        while (Product::where('ref', $candidate)->exists()) {
            $candidate = $baseRef.'-copy-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(): array
    {
        $warehouses = Warehouse::query()->active()->with(['locations' => fn ($q) => $q->active()])->orderByDesc('is_primary')->orderBy('name')->get();

        return [
            'vatCategories' => VatCategoryHelper::all(),
            'productTypeCategories' => Setting::getList('product_type_categories', ['Électronique', 'Textile', 'Alimentaire', 'Service']),
            'serviceCategories' => Setting::getList('service_categories', [
                'Installation',
                'Montage',
                'Lavage',
                'Livraison',
                'Diagnostic',
                'Main d\'œuvre',
            ]),
            'billingUnits' => Product::BILLING_UNITS,
            'itemKinds' => Product::ITEM_KINDS,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'warehouses' => $warehouses,
            'lowStockThreshold' => StockSettings::lowThreshold(),
            'defaultMinimumStock' => StockSettings::minimumDefault(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateProduct(Request $request, ?Product $product = null): array
    {
        $refRule = Rule::unique('products', 'ref');
        if ($product) {
            $refRule = $refRule->ignore($product->id);
        }

        return $request->validate([
            'item_kind' => ['required', Rule::in(array_keys(Product::ITEM_KINDS))],
            'name' => 'required|string|max:255',
            'ref' => ['required', 'string', 'max:255', $refRule],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cost_price_ht' => 'nullable|numeric|min:0',
            'last_purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'sale_price_ht' => 'nullable|numeric|min:0',
            'product_margin' => 'nullable|numeric|min:0',
            'minimum_safety_stock' => 'nullable|integer|min:0',
            'minimum_alert_stock' => 'nullable|integer|min:0',
            'maximum_stock' => 'nullable|integer|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'stock_reserved' => 'nullable|integer|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'warehouse_location_id' => [
                'nullable',
                'exists:warehouse_locations,id',
                function ($attribute, $value, $fail) use ($request) {
                    if (! $value || ! $request->filled('warehouse_id')) {
                        return;
                    }
                    $belongs = WarehouseLocation::query()
                        ->where('id', $value)
                        ->where('warehouse_id', $request->input('warehouse_id'))
                        ->exists();
                    if (! $belongs) {
                        $fail('L’emplacement doit appartenir au dépôt sélectionné.');
                    }
                },
            ],
            'location' => 'nullable|string|max:255',
            'depot' => 'nullable|string|max:255',
            'primary_supplier_id' => 'nullable|exists:suppliers,id',
            'barcode' => 'nullable|string|max:255',
            'vat_category' => 'nullable|string|max:255',
            'tag' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'product_category' => 'nullable|string|max:255',
            'product_type_category' => 'nullable|string|max:255',
            'service_category' => 'nullable|string|max:255',
            'estimated_duration' => 'nullable|string|max:255',
            'billing_unit' => ['nullable', Rule::in(array_keys(Product::BILLING_UNITS))],
            'technician_required' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);
    }

    /**
     * Apply ERP rules: stock fields only for stocked products; service fields only for services.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeByItemKind(array $validated, ?Product $existing = null): array
    {
        $kind = $validated['item_kind'] ?? Product::KIND_STOCKED;
        $validated['element_type'] = Product::elementTypeForKind($kind);
        $validated['technician_required'] = (bool) ($validated['technician_required'] ?? false);

        if ($kind === Product::KIND_STOCKED) {
            $qty = (int) ($validated['stock_quantity'] ?? ($existing->stock_quantity ?? 0));
            $validated['stock_quantity'] = $qty;
            $validated['stock_reserved'] = (int) ($validated['stock_reserved'] ?? ($existing->stock_reserved ?? 0));

            if (! $existing || ! $existing->isShopifyProduct()) {
                $validated['stock_magasin'] = $qty;
            }

            // Sync free-text labels from related models when IDs are provided
            if (! empty($validated['warehouse_id'])) {
                $wh = Warehouse::find($validated['warehouse_id']);
                $validated['depot'] = $wh?->name;
            }
            if (! empty($validated['warehouse_location_id'])) {
                $loc = WarehouseLocation::find($validated['warehouse_location_id']);
                $validated['location'] = $loc?->code;
            }

            $validated['service_category'] = null;
            $validated['estimated_duration'] = null;
            $validated['billing_unit'] = null;
            $validated['technician_required'] = false;

            return $validated;
        }

        // Non-stocked product or service: never track inventory
        $validated['stock_quantity'] = 0;
        $validated['stock_reserved'] = 0;
        $validated['stock_magasin'] = 0;
        $validated['minimum_safety_stock'] = null;
        $validated['minimum_alert_stock'] = null;
        $validated['maximum_stock'] = null;
        $validated['location'] = null;
        $validated['depot'] = null;
        $validated['warehouse_id'] = null;
        $validated['warehouse_location_id'] = null;

        if ($kind === Product::KIND_SERVICE) {
            $validated['barcode'] = null;
            $validated['primary_supplier_id'] = null;
            $validated['last_purchase_price'] = null;
            $validated['product_category'] = $validated['service_category'] ?? $validated['product_category'] ?? null;
        } else {
            // Non-stocked product: hide stock but keep purchase/supplier/barcode usable
            $validated['service_category'] = null;
            $validated['estimated_duration'] = null;
            $validated['billing_unit'] = null;
            $validated['technician_required'] = false;
        }

        return $validated;
    }
}
