<?php

namespace App\Http\Controllers;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Services\OrderPhysicalStockService;
use App\Support\StockSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderPickingController extends Controller
{
    public function __construct(
        protected OrderPhysicalStockService $orderPhysicalStock
    ) {}

    public function index(Request $request)
    {
        $pickingEnabled = StockSettings::pickingEnabled();
        $pickingActivatedAt = StockSettings::pickingActivatedAt();

        // Before listing: verify physical stock (Belvédère / dépôts physiques), reserve what
        // is available, and push shortfalls to Besoins d'achat. Shopify online is never used.
        if ($pickingEnabled && $pickingActivatedAt) {
            $this->syncPhysicalAllocations($pickingActivatedAt);
        }

        $query = PosSale::query()
            ->with(['client', 'items.product', 'items.variant'])
            ->whereNull('physical_stock_processed_at')
            ->whereHas('items.product', fn ($q) => $q->where('item_kind', 'stocked'))
            // Only orders with real physical reservations belong in Picking.
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('stock_reservations')
                    ->whereColumn('stock_reservations.source_id', 'pos_sales.id')
                    ->where('stock_reservations.source_type', 'pos_sale')
                    ->where('stock_reservations.status', StockReservation::STATUS_ACTIVE);
            })
            ->orderByDesc('id');

        // Active picking queue starts at activation — never pull pre-activation history.
        if ($pickingEnabled && $pickingActivatedAt) {
            $query->where('created_at', '>=', $pickingActivatedAt);
        } else {
            // Picking off or no cutoff yet → empty active queue (orders stay in normal history).
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', '%'.$search.'%')
                    ->orWhere('external_id', 'like', '%'.$search.'%')
                    ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('sold_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sold_at', '<=', $request->input('date_to'));
        }

        $orders = $query->paginate(40)->withQueryString();

        $belvedere = Warehouse::fulfillmentWarehouse();
        $orderLines = [];
        $reservationCounts = [];

        foreach ($orders as $order) {
            $lines = $this->orderPhysicalStock->pickingLinesForOrder($order, $belvedere);
            $orderLines[$order->id] = $lines;
            // Order-level "Réservé" = sum of quantities actually reserved on product lines.
            $reservationCounts[$order->id] = (int) collect($lines)->sum('reserved');
        }

        return view('sales.picking.index', [
            'orders' => $orders,
            'orderLines' => $orderLines,
            'reservationCounts' => $reservationCounts,
            'pickingEnabled' => $pickingEnabled,
            'pickingActivatedAt' => $pickingActivatedAt,
            'belvedereName' => $belvedere?->name ?: 'Magasin Belvédère',
        ]);
    }

    public function pdf(Request $request)
    {
        [$orderIds, $linesByOrder] = $this->selectionFromRequest($request);
        if ($orderIds === []) {
            return back()->with('error', 'Sélectionnez au moins une commande ou une ligne produit réservée.');
        }

        $orderIds = $this->eligiblePickingOrderIds($orderIds);
        if ($orderIds === []) {
            return back()->with('error', 'Aucune commande éligible au picking (créée/importée avant l’activation).');
        }

        $reservations = StockReservation::query()
            ->active()
            ->where('source_type', 'pos_sale')
            ->whereIn('source_id', $orderIds)
            ->with(['product', 'variant', 'warehouse', 'location', 'product'])
            ->get()
            // Lignes cochées uniquement quand une sélection par ligne est envoyée.
            ->filter(function (StockReservation $reservation) use ($linesByOrder) {
                $selected = $linesByOrder[(int) $reservation->source_id] ?? null;

                return $selected === null || in_array((int) $reservation->source_line_id, $selected, true);
            });

        $orders = PosSale::query()
            ->whereIn('id', $orderIds)
            ->get()
            ->keyBy('id');

        // Group: Warehouse → Location → Product
        $groups = [];
        foreach ($reservations as $reservation) {
            $whName = $reservation->warehouse?->name ?: 'Dépôt';
            $locCode = $reservation->location?->code ?: '—';
            $sku = $reservation->variant?->sku ?: $reservation->product?->ref ?: '—';
            $key = $whName.'|'.$locCode.'|'.$sku.'|'.$reservation->product_id.'|'.($reservation->product_variant_id ?: 0);

            if (! isset($groups[$whName])) {
                $groups[$whName] = [];
            }
            if (! isset($groups[$whName][$locCode])) {
                $groups[$whName][$locCode] = [];
            }
            if (! isset($groups[$whName][$locCode][$key])) {
                $groups[$whName][$locCode][$key] = [
                    'sku' => $sku,
                    'name' => $reservation->product?->name,
                    'variant' => $reservation->variant?->full_title,
                    'quantity' => 0,
                    'orders' => [],
                ];
            }
            $groups[$whName][$locCode][$key]['quantity'] += (int) $reservation->quantity;
            $order = $orders->get($reservation->source_id);
            $ref = $order?->ticket_number ?: ('#'.$reservation->source_id);
            $groups[$whName][$locCode][$key]['orders'][$ref] = $ref;
        }

        $pdf = Pdf::loadView('sales.picking.pdf', [
            'groups' => $groups,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('picking-'.now()->format('Ymd-His').'.pdf');
    }

    public function validateExit(Request $request)
    {
        [$orderIds, $linesByOrder] = $this->selectionFromRequest($request);
        if ($orderIds === []) {
            return back()->with('error', 'Sélectionnez au moins une ligne produit réservée à sortir.');
        }

        $completed = 0;
        $partial = 0;
        $lines = 0;
        $units = 0;
        $errors = [];

        foreach ($this->eligiblePickingOrderIds($orderIds) as $orderId) {
            $order = PosSale::query()->find($orderId);
            if (! $order) {
                continue;
            }
            try {
                // null → toutes les lignes réservées ; sinon UNIQUEMENT les lignes cochées.
                $result = $this->orderPhysicalStock->validateExit($order, $linesByOrder[$orderId] ?? null);
                $lines += $result['consumed'];
                $units += $result['quantity'] ?? 0;
                if ($result['completed']) {
                    $completed++;
                } elseif ($result['consumed'] > 0) {
                    $partial++;
                }
            } catch (\Throwable $e) {
                $errors[] = ($order->ticket_number ?: '#'.$order->id).' : '.$e->getMessage();
            }
        }

        $message = sprintf(
            'Sortie physique validée : %d ligne(s), %d unité(s) (stock diminué, réservations soldées). %d commande(s) complète(s), %d commande(s) en sortie partielle (lignes restantes en attente / besoin d’achat).',
            $lines,
            $units,
            $completed,
            $partial
        );

        if ($errors !== []) {
            return back()->with('warning', $message.' Erreurs : '.implode(' | ', $errors));
        }

        return back()->with('success', $message);
    }

    /**
     * Picking selection: either ticked product lines (line_ids[]) or whole orders (order_ids[]).
     * When at least one line is ticked, only those lines are used (sortie partielle).
     *
     * @return array{0: list<int>, 1: array<int, list<int>>}
     */
    protected function selectionFromRequest(Request $request): array
    {
        $validated = $request->validate([
            'order_ids' => 'nullable|array',
            'order_ids.*' => 'integer|exists:pos_sales,id',
            'line_ids' => 'nullable|array',
            'line_ids.*' => 'integer',
        ]);

        $lineIds = array_values(array_unique(array_map('intval', $validated['line_ids'] ?? [])));
        if ($lineIds !== []) {
            $linesByOrder = [];
            PosSaleItem::query()
                ->whereIn('id', $lineIds)
                ->get(['id', 'pos_sale_id'])
                ->each(function (PosSaleItem $item) use (&$linesByOrder) {
                    $linesByOrder[(int) $item->pos_sale_id][] = (int) $item->id;
                });

            return [array_map('intval', array_keys($linesByOrder)), $linesByOrder];
        }

        $orderIds = array_values(array_unique(array_map('intval', $validated['order_ids'] ?? [])));

        return [$orderIds, []];
    }

    /**
     * Verify physical stock for eligible orders and reserve what Belvédère (and other
     * physical depots) can cover. Shortfalls become replenishment needs.
     */
    protected function syncPhysicalAllocations(\Carbon\CarbonInterface $activatedAt): void
    {
        $candidates = PosSale::query()
            ->whereNull('physical_stock_processed_at')
            ->where('created_at', '>=', $activatedAt)
            ->whereHas('items.product', fn ($q) => $q->where('item_kind', 'stocked'))
            ->orderBy('id')
            ->limit(500)
            ->get();

        foreach ($candidates as $order) {
            try {
                $this->orderPhysicalStock->ensureAllocated($order);
            } catch (\Throwable $e) {
                Log::warning('Picking allocation failed', [
                    'order_id' => $order->id,
                    'ticket' => $order->ticket_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  list<int|string>  $orderIds
     * @return list<int>
     */
    protected function eligiblePickingOrderIds(array $orderIds): array
    {
        if (! StockSettings::pickingEnabled()) {
            return [];
        }

        $activatedAt = StockSettings::pickingActivatedAt();
        if (! $activatedAt) {
            return [];
        }

        return PosSale::query()
            ->whereIn('id', $orderIds)
            ->where('created_at', '>=', $activatedAt)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('stock_reservations')
                    ->whereColumn('stock_reservations.source_id', 'pos_sales.id')
                    ->where('stock_reservations.source_type', 'pos_sale')
                    ->where('stock_reservations.status', StockReservation::STATUS_ACTIVE);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
