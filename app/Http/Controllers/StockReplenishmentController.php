<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockReplenishmentNeed;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Services\DocumentNumberService;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Services\LocationStockReportService;
use App\Services\OrderPhysicalStockService;
use App\Services\ProductPurchaseHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockReplenishmentController extends Controller
{
    public function __construct(
        protected ProductPurchaseHistoryService $purchaseHistory,
        protected LocationStockReportService $purchaseCosts
    ) {}

    public function index()
    {
        $needs = StockReplenishmentNeed::query()
            ->open()
            ->where('quantity_needed', '>', 0)
            ->whereHas('product', fn ($q) => $q->tracksStock())
            ->with(['product', 'suggestedSupplier', 'supplier', 'warehouse', 'posSale'])
            ->orderByDesc('id')
            ->get();

        $productIds = $needs->pluck('product_id')->unique()->all();
        $lastSuppliers = $this->purchaseHistory->lastSuppliersForProducts($productIds);

        $groups = $needs->groupBy(function (StockReplenishmentNeed $need) {
            return (string) ($need->supplier_id ?: $need->suggested_supplier_id ?: 0);
        });

        $suppliers = Supplier::query()->orderBy('name')->get();
        $physicalStocks = $this->physicalStockByProduct($productIds);

        return view('stock.replenishment.index', compact('needs', 'groups', 'suppliers', 'lastSuppliers', 'physicalStocks'));
    }

    /**
     * Achats → Besoins d'achat — même besoin métier que Produits → À approvisionner.
     */
    public function purchaseNeeds()
    {
        $needs = StockReplenishmentNeed::query()
            ->open()
            ->where('quantity_needed', '>', 0)
            ->whereHas('product', fn ($q) => $q->tracksStock())
            ->with(['product.stocks.warehouse', 'suggestedSupplier', 'supplier', 'warehouse', 'posSale'])
            ->orderByDesc('id')
            ->get();

        $productIds = $needs->pluck('product_id')->unique()->all();
        $lastSuppliers = $this->purchaseHistory->lastSuppliersForProducts($productIds);

        $groups = $needs->groupBy(function (StockReplenishmentNeed $need) {
            return (string) ($need->supplier_id ?: $need->suggested_supplier_id ?: 0);
        });

        $suppliers = Supplier::query()->orderBy('name')->get();
        $physicalStocks = $this->physicalStockByProduct($productIds);

        return view('purchases.needs.index', compact('needs', 'groups', 'suppliers', 'lastSuppliers', 'physicalStocks'));
    }

    /**
     * « Réinitialiser / Recalculer les besoins d’achat » : supprime les besoins automatiques
     * non traités, libère les réservations non validées, relit le stock physique et recrée
     * uniquement les vrais manques. Ne touche ni aux commandes ni au stock physique.
     */
    public function recalculate(OrderPhysicalStockService $orderPhysicalStock)
    {
        try {
            $result = $orderPhysicalStock->recalculatePurchaseNeeds();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Besoins d’achat réinitialisés : %d commande(s) analysée(s), %d besoin(s) non traité(s) supprimé(s), %d réservation(s) libérée(s), %d unité(s) re-réservée(s), %d besoin(s) réel(s) recréé(s) (%d unité(s)).',
            $result['orders'],
            $result['cancelled_needs'],
            $result['released'],
            $result['reserved_qty'],
            $result['needs'],
            $result['shortage_qty']
        ));
    }

    /**
     * Stock physique / réservé / disponible par produit — même source que « Stock par
     * emplacement » et le picking : dépôts physiques vendables (Magasin Belvédère),
     * jamais le miroir Shopify en ligne ni SAV / quarantaine.
     *
     * @param  list<int>  $productIds
     * @return array<int, array{physical:int, reserved:int, available:int}>
     */
    protected function physicalStockByProduct(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $warehouseIds = Warehouse::query()->active()->sellable()->pluck('id');

        $rows = ProductStock::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('warehouse_id', $warehouseIds)
            ->get(['product_id', 'quantity', 'reserved']);

        $out = [];
        foreach ($rows as $row) {
            $pid = (int) $row->product_id;
            $out[$pid] ??= ['physical' => 0, 'reserved' => 0, 'available' => 0];
            $out[$pid]['physical'] += (int) $row->quantity;
            $out[$pid]['reserved'] += (int) $row->reserved;
            $out[$pid]['available'] += max(0, (int) $row->quantity - (int) $row->reserved);
        }

        return $out;
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
                $unit = $product
                    ? $this->purchaseCosts->purchasePriceHt($product)
                    : 0.0;
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
