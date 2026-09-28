<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\LocationStockReportService;
use App\Services\ProductPurchasePriceService;
use App\Services\StockMovementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocationStockController extends Controller
{
    public function __construct(
        protected LocationStockReportService $reports,
        protected StockMovementService $stockMovement,
        protected ProductPurchasePriceService $purchasePrices,
    ) {}

    public function index()
    {
        $warehouses = Warehouse::query()->active()->orderByDesc('is_fulfillment_default')->orderBy('name')->get();

        $stats = [];
        foreach ($warehouses as $warehouse) {
            $report = $this->reports->report($warehouse);
            $stats[$warehouse->id] = $report;
        }

        return view('stock.locations.index', compact('warehouses', 'stats'));
    }

    public function show(Request $request, Warehouse $warehouse)
    {
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->input('as_of'))
            : now();
        $locationId = $request->filled('warehouse_location_id')
            ? $request->integer('warehouse_location_id')
            : null;

        $report = $this->reports->report($warehouse, $asOf, $locationId);
        $locations = $warehouse->locations()->orderBy('code')->get();

        return view('stock.locations.show', [
            'warehouse' => $warehouse,
            'report' => $report,
            'asOf' => $asOf,
            'locations' => $locations,
            'selectedLocationId' => $locationId,
        ]);
    }

    /**
     * Saisie / mise à jour du Prix dernier achat TTC depuis Stock par emplacement.
     * Écrit uniquement products.last_purchase_price (même source que la fiche produit).
     */
    public function updatePurchasePrice(Request $request, Warehouse $warehouse, Product $product)
    {
        $validated = $request->validate([
            'last_purchase_price' => 'required|numeric|min:0',
            'as_of' => 'nullable|date',
            'warehouse_location_id' => 'nullable|integer|exists:warehouse_locations,id',
        ]);

        if (! $product->tracksStock()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit ne gère pas de stock physique.',
            ], 422);
        }

        $cost = $this->purchasePrices->setManualLastPurchasePrice(
            $product,
            (float) $validated['last_purchase_price'],
            $request->user()
        );

        $asOf = ! empty($validated['as_of'])
            ? Carbon::parse($validated['as_of'])
            : now();
        $locationId = ! empty($validated['warehouse_location_id'])
            ? (int) $validated['warehouse_location_id']
            : null;

        $report = $this->reports->report($warehouse, $asOf, $locationId);
        $productRows = $report['rows']
            ->filter(fn ($row) => (int) $row->product_id === (int) $product->id)
            ->map(fn ($row) => [
                'location' => $row->location,
                'quantity' => $row->quantity,
                'price_ht' => $row->price_ht,
                'price_ttc' => $row->price_ttc,
                'value_ht' => $row->value_ht,
                'value_ttc' => $row->value_ttc,
                'has_purchase_cost' => $row->has_purchase_cost,
                'price_source' => $row->price_source,
            ])
            ->values()
            ->all();

        $product->loadMissing('lastPurchasePriceUpdatedBy');

        return response()->json([
            'success' => true,
            'message' => 'Prix d’achat enregistré sur la fiche produit.',
            'product_id' => (int) $product->id,
            'price_ht' => $cost['ht'],
            'price_ttc' => $cost['ttc'],
            'has_purchase_cost' => $cost['has_cost'],
            'price_source' => $cost['source'],
            'updated_at' => optional($product->last_purchase_price_updated_at)?->format('d/m/Y H:i'),
            'updated_by' => $product->lastPurchasePriceUpdatedBy?->name,
            'rows' => $productRows,
            'totals' => [
                'references' => $report['references'],
                'quantity' => $report['quantity'],
                'value_ht' => $report['value_ht'],
                'value_ttc' => $report['value_ttc'],
                'value_vat' => $report['value_vat'],
            ],
        ]);
    }

    public function export(Request $request, Warehouse $warehouse, string $format)
    {
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->input('as_of'))
            : now();
        $locationId = $request->filled('warehouse_location_id')
            ? $request->integer('warehouse_location_id')
            : null;
        $report = $this->reports->report($warehouse, $asOf, $locationId);

        return match ($format) {
            'excel', 'xlsx' => $this->exportExcel($report),
            'pdf' => $this->exportPdf($report),
            default => abort(404),
        };
    }

    public function countForm(Warehouse $warehouse)
    {
        $slots = ProductStock::query()
            ->with(['product', 'location', 'variant'])
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->whereHas('product', fn ($q) => $q->tracksStock())
            ->orderBy('product_id')
            ->orderBy('warehouse_location_id')
            ->get();

        return view('stock.locations.count', compact('warehouse', 'slots'));
    }

    public function countStore(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'counts' => 'required|array',
            'counts.*.product_id' => 'required|exists:products,id',
            'counts.*.product_stock_id' => 'nullable|integer|exists:product_stocks,id',
            'counts.*.warehouse_location_id' => 'nullable|integer|exists:warehouse_locations,id',
            'counts.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            // 0 is a valid counted quantity (must not be treated as empty/absent).
            'counts.*.counted' => ['required', 'integer', 'min:0'],
        ]);

        $adjusted = 0;
        DB::transaction(function () use ($validated, $warehouse, &$adjusted) {
            foreach ($validated['counts'] as $row) {
                $product = Product::query()->lockForUpdate()->find($row['product_id']);
                if (! $product || ! $product->tracksStock()) {
                    continue;
                }

                // Explicit cast: "0" and 0 must remain 0 (never fall back to theoretical).
                $counted = (int) $row['counted'];
                $notes = 'Inventaire physique '.$warehouse->name;
                $channel = $warehouse->isOnline() ? 'enligne' : 'magasin';

                // Prefer the exact ProductStock row from the form so a product default
                // location never redirects a null-emplacement line to the wrong slot
                // (which made counted=0 a no-op when that other slot was already 0).
                if (! empty($row['product_stock_id'])) {
                    $slot = ProductStock::query()
                        ->where('id', (int) $row['product_stock_id'])
                        ->where('warehouse_id', $warehouse->id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();
                    if (! $slot) {
                        continue;
                    }

                    $current = (int) $slot->quantity;
                    if ($current === $counted) {
                        continue;
                    }

                    $this->stockMovement->setSlotQuantity(
                        $product,
                        $slot,
                        $counted,
                        $notes.' : écart '.($counted - $current),
                        $channel
                    );
                    $adjusted++;

                    continue;
                }

                $locationId = array_key_exists('warehouse_location_id', $row) && $row['warehouse_location_id'] !== null && $row['warehouse_location_id'] !== ''
                    ? (int) $row['warehouse_location_id']
                    : null;
                $variantId = ! empty($row['product_variant_id']) ? (int) $row['product_variant_id'] : null;
                $current = $this->stockMovement->quantityAtSlot(
                    $product,
                    (int) $warehouse->id,
                    $locationId,
                    $variantId
                );

                if ($current === $counted) {
                    continue;
                }

                $this->stockMovement->setQuantity(
                    $product,
                    $counted,
                    (int) $warehouse->id,
                    $locationId,
                    $notes.' : écart '.($counted - $current),
                    $channel,
                    $variantId,
                    false
                );
                $adjusted++;
            }
        });

        return redirect()->route('stock.locations.show', $warehouse)
            ->with('success', $adjusted.' ajustement(s) d’inventaire enregistré(s).');
    }

    public function productBreakdown(Product $product)
    {
        $product->load(['stocks.warehouse', 'variants']);
        $warehouses = Warehouse::query()->active()->orderByDesc('is_fulfillment_default')->orderBy('name')->get();
        $locations = $this->stockMovement->locationBreakdown($product);
        $physicalWarehouses = $warehouses->filter(fn (Warehouse $w) => $w->isPhysical());

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->ref,
                'image' => $product->image_url,
                'has_variants' => $product->hasVariants(),
            ],
            'variants' => $product->hasVariants()
                ? $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'label' => $v->name ?: $v->sku,
                    'sku' => $v->sku,
                ])->values()
                : [],
            'locations' => $locations,
            'physical_total' => $this->stockMovement->physicalTotal($product),
            'online_total' => (int) $product->stock_enligne,
            'warehouses' => $warehouses->map(fn (Warehouse $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'kind' => $w->kind,
                'is_physical' => $w->isPhysical(),
            ]),
            'physical_warehouses' => $physicalWarehouses->map(fn (Warehouse $w) => [
                'id' => $w->id,
                'name' => $w->name,
            ])->values(),
            'reasons' => collect(StockMovement::PHYSICAL_STOCK_REASONS)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
            'adjustment_reasons' => collect(StockMovement::STOCK_ADJUSTMENT_REASONS)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
            'locations_url' => route('warehouses.locations.json'),
            'movements_url' => route('stock.movements.index', ['product_id' => $product->id]),
            'transfer_url' => route('stock.transfer.store'),
            'declare_url' => route('products.declare-stock', $product),
            'adjust_url' => route('stock.magasin.edit', $product),
        ]);
    }

    public function declarePhysicalStock(Request $request, Product $product)
    {
        if (! $product->tracksStock()) {
            abort(404);
        }

        $mode = $request->input('mode', 'add');
        $isAdjust = $mode === 'set';

        $validated = $request->validate([
            'mode' => 'nullable|in:add,set',
            'quantity' => $isAdjust ? 'required|integer|min:0' : 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
            'reason' => [
                'required',
                'string',
                \Illuminate\Validation\Rule::in(array_keys(
                    $isAdjust ? StockMovement::STOCK_ADJUSTMENT_REASONS : StockMovement::PHYSICAL_STOCK_REASONS
                )),
            ],
            'moved_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        if ($product->hasVariants() && empty($validated['product_variant_id'])) {
            return $this->declareStockResponse($request, false, 'Veuillez sélectionner une variante.');
        }

        $warehouse = Warehouse::query()->findOrFail($validated['warehouse_id']);
        if ($warehouse->isOnline()) {
            return $this->declareStockResponse(
                $request,
                false,
                $isAdjust
                    ? 'Le stock Shopify / en ligne ne peut pas être ajusté ici.'
                    : 'Le stock physique ne peut pas être déclaré sur un dépôt en ligne.'
            );
        }

        $locationId = ! empty($validated['warehouse_location_id'])
            ? (int) $validated['warehouse_location_id']
            : null;

        if ($locationId) {
            $location = WarehouseLocation::query()->findOrFail($locationId);
            if ((int) $location->warehouse_id !== (int) $warehouse->id) {
                return $this->declareStockResponse($request, false, 'L’emplacement sélectionné n’appartient pas au dépôt choisi.');
            }
        }

        try {
            DB::transaction(function () use ($product, $validated, $locationId, $isAdjust) {
                if ($isAdjust) {
                    $movement = $this->stockMovement->adjustPhysicalStock(
                        $product,
                        (int) $validated['quantity'],
                        (int) $validated['warehouse_id'],
                        $locationId,
                        $validated['reason'],
                        $validated['notes'] ?? null,
                        isset($validated['product_variant_id']) ? (int) $validated['product_variant_id'] : null
                    );

                    if (! $movement) {
                        throw new \RuntimeException('Aucun changement : la quantité est identique au stock actuel.');
                    }

                    return;
                }

                $movedAt = ! empty($validated['moved_at'])
                    ? Carbon::parse($validated['moved_at'])
                    : null;

                $this->stockMovement->declarePhysicalStock(
                    $product,
                    (int) $validated['quantity'],
                    (int) $validated['warehouse_id'],
                    $locationId,
                    $validated['reason'],
                    $validated['notes'] ?? null,
                    $movedAt,
                    isset($validated['product_variant_id']) ? (int) $validated['product_variant_id'] : null
                );
            });
        } catch (\Throwable $e) {
            return $this->declareStockResponse($request, false, $e->getMessage());
        }

        $message = $isAdjust
            ? 'Stock physique ajusté à '.(int) $validated['quantity'].' unité(s). Shopify / En ligne non modifié.'
            : 'Stock physique déclaré : +'.(int) $validated['quantity'].' unité(s) enregistrée(s).';

        if ($request->expectsJson() || $request->ajax()) {
            $product->refresh()->load(['stocks.warehouse']);

            return response()->json([
                'success' => true,
                'message' => $message,
                'physical_total' => $this->stockMovement->physicalTotal($product),
                'online_total' => (int) $product->stock_enligne,
                'locations' => $this->stockMovement->locationBreakdown($product),
            ]);
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    protected function declareStockResponse(Request $request, bool $success, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        }

        return redirect()->back()->withInput()->with($success ? 'success' : 'error', $message);
    }

    protected function exportExcel(array $report): StreamedResponse
    {
        $warehouse = $report['warehouse'];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($warehouse->name, 0, 31));

        $sheet->setCellValue([1, 1], 'STOCK '.$warehouse->name);
        $sheet->setCellValue([1, 2], 'État au '.$report['as_of']->format('d/m/Y'));

        $headers = [
            'Produit', 'Variante', 'SKU', 'Dépôt', 'Emplacement',
            'Quantité physique', 'Réservé', 'Disponible',
            'PA HT', 'PA TTC', 'Source du prix d\'achat',
            'Val. HT', 'Val. TTC',
            'Fournisseur', 'TVA %',
        ];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 4], $header);
        }

        $rowNum = 5;
        foreach ($report['rows'] as $row) {
            $hasCost = (bool) ($row->has_purchase_cost ?? ($row->price_ht !== null));
            $sheet->setCellValue([1, $rowNum], $row->name);
            $sheet->setCellValue([2, $rowNum], $row->variant ?? '');
            $sheet->setCellValue([3, $rowNum], $row->sku);
            $sheet->setCellValue([4, $rowNum], $row->depot ?? $warehouse->name);
            $sheet->setCellValue([5, $rowNum], $row->location ?? '—');
            $sheet->setCellValue([6, $rowNum], $row->quantity);
            $sheet->setCellValue([7, $rowNum], $row->reserved ?? 0);
            $sheet->setCellValue([8, $rowNum], $row->available ?? $row->quantity);
            $sheet->setCellValue([9, $rowNum], $hasCost ? $row->price_ht : LocationStockReportService::PRICE_SOURCE_NONE);
            $sheet->setCellValue([10, $rowNum], $hasCost ? $row->price_ttc : LocationStockReportService::PRICE_SOURCE_NONE);
            $sheet->setCellValue([11, $rowNum], $row->price_source ?? LocationStockReportService::PRICE_SOURCE_NONE);
            $sheet->setCellValue([12, $rowNum], $hasCost ? $row->value_ht : LocationStockReportService::PRICE_SOURCE_NONE);
            $sheet->setCellValue([13, $rowNum], $hasCost ? $row->value_ttc : LocationStockReportService::PRICE_SOURCE_NONE);
            $sheet->setCellValue([14, $rowNum], $row->supplier ?? '');
            $sheet->setCellValue([15, $rowNum], $row->vat_rate ?? '');
            $rowNum++;
        }

        $rowNum++;
        $sheet->setCellValue([1, $rowNum], 'TOTAL STOCK '.$warehouse->name);
        $rowNum++;
        $sheet->setCellValue([1, $rowNum], 'Nombre de références');
        $sheet->setCellValue([2, $rowNum], $report['references']);
        $rowNum++;
        $sheet->setCellValue([1, $rowNum], 'Quantité totale');
        $sheet->setCellValue([2, $rowNum], $report['quantity']);
        $rowNum++;
        $sheet->setCellValue([1, $rowNum], 'Valeur totale HT (DH)');
        $sheet->setCellValue([2, $rowNum], $report['value_ht']);
        $rowNum++;
        $sheet->setCellValue([1, $rowNum], 'TVA');
        $sheet->setCellValue([2, $rowNum], $report['value_vat'] ?? round($report['value_ttc'] - $report['value_ht'], 2));
        $rowNum++;
        $sheet->setCellValue([1, $rowNum], 'Valeur totale TTC (DH)');
        $sheet->setCellValue([2, $rowNum], $report['value_ttc']);

        $filename = 'stock-'.\Illuminate\Support\Str::slug($warehouse->code ?: $warehouse->name).'-'.$report['as_of']->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function exportPdf(array $report)
    {
        $warehouse = $report['warehouse'];
        $pdf = Pdf::loadView('stock.locations.pdf', $report);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('stock-'.\Illuminate\Support\Str::slug($warehouse->code ?: $warehouse->name).'-'.$report['as_of']->format('Y-m-d').'.pdf');
    }
}
