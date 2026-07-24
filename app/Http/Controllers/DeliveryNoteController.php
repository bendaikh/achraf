<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Services\DocumentNumberService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeliveryNoteController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function index(Request $request)
    {
        $query = DeliveryNote::with('client');

        $this->applyTableSearch($query, $request, ['delivery_number', 'reference', 'client.name']);
        $this->applyTableDateRange($query, $request, 'delivery_date');
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableSort($query, $request, [
            'delivery_date' => 'delivery_date',
            'shipping_date' => 'shipping_date',
        ], 'delivery_date', 'desc');

        $deliveryNotes = $this->paginateTable($query, $request);

        return view('sales.delivery-notes.index', compact('deliveryNotes'));
    }

    public function create()
    {
        $products = Product::all();
        $deliveryNumber = DocumentNumberService::preview('bon_livraison');
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('sales.delivery-notes.create', compact('products', 'deliveryNumber', 'pricesAreTtc'));
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
            ]);

            $subtotal = $this->syncItems($deliveryNote, $validated['items']);
            $deliveryNote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();

            return redirect()->route('delivery-notes.index')->with('success', 'Bon de livraison créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur: '.$e->getMessage());
        }
    }

    public function show(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load('client', 'items');

        return view('sales.delivery-notes.show', compact('deliveryNote'));
    }

    public function edit(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load('client', 'items');
        $products = Product::all();
        $existingItems = $deliveryNote->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'ref' => $item->ref,
            'designation' => $item->designation,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
            'discount' => $item->discount,
            'discount_type' => $item->discount_type ?? 'fixed',
        ])->values();
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('sales.delivery-notes.edit', compact('deliveryNote', 'products', 'existingItems', 'pricesAreTtc'));
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

            DB::commit();

            return redirect()->route('delivery-notes.show', $deliveryNote)->with('success', 'Bon de livraison mis à jour avec succès!');
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
        if ($deliveryNote->document_file_path && Storage::disk('public')->exists($deliveryNote->document_file_path)) {
            $path = Storage::disk('public')->path($deliveryNote->document_file_path);
            $filename = $deliveryNote->delivery_number.'.'.pathinfo($path, PATHINFO_EXTENSION);

            return response()->file($path, [
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

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
        if ($deliveryNote->document_file_path && Storage::disk('public')->exists($deliveryNote->document_file_path)) {
            $path = Storage::disk('public')->path($deliveryNote->document_file_path);
            $filename = $deliveryNote->delivery_number.'.'.pathinfo($path, PATHINFO_EXTENSION);

            return response()->download($path, $filename);
        }

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
        ]);
    }

    protected function syncItems(DeliveryNote $deliveryNote, array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $computed = LineItemCalculator::compute($item);

            $deliveryNote->items()->create([
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

        return $subtotal;
    }
}
