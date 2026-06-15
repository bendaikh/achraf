<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Client;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Services\DocumentNumberService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function index(Request $request)
    {
        $query = PurchaseOrder::with('client')->latest();

        $this->applyTableSearch($query, $request, ['reference', 'client.name']);
        $this->applyTableDateRange($query, $request, 'order_date');
        $this->applyTableFilter($query, $request, 'status', 'status');

        $purchaseOrders = $query->paginate(15)->withQueryString();

        return view('sales.purchase-orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        $products = Product::all();
        $reference = DocumentNumberService::preview('bc_client');
        
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('sales.purchase-orders.create', compact('products', 'reference', 'pricesAreTtc'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'order_date' => 'required|date',
            'expiry_date' => 'nullable|date',
            'currency' => 'required|string',
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
        ]);

        DB::beginTransaction();
        try {
            $purchaseOrder = PurchaseOrder::create([
                'reference' => DocumentNumberService::generate('bc_client'),
                'client_id' => $validated['client_id'],
                'order_date' => $validated['order_date'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'model' => $validated['model'] ?? null,
                'matricule' => $validated['matricule'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
                'subtotal' => 0,
                'discount' => 0,
                'adjustment' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item);

                $purchaseOrder->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'ref' => $item['ref'] ?? null,
                    'designation' => $item['designation'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $computed['discount'],
                    'discount_type' => $computed['discount_type'],
                    'line_total' => $computed['line_total'],
                ]);

                $subtotal += $computed['line_total'];
            }

            $purchaseOrder->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();
            return redirect()->route('purchase-orders.index')->with('success', 'Bon de commande créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la création du bon de commande: ' . $e->getMessage());
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('client', 'items');
        return view('sales.purchase-orders.show', compact('purchaseOrder'));
    }

    public function print(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('client', 'items');
        $printData = $this->printViewData($purchaseOrder, $purchaseOrder->items);

        return view('sales.purchase-orders.print', array_merge(
            CommercialDocumentView::forPurchaseOrder($purchaseOrder, $printData['taxes']),
            $printData,
            compact('purchaseOrder'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('client', 'items');
        $printData = $this->printViewData($purchaseOrder, $purchaseOrder->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forPurchaseOrder($purchaseOrder, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'bon-commande',
            $purchaseOrder->reference
        );
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->delete();
        return redirect()->route('purchase-orders.index')->with('success', 'Bon de commande supprimé avec succès!');
    }
}
