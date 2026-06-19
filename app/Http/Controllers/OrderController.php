<?php

namespace App\Http\Controllers;

use App\Models\JumiaIntegration;
use App\Models\PosSale;
use App\Models\ShopifyIntegration;
use App\Support\OrderSource;
use App\Models\Quote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\InvoiceItem;
use App\Services\DocumentNumberService;
use App\Services\OrderToInvoiceConverter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected OrderToInvoiceConverter $orderToInvoiceConverter
    ) {}

    public function index(Request $request): View
    {
        $query = PosSale::with(['client'])
            ->orderBy('sold_at', 'desc');

        // Filter by source (Shopify, Jumia, POS, etc.)
        if ($request->filled('source')) {
            $source = $request->input('source');
            if ($source === 'pos') {
                $query->where(function ($q) {
                    $q->whereNull('source')
                        ->orWhereNotIn('source', [OrderSource::SHOPIFY, OrderSource::JUMIA]);
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

        // Search by ticket number, client name, or external ID
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('external_id', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Date filters
        if ($request->filled('date_from')) {
            $query->whereDate('sold_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sold_at', '<=', $request->input('date_to'));
        }

        // Pagination with configurable per page
        $perPage = $request->input('per_page', 20);
        $perPage = in_array($perPage, [25, 50, 100]) ? $perPage : 20;
        $orders = $query->paginate($perPage)->withQueryString();

        $shopifyIntegration = ShopifyIntegration::query()->first();
        $jumiaIntegration = JumiaIntegration::query()->first();

        // Calculate totals
        $totalOrders = PosSale::count();
        $totalShopifyOrders = PosSale::where('source', OrderSource::SHOPIFY)->count();
        $totalJumiaOrders = PosSale::where('source', OrderSource::JUMIA)->count();
        $totalPosOrders = PosSale::where(function ($q) {
            $q->whereNull('source')
                ->orWhereNotIn('source', [OrderSource::SHOPIFY, OrderSource::JUMIA]);
        })->count();
        $totalRevenue = PosSale::where('status', PosSale::STATUS_COMPLETED)->sum('total');

        return view('sales.orders.index', compact(
            'orders',
            'totalOrders',
            'totalShopifyOrders',
            'totalJumiaOrders',
            'totalPosOrders',
            'totalRevenue',
            'shopifyIntegration',
            'jumiaIntegration',
        ));
    }

    public function show(PosSale $order): View
    {
        $order->load(['client', 'user', 'items.product', 'invoice']);

        return view('sales.orders.show', compact('order'));
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
                'message' => count($created) . ' ' . $typeLabels[$type] . ' créé(s) avec succès.',
                'created' => $created,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la conversion: ' . $e->getMessage(),
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
                'bon_livraison' => $this->createPurchaseOrder($order),
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
            'remarks' => 'Converti depuis la commande ' . $order->ticket_number,
        ]);

        $this->copyOrderItems($order, $quote);

        return $quote;
    }

    /**
     * Create a Purchase Order (Bon de livraison) from an order
     */
    private function createPurchaseOrder(PosSale $order): PurchaseOrder
    {
        $reference = DocumentNumberService::generate('bon_livraison');

        $purchaseOrder = PurchaseOrder::create([
            'reference' => $reference,
            'client_id' => $order->client_id,
            'order_date' => now(),
            'expiry_date' => now()->addDays(30),
            'currency' => $order->currency ?? 'MAD',
            'status' => 'En cours',
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'adjustment' => 0,
            'total' => $order->total,
            'remarks' => 'Converti depuis la commande ' . $order->ticket_number,
        ]);

        $this->copyOrderItems($order, $purchaseOrder);

        return $purchaseOrder;
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
            'bon_livraison' => $document->reference,
        };
    }
}
