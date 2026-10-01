<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesCommercialAttribution;
use App\Http\Controllers\Concerns\ExpandsCompactedFormArrays;
use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\SalesDocumentChainService;
use App\Services\SalesDocumentConversionService;
use App\Services\SalesStockIssueService;
use App\Support\CommercialDocumentView;
use App\Support\InvoiceCommercialStatus;
use App\Support\LineItemPersistence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeliveryNoteController extends Controller
{
    use AppliesCommercialAttribution, ExpandsCompactedFormArrays, FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function __construct(
        protected SalesStockIssueService $salesStockIssue,
    ) {}

    public function index(Request $request)
    {
        $query = DeliveryNote::with(['client', 'convertedInvoice']);

        $this->applyTableSearch($query, $request, ['delivery_number', 'reference', 'client.name']);
        $this->applyTableDateRange($query, $request, 'delivery_date');
        $this->applyTableSort($query, $request, [
            'delivery_date' => 'delivery_date',
            'shipping_date' => 'shipping_date',
        ], 'delivery_date', 'desc');

        if ($request->filled('commercial_status')) {
            $status = (string) $request->string('commercial_status');

            if ($status === InvoiceCommercialStatus::NORMAL) {
                $query->where(function ($q) use ($status) {
                    $q->whereDoesntHave('convertedInvoice')
                        ->orWhereHas('convertedInvoice', function ($invoiceQuery) use ($status) {
                            $invoiceQuery->where('commercial_status', $status)
                                ->orWhereNull('commercial_status');
                        });
                });
            } else {
                $query->whereHas('convertedInvoice', function ($invoiceQuery) use ($status) {
                    $invoiceQuery->where('commercial_status', $status);
                });
            }
        }

        if ($request->filled('source')) {
            $source = (string) $request->string('source');

            if ($source === 'libromart') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('convertedInvoice')
                        ->orWhereHas('convertedInvoice', function ($invoiceQuery) {
                            $invoiceQuery->where('source', 'libromart')
                                ->orWhereNull('source');
                        });
                });
            } else {
                $query->whereHas('convertedInvoice', function ($invoiceQuery) use ($source) {
                    $invoiceQuery->where('source', $source);
                });
            }
        }

        $deliveryNotes = $this->paginateTable($query, $request);

        return view('sales.delivery-notes.index', compact('deliveryNotes'));
    }

    public function create()
    {
        $products = collect();
        $deliveryNumber = DocumentNumberService::preview('bon_livraison');
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';
        $warehouses = Warehouse::query()->active()->physical()->orderByDesc('is_fulfillment_default')->orderBy('name')->get();

        return view('sales.delivery-notes.create', compact('products', 'deliveryNumber', 'pricesAreTtc', 'warehouses'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateDeliveryNote($request);

        DB::beginTransaction();
        try {
            $deliveryNote = DeliveryNote::create([
                'delivery_number' => DocumentNumberService::generate('bon_livraison'),
                'client_id' => $validated['client_id'],
                'delivery_date' => $validated['delivery_date'],
                'shipping_date' => $validated['shipping_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'matricule' => $validated['matricule'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
                'subtotal' => 0,
                'discount' => 0,
                'adjustment' => 0,
                'total' => 0,
            ] + $this->commercialCreateAttributes($request));

            $subtotal = $this->syncItems($deliveryNote, $validated['items']);
            $deliveryNote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            $this->salesStockIssue->applyForDeliveryNoteIfNeeded($deliveryNote->fresh('items'));

            DB::commit();

            return redirect()->route('delivery-notes.index')->with('success', 'Bon de livraison créé avec succès!');
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur: '.$e->getMessage());
        }
    }

    public function show(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['client', 'items.warehouse', 'items.location', 'sourceQuotes', 'sourcePurchaseOrders', 'convertedInvoice']);
        $documentChain = app(SalesDocumentChainService::class)->forDeliveryNote($deliveryNote);

        return view('sales.delivery-notes.show', compact('deliveryNote', 'documentChain'));
    }

    public function edit(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load('client', 'items');
        $products = collect();
        $existingItems = $deliveryNote->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'warehouse_id' => $item->warehouse_id,
            'warehouse_location_id' => $item->warehouse_location_id,
            'ref' => $item->ref,
            'designation' => $item->designation,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
            'discount' => $item->discount,
            'discount_type' => $item->discount_type ?? 'fixed',
        ])->values();
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';
        $warehouses = Warehouse::query()->active()->physical()->orderByDesc('is_fulfillment_default')->orderBy('name')->get();

        return view('sales.delivery-notes.edit', compact('deliveryNote', 'products', 'existingItems', 'pricesAreTtc', 'warehouses'));
    }

    public function update(Request $request, DeliveryNote $deliveryNote)
    {
        $validated = $this->validateDeliveryNote($request);

        DB::beginTransaction();
        try {
            $deliveryNote->update([
                'client_id' => $validated['client_id'],
                'delivery_date' => $validated['delivery_date'],
                'shipping_date' => $validated['shipping_date'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'matricule' => $validated['matricule'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
            ]);

            $deliveryNote->items()->delete();
            $subtotal = $this->syncItems($deliveryNote, $validated['items']);
            $deliveryNote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            $this->salesStockIssue->applyForDeliveryNoteIfNeeded($deliveryNote->fresh('items'));

            DB::commit();

            return redirect()->route('delivery-notes.show', $deliveryNote)->with('success', 'Bon de livraison mis à jour avec succès!');
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur: '.$e->getMessage());
        }
    }

    public function destroy(DeliveryNote $deliveryNote)
    {
        if ($deliveryNote->document_file_path) {
            Storage::disk('public')->delete($deliveryNote->document_file_path);
        }

        $deliveryNote->delete();

        return redirect()->route('delivery-notes.index')->with('success', 'Bon de livraison supprimé!');
    }

    public function print(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load('client', 'items');
        $printData = $this->printViewData($deliveryNote, $deliveryNote->items);

        return view('sales.delivery-notes.print', array_merge(
            CommercialDocumentView::forDeliveryNote($deliveryNote, $printData['taxes']),
            $printData,
            compact('deliveryNote'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load('client', 'items');
        $printData = $this->printViewData($deliveryNote, $deliveryNote->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forDeliveryNote($deliveryNote, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'bon-livraison',
            $deliveryNote->delivery_number
        );
    }

    protected function validateDeliveryNote(Request $request): array
    {
        $this->expandCompactedFormArrays($request);

        return $request->validate([
            'client_id' => 'required|exists:clients,id',
            'delivery_date' => 'required|date',
            'shipping_date' => 'nullable|date',
            'reference' => 'nullable|string',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
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
            'items.*.discount_type' => 'nullable|in:fixed,percent',
        ] + $this->salesStockIssue->validationRules() + $this->commercialValidationRules());
    }

    protected function syncItems(DeliveryNote $deliveryNote, array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $computed = LineItemPersistence::createInvoiceItem($deliveryNote, $item);
            $subtotal += $computed['line_total'];
        }

        return $subtotal;
    }

    public function bulkConvert(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:delivery_notes,id',
            'mode' => 'required|in:separate,combined',
            'target' => 'required|in:invoice',
        ]);

        $notes = DeliveryNote::with('items')
            ->whereIn('id', $validated['ids'])
            ->orderBy('delivery_date')
            ->get();

        $converter = app(SalesDocumentConversionService::class);

        try {
            $created = $converter->convert($notes, $validated['mode'], $validated['target']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $converter->successMessage($validated['target'], $created->count()),
            'redirect_url' => $converter->redirectUrl($validated['target']),
        ]);
    }
}
