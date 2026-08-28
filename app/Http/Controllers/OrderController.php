<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\DeliveryNote;
use App\Models\InvoiceItem;
use App\Models\JumiaIntegration;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\ShopifyIntegration;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\OrderPhysicalStockService;
use App\Services\OrderToInvoiceConverter;
use App\Services\ShopifyOrderCreator;
use App\Support\IntelligentSearch;
use App\Support\VariantCatalogSearch;
use App\Support\OrderSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected OrderToInvoiceConverter $orderToInvoiceConverter,
        protected ShopifyOrderCreator $shopifyOrderCreator,
        protected OrderPhysicalStockService $orderPhysicalStock,
    ) {}

    public function index(Request $request): View
    {
        $query = PosSale::with(['client']);

        // Filter by source (Shopify, Jumia, POS, etc.)
        if ($request->filled('source')) {
            $source = $request->input('source');
            if ($source === 'pos') {
                $query->where(function ($q) {
                    $q->whereNull('source')
                        ->orWhereNotIn('source', [OrderSource::SHOPIFY, OrderSource::JUMIA, OrderSource::LIBROMART]);
                });
            } else {
                $query->where('source', $source);
            }
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        // Filter by fulfillment status
        if ($request->filled('fulfillment_status')) {
            $query->where('fulfillment_status', $request->input('fulfillment_status'));
        }

        $this->applyTableSearch($query, $request, [
            'ticket_number',
            'external_id',
            'client.name',
            'fulfillments.tracking_number',
            'trackings.tracking_number',
        ]);

        // Date filters
        if ($request->filled('date_from')) {
            $query->whereDate('sold_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sold_at', '<=', $request->input('date_to'));
        }

        $this->applyTableSort($query, $request, [
            'sold_at' => 'sold_at',
        ], 'sold_at', 'desc');

        $orders = $this->paginateTable($query, $request);

        $shopifyIntegration = ShopifyIntegration::query()->first();
        $jumiaIntegration = JumiaIntegration::query()->first();

        // Calculate totals
        $totalOrders = PosSale::count();
        $totalShopifyOrders = PosSale::where('source', OrderSource::SHOPIFY)->count();
        $totalJumiaOrders = PosSale::where('source', OrderSource::JUMIA)->count();
        $totalLibromartOrders = PosSale::where('source', OrderSource::LIBROMART)->count();
        $totalPosOrders = PosSale::where(function ($q) {
            $q->whereNull('source')
                ->orWhereNotIn('source', [OrderSource::SHOPIFY, OrderSource::JUMIA, OrderSource::LIBROMART]);
        })->count();
        $totalRevenue = PosSale::where('status', PosSale::STATUS_COMPLETED)->sum('total');

        return view('sales.orders.index', compact(
            'orders',
            'totalOrders',
            'totalShopifyOrders',
            'totalJumiaOrders',
            'totalLibromartOrders',
            'totalPosOrders',
            'totalRevenue',
            'shopifyIntegration',
            'jumiaIntegration',
        ));
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('sales.orders.create', [
            'currentUser' => $user,
            'assignableUsers' => $user->isSuperAdmin()
                ? User::query()->orderBy('name')->get(['id', 'name', 'email'])
                : collect([$user]),
            'shopifyIntegration' => ShopifyIntegration::query()->first(),
            'creationToken' => (string) Str::uuid(),
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        $browse = $request->boolean('browse');

        if (! $browse && mb_strlen($term) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->with('variants')
            ->when($term !== '', fn ($query) => IntelligentSearch::constrain($query, IntelligentSearch::PRODUCT_COLUMNS, $term))
            ->where(function ($query) {
                $query->where('status', 'Activer')->orWhereNull('status');
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        $rows = VariantCatalogSearch::expandProducts($products, 'sale')->map(function (array $row) {
            $row['tax_rate'] = $this->defaultTaxRate(Product::find($row['product_id']));

            return $row;
        });

        return response()->json(['products' => $rows]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'creation_token' => 'required|uuid',
            'client_id' => 'required|exists:clients,id',
            'assigned_user_id' => [
                'required',
                Rule::exists('users', 'id'),
            ],
            'sold_at' => 'required|date',
            'status' => 'required|in:pending,completed,cancelled',
            'sales_channel' => 'required|in:shopify,manual,phone,whatsapp,store',
            'currency' => 'required|string|max:16',
            'payment_status' => 'required|in:pending,partially_paid,paid,refunded',
            'payment_method' => ['required', Rule::in(array_keys(PosSale::paymentLabels()))],
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1|max:10000',
            'items.*.discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:amount,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:255',
            'shipping_amount' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:255',
            'shipping_postal_code' => 'nullable|string|max:32',
            'shipping_country' => 'nullable|string|max:64',
            'shipping_method' => 'nullable|string|max:255',
            'delivery_note' => 'nullable|string|max:2000',
            'internal_note' => 'nullable|string|max:5000',
            'tags' => 'nullable|string|max:1000',
            'submit_action' => 'required|in:save,sync',
        ]);

        $assignedUserId = $user->isSuperAdmin()
            ? (int) $validated['assigned_user_id']
            : (int) $user->id;

        $existing = PosSale::query()->where('creation_token', $validated['creation_token'])->first();
        if ($existing) {
            return redirect()->route('orders.show', $existing)
                ->with('success', 'Cette commande avait déjà été enregistrée.');
        }

        $order = DB::transaction(function () use ($validated, $user, $assignedUserId) {
            $lineRows = [];
            $subtotalHt = 0.0;
            $taxTotal = 0.0;

            foreach ($validated['items'] as $input) {
                $product = Product::query()->findOrFail($input['product_id']);
                $variant = ! empty($input['variant_id'])
                    ? ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->findOrFail($input['variant_id'])
                    : null;

                $quantity = (int) $input['quantity'];
                $priceTtc = (float) ($variant?->price ?? $product->sale_price ?? 0);
                $taxRate = $this->defaultTaxRate($product);
                $lineDiscount = min(
                    (float) ($input['discount'] ?? 0),
                    $priceTtc * $quantity
                );
                $lineTtc = max(0, ($priceTtc * $quantity) - $lineDiscount);
                $lineHt = $lineTtc / (1 + ($taxRate / 100));
                $lineTax = $lineTtc - $lineHt;

                $subtotalHt += $lineHt;
                $taxTotal += $lineTax;
                $lineRows[] = compact(
                    'product', 'variant', 'quantity', 'taxRate',
                    'lineDiscount', 'lineTtc', 'lineHt'
                );
            }

            $beforeDiscount = round($subtotalHt + $taxTotal, 2);
            $discountValue = (float) ($validated['discount_value'] ?? 0);
            $globalDiscount = ($validated['discount_type'] ?? null) === 'percent'
                ? round($beforeDiscount * min($discountValue, 100) / 100, 2)
                : min(round($discountValue, 2), $beforeDiscount);
            $shippingAmount = round((float) ($validated['shipping_amount'] ?? 0), 2);
            $total = max(0, round($beforeDiscount - $globalDiscount + $shippingAmount, 2));

            $order = PosSale::create([
                'ticket_number' => $this->nextOrderNumber(),
                'creation_token' => $validated['creation_token'],
                'client_id' => $validated['client_id'],
                'user_id' => $assignedUserId,
                'created_by_user_id' => $user->id,
                'assigned_user_id' => $assignedUserId,
                'sold_at' => $validated['sold_at'],
                'currency' => strtoupper($validated['currency']),
                'subtotal' => round($subtotalHt, 2),
                'discount' => $globalDiscount,
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => $discountValue,
                'discount_reason' => $validated['discount_reason'] ?? null,
                'tax_total' => round($taxTotal, 2),
                'shipping_amount' => $shippingAmount,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_status'],
                'fulfillment_status' => 'unfulfilled',
                'status' => $validated['status'],
                'source' => 'libromart',
                'sales_channel' => $validated['sales_channel'],
                'sync_status' => PosSale::SYNC_NOT_SYNCED,
                'shipping_address' => $validated['shipping_address'] ?? null,
                'shipping_city' => $validated['shipping_city'] ?? null,
                'shipping_postal_code' => $validated['shipping_postal_code'] ?? null,
                'shipping_country' => $validated['shipping_country'] ?? null,
                'shipping_method' => $validated['shipping_method'] ?? null,
                'delivery_note' => $validated['delivery_note'] ?? null,
                'internal_note' => $validated['internal_note'] ?? null,
                'tags' => $this->parseTags($validated['tags'] ?? ''),
            ]);

            foreach ($lineRows as $row) {
                $order->items()->create([
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant']?->id,
                    'ref' => $row['variant']?->sku ?: $row['product']->ref,
                    'designation' => $row['product']->name,
                    'variant_title' => $row['variant']?->full_title,
                    'shopify_variant_id' => $row['variant']?->shopify_variant_id,
                    'quantity' => $row['quantity'],
                    'unit_price' => round($row['lineHt'] / $row['quantity'], 2),
                    'tax_rate' => $row['taxRate'],
                    'discount' => round($row['lineDiscount'], 2),
                    'line_total' => round($row['lineTtc'], 2),
                ]);
            }

            $order->recordActivity('created', 'Commande créée par '.$user->name, $user->id);
            $assigned = User::query()->find($assignedUserId);
            $order->recordActivity(
                'assigned',
                'Commercial attribué : '.($assigned?->name ?? 'Utilisateur #'.$assignedUserId),
                $user->id,
                ['assigned_user_id' => $assignedUserId]
            );

            return $order;
        });

        if ($validated['submit_action'] === 'sync') {
            try {
                $this->shopifyOrderCreator->sync($order, $user->id);

                return redirect()->route('orders.show', $order)
                    ->with('success', 'Commande créée dans Libromart et synchronisée avec Shopify.');
            } catch (\Throwable $e) {
                return redirect()->route('orders.show', $order)
                    ->with('error', 'Commande enregistrée dans Libromart. Échec Shopify : '.$e->getMessage());
            }
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Commande enregistrée dans Libromart.');
    }

    public function sync(Request $request, PosSale $order)
    {
        try {
            $this->shopifyOrderCreator->sync($order, $request->user()->id);

            return back()->with('success', 'Commande synchronisée avec Shopify.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Échec Shopify : '.$e->getMessage());
        }
    }

    public function show(PosSale $order): View
    {
        $order->load([
            'client',
            'user',
            'items.product',
            'invoice.payments.user',
            'invoice.payments.paymentImport',
            'fulfillments',
            'creator',
            'assignedUser',
            'activities.actor',
        ]);

        return view('sales.orders.show', compact('order'));
    }

    public function preparePhysicalStock(PosSale $order)
    {
        try {
            $result = $this->orderPhysicalStock->process($order);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $warehouseName = $result['warehouse']->name;
        if ($result['unavailable'] === []) {
            return back()->with('success', 'Stock physique déduit de '.$warehouseName.'. Sorties commande enregistrées.');
        }

        $names = collect($result['unavailable'])->map(fn ($row) => $row['name'].' ×'.$row['quantity'])->implode(', ');

        return back()->with('warning', 'STOCK PHYSIQUE NON DISPONIBLE pour : '.$names.'. Ajouté à la liste À approvisionner. Le stock Shopify n’a pas été utilisé.');
    }

    /**
     * Bulk convert orders to other document types
     */
    public function bulkConvert(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:pos_sales,id',
            'type' => 'required|in:devis,facture,bon_livraison',
        ]);

        $orderIds = $request->input('order_ids');
        $type = $request->input('type');

        $orders = PosSale::with(['client', 'items.product'])->whereIn('id', $orderIds)->get();

        $created = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($orders as $order) {
                $result = $this->convertOrder($order, $type);
                if ($result['success']) {
                    $created[] = $result['data'];
                } else {
                    $errors[] = [
                        'order_id' => $order->id,
                        'ticket' => $order->ticket_number,
                        'error' => $result['error'],
                    ];
                }
            }

            DB::commit();

            $typeLabels = [
                'devis' => 'devis',
                'facture' => 'facture(s)',
                'bon_livraison' => 'bon(s) de livraison',
            ];

            return response()->json([
                'success' => true,
                'message' => count($created).' '.$typeLabels[$type].' créé(s) avec succès.',
                'created' => $created,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la conversion: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert a single order to the specified document type
     */
    private function convertOrder(PosSale $order, string $type): array
    {
        try {
            $document = match ($type) {
                'devis' => $this->createQuote($order),
                'facture' => $this->orderToInvoiceConverter->convert($order),
                'bon_livraison' => $this->createDeliveryNote($order),
            };

            return [
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'ticket' => $order->ticket_number,
                    'document_id' => $document->id,
                    'document_number' => $this->getDocumentNumber($document, $type),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a Quote (Devis) from an order
     */
    private function createQuote(PosSale $order): Quote
    {
        $quoteNumber = DocumentNumberService::generate('devis');

        $quote = Quote::create([
            'quote_number' => $quoteNumber,
            'client_id' => $order->client_id,
            'quote_date' => now(),
            'expiry_date' => now()->addDays(30),
            'currency' => $order->currency ?? 'MAD',
            'status' => 'Brouillon',
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'adjustment' => 0,
            'total' => $order->total,
            'remarks' => 'Converti depuis la commande '.$order->ticket_number,
        ]);

        $this->copyOrderItems($order, $quote);

        return $quote;
    }

    /**
     * Create a Delivery Note (Bon de livraison) from an order
     */
    private function createDeliveryNote(PosSale $order): DeliveryNote
    {
        $deliveryNumber = DocumentNumberService::generate('bon_livraison');

        $deliveryNote = DeliveryNote::create([
            'delivery_number' => $deliveryNumber,
            'client_id' => $order->client_id,
            'delivery_date' => now(),
            'shipping_date' => now(),
            'currency' => $order->currency ?? 'MAD',
            'status' => 'En cours',
            'stock_location' => 'DEPOT',
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'adjustment' => 0,
            'total' => $order->total,
            'remarks' => 'Converti depuis la commande '.$order->ticket_number,
        ]);

        $this->copyOrderItems($order, $deliveryNote);

        return $deliveryNote;
    }

    /**
     * Copy items from order to document
     */
    private function copyOrderItems(PosSale $order, $document): void
    {
        foreach ($order->items as $item) {
            InvoiceItem::create([
                'itemable_type' => get_class($document),
                'itemable_id' => $document->id,
                'product_id' => $item->product_id,
                'ref' => $item->ref ?? $item->product?->ref,
                'designation' => $item->designation ?? $item->product?->name,
                'description' => $item->product?->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate ?? 20,
                'discount' => $item->discount ?? 0,
                'line_total' => $item->line_total,
            ]);
        }
    }

    /**
     * Get document number based on type
     */
    private function getDocumentNumber($document, string $type): string
    {
        return match ($type) {
            'devis' => $document->quote_number,
            'facture' => $document->invoice_number,
            'bon_livraison' => $document->delivery_number,
        };
    }

    private function nextOrderNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'CMD-'.$year.'-';
        $last = PosSale::query()
            ->where('ticket_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('ticket_number')
            ->value('ticket_number');
        $sequence = $last ? ((int) Str::afterLast($last, '-') + 1) : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function defaultTaxRate(Product $product): float
    {
        $vat = strtolower((string) ($product->vat_category ?? ''));
        if (str_contains($vat, '10') || str_contains($vat, 'réduit') || str_contains($vat, 'reduit')) {
            return 10.0;
        }
        if (str_contains($vat, '0') || str_contains($vat, 'exempt')) {
            return 0.0;
        }

        return 20.0;
    }

    private function parseTags(string $tags): array
    {
        return collect(preg_split('/[,;]+/', $tags) ?: [])
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
