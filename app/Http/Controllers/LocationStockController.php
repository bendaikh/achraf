<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\LocationStockReportService;
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
            ->with('product')
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->whereHas('product', fn ($q) => $q->tracksStock())
            ->orderBy('product_id')
            ->get();

        return view('stock.locations.count', compact('warehouse', 'slots'));
    }

    public function countStore(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'counts' => 'required|array',
            'counts.*.product_id' => 'required|exists:products,id',
            'counts.*.counted' => 'required|integer|min:0',
        ]);

        $adjusted = 0;
        DB::transaction(function () use ($validated, $warehouse, &$adjusted) {
            foreach ($validated['counts'] as $row) {
                $product = Product::query()->lockForUpdate()->find($row['product_id']);
                if (! $product || ! $product->tracksStock()) {
                    continue;
                }
                $current = $this->stockMovement->quantityAtWarehouse($product, (int) $warehouse->id);
                $counted = (int) $row['counted'];
                if ($current === $counted) {
                    continue;
                }
                $this->stockMovement->setQuantity(
                    $product,
                    $counted,
                    (int) $warehouse->id,
                    null,
                    'Inventaire physique '.$warehouse->name.' : écart '.($counted - $current),
                    $warehouse->isOnline() ? 'enligne' : 'magasin'
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
            'locations_url' => route('warehouses.locations.json'),
            'movements_url' => route('stock.movements.index', ['product_id' => $product->id]),
            'transfer_url' => route('stock.transfer.store'),
            'declare_url' => route('products.declare-stock', $product),
        ]);
    }

    public function declarePhysicalStock(Request $request, Product $product)
    {
        if (! $product->tracksStock()) {
            abort(404);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
            'reason' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(StockMovement::PHYSICAL_STOCK_REASONS))],
            'moved_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        if ($product->hasVariants() && empty($validated['product_variant_id'])) {
            return $this->declareStockResponse($request, false, 'Veuillez sélectionner une variante.');
        }

        $warehouse = Warehouse::query()->findOrFail($validated['warehouse_id']);
        if ($warehouse->isOnline()) {
            return $this->declareStockResponse($request, false, 'Le stock physique ne peut pas être déclaré sur un dépôt en ligne.');
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
            DB::transaction(function () use ($product, $validated, $locationId) {
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

        $message = 'Stock physique déclaré : +'.(int) $validated['quantity'].' unité(s) enregistrée(s).';

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
            'Référence/SKU', 'Produit', 'Variante', 'Fournisseur', 'Dépôt', 'Emplacement',
            'Quantité physique', 'Réservé', 'Disponible',
            'Dernier prix d\'achat HT', 'TVA', 'Prix d\'achat TTC',
            'Valeur stock HT', 'Valeur stock TTC',
            'Prix de vente HT', 'Prix de vente TTC',
        ];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 4], $header);
        }

        $rowNum = 5;
        foreach ($report['rows'] as $row) {
            $sheet->setCellValue([1, $rowNum], $row->sku);
            $sheet->setCellValue([2, $rowNum], $row->name);
            $sheet->setCellValue([3, $rowNum], $row->variant ?? '');
            $sheet->setCellValue([4, $rowNum], $row->supplier ?? '');
            $sheet->setCellValue([5, $rowNum], $row->depot ?? $warehouse->name);
            $sheet->setCellValue([6, $rowNum], $row->location ?? '—');
            $sheet->setCellValue([7, $rowNum], $row->quantity);
            $sheet->setCellValue([8, $rowNum], $row->reserved ?? 0);
            $sheet->setCellValue([9, $rowNum], $row->available ?? $row->quantity);
            $sheet->setCellValue([10, $rowNum], $row->price_ht);
            $sheet->setCellValue([11, $rowNum], $row->vat_rate ?? '');
            $sheet->setCellValue([12, $rowNum], $row->price_ttc);
            $sheet->setCellValue([13, $rowNum], $row->value_ht);
            $sheet->setCellValue([14, $rowNum], $row->value_ttc);
            $sheet->setCellValue([15, $rowNum], $row->sale_price_ht ?? 0);
            $sheet->setCellValue([16, $rowNum], $row->sale_price_ttc ?? 0);
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
