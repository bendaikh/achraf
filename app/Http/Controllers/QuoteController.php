<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Setting;
use App\Services\DocumentNumberService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function index(Request $request)
    {
        $query = Quote::with('client');

        $this->applyTableSearch($query, $request, ['quote_number', 'client.name']);
        $this->applyTableDateRange($query, $request, 'quote_date');
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableSort($query, $request, [
            'quote_date' => 'quote_date',
            'expiry_date' => 'expiry_date',
        ], 'quote_date', 'desc');

        $quotes = $this->paginateTable($query, $request);

        return view('sales.quotes.index', compact('quotes'));
    }

    public function create()
    {
        $products = Product::all();
        $quoteNumber = DocumentNumberService::preview('devis');

        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('sales.quotes.create', compact('products', 'quoteNumber', 'pricesAreTtc'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuote($request);

        DB::beginTransaction();
        try {
            $quote = Quote::create([
                'quote_number' => DocumentNumberService::generate('devis'),
                'client_id' => $validated['client_id'],
                'quote_date' => $validated['quote_date'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
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

            $subtotal = $this->syncItems($quote, $validated['items']);

            $quote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();

            return redirect()->route('quotes.index')->with('success', 'Devis créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur lors de la création du devis: '.$e->getMessage());
        }
    }

    public function show(Quote $quote)
    {
        $quote->load('client', 'items');

        return view('sales.quotes.show', compact('quote'));
    }

    public function edit(Quote $quote)
    {
        $quote->load('client', 'items');
        $products = Product::all();
        $existingItems = $quote->items->map(fn ($item) => [
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

        return view('sales.quotes.edit', compact('quote', 'products', 'existingItems', 'pricesAreTtc'));
    }

    public function update(Request $request, Quote $quote)
    {
        $validated = $this->validateQuote($request);

        DB::beginTransaction();
        try {
            $quote->update([
                'client_id' => $validated['client_id'],
                'quote_date' => $validated['quote_date'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'status' => $validated['status'],
                'model' => $validated['model'] ?? null,
                'matricule' => $validated['matricule'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
            ]);

            $quote->items()->delete();
            $subtotal = $this->syncItems($quote, $validated['items']);

            $quote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();

            return redirect()->route('quotes.show', $quote)->with('success', 'Devis mis à jour avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur lors de la mise à jour du devis: '.$e->getMessage());
        }
    }

    public function print(Quote $quote)
    {
        $quote->load('client', 'items');
        $printData = $this->printViewData($quote, $quote->items);

        return view('sales.quotes.print', array_merge(
            CommercialDocumentView::forQuote($quote, $printData['taxes']),
            $printData,
            compact('quote'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(Quote $quote)
    {
        $quote->load('client', 'items');
        $printData = $this->printViewData($quote, $quote->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forQuote($quote, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'devis',
            $quote->quote_number
        );
    }

    public function destroy(Quote $quote)
    {
        $quote->delete();

        return redirect()->route('quotes.index')->with('success', 'Devis supprimé avec succès!');
    }

    protected function validateQuote(Request $request): array
    {
        return $request->validate([
            'client_id' => 'required|exists:clients,id',
            'quote_date' => 'required|date',
            'expiry_date' => 'nullable|date',
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

    protected function syncItems(Quote $quote, array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $computed = LineItemCalculator::compute($item);

            $quote->items()->create([
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
