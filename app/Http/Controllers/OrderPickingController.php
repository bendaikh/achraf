<?php

namespace App\Http\Controllers;

use App\Models\PosSale;
use App\Models\StockReservation;
use App\Services\OrderPhysicalStockService;
use App\Support\StockSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OrderPickingController extends Controller
{
    public function __construct(
        protected OrderPhysicalStockService $orderPhysicalStock
    ) {}

    public function index(Request $request)
    {
        $query = PosSale::query()
            ->with(['client', 'items.product', 'items.variant'])
            ->whereNull('physical_stock_processed_at')
            ->whereHas('items.product', fn ($q) => $q->where('item_kind', 'stocked'))
            ->orderByDesc('id');

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

        $reservationCounts = StockReservation::query()
            ->active()
            ->where('source_type', 'pos_sale')
            ->whereIn('source_id', $orders->pluck('id'))
            ->selectRaw('source_id, SUM(quantity) as qty')
            ->groupBy('source_id')
            ->pluck('qty', 'source_id');

        return view('sales.picking.index', [
            'orders' => $orders,
            'reservationCounts' => $reservationCounts,
            'pickingEnabled' => StockSettings::pickingEnabled(),
        ]);
    }

    public function pdf(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:pos_sales,id',
        ]);

        $reservations = StockReservation::query()
            ->active()
            ->where('source_type', 'pos_sale')
            ->whereIn('source_id', $validated['order_ids'])
            ->with(['product', 'variant', 'warehouse', 'location', 'product'])
            ->get();

        $orders = PosSale::query()
            ->whereIn('id', $validated['order_ids'])
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
        $validated = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:pos_sales,id',
        ]);

        $ok = 0;
        $errors = [];

        foreach ($validated['order_ids'] as $orderId) {
            $order = PosSale::query()->find($orderId);
            if (! $order) {
                continue;
            }
            try {
                $result = $this->orderPhysicalStock->validateExit($order);
                if ($result['consumed'] > 0 || $order->physical_stock_processed_at) {
                    $ok++;
                }
            } catch (\Throwable $e) {
                $errors[] = ($order->ticket_number ?: '#'.$order->id).' : '.$e->getMessage();
            }
        }

        if ($errors !== []) {
            return back()->with('warning', $ok.' sortie(s) validée(s). Erreurs : '.implode(' | ', $errors));
        }

        return back()->with('success', $ok.' commande(s) : sortie physique validée (stock diminué, réservations soldées).');
    }
}
