<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockReplenishmentNeed;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Services\DocumentNumberService;
use App\Services\ProductPurchaseHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockReplenishmentController extends Controller
{
    public function __construct(
        protected ProductPurchaseHistoryService $purchaseHistory
    ) {}

    public function index()
    {
        $needs = StockReplenishmentNeed::query()
            ->open()
            ->with(['product', 'suggestedSupplier', 'supplier', 'warehouse', 'posSale'])
            ->orderByDesc('id')
            ->get();

        $productIds = $needs->pluck('product_id')->unique()->all();
        $lastSuppliers = $this->purchaseHistory->lastSuppliersForProducts($productIds);

        $groups = $needs->groupBy(function (StockReplenishmentNeed $need) {
            return (string) ($need->supplier_id ?: $need->suggested_supplier_id ?: 0);
        });

        $suppliers = Supplier::query()->orderBy('name')->get();

        return view('stock.replenishment.index', compact('needs', 'groups', 'suppliers', 'lastSuppliers'));
    }

    public function updateSupplier(Request $request, StockReplenishmentNeed $need)
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $need->update([
            'supplier_id' => $validated['supplier_id'] ?? null,
        ]);

        return back()->with('success', 'Fournisseur mis à jour.');
    }

    public function generatePurchaseOrder(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'need_ids' => 'required|array|min:1',
            'need_ids.*' => 'integer|exists:stock_replenishment_needs,id',
        ]);

        $needs = StockReplenishmentNeed::query()
            ->open()
            ->whereIn('id', $validated['need_ids'])
            ->with('product')
            ->get();

        if ($needs->isEmpty()) {
            return back()->with('error', 'Aucun besoin ouvert à regrouper.');
        }

        $order = DB::transaction(function () use ($needs, $validated) {
            $number = DocumentNumberService::preview('bc_fournisseur');
            $order = SupplierPurchaseOrder::create([
                'order_number' => $number,
                'supplier_id' => $validated['supplier_id'],
                'order_date' => now()->toDateString(),
                'currency' => 'dh - MAD',
                'stock_location' => 'Magasin Belvédère',
                'remarks' => 'Généré depuis les besoins d’approvisionnement',
                'subtotal' => 0,
                'total' => 0,
            ]);

            $byProduct = $needs->groupBy('product_id');
            $subtotal = 0;
            foreach ($byProduct as $productId => $productNeeds) {
                $product = $productNeeds->first()->product;
                $qty = (int) $productNeeds->sum('quantity_needed');
                $unit = (float) ($product->cost_price_ht ?? $product->last_purchase_price ?? 0);
                $lineTotal = round($unit * $qty, 2);
                $order->items()->create([
                    'product_id' => $productId,
                    'ref' => $product?->ref,
                    'designation' => $product?->name ?? 'Produit',
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'tax_rate' => 20,
                    'discount' => 0,
                    'discount_type' => 'fixed',
                    'line_total' => $lineTotal,
                ]);
                $subtotal += $lineTotal;
            }

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            foreach ($needs as $need) {
                $need->update([
                    'status' => StockReplenishmentNeed::STATUS_ORDERED,
                    'supplier_id' => $validated['supplier_id'],
                    'supplier_purchase_order_id' => $order->id,
                    'quantity_ordered' => $need->quantity_needed,
                ]);
            }

            DocumentNumberService::advanceAfterUse('bc_fournisseur', $number);

            return $order;
        });

        return redirect()->route('supplier-purchase-orders.show', $order)
            ->with('success', 'BC fournisseur généré : '.$order->order_number);
    }
}
