<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Setting;
use App\Services\DocumentNumberService;
use App\Services\StockMovementService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditNoteController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function __construct(
        protected StockMovementService $stockMovement
    ) {}

    public function index(Request $request)
    {
        $query = CreditNote::with('client', 'invoice')->latest();

        $this->applyTableSearch($query, $request, ['credit_note_number', 'client.name']);
        $this->applyTableDateRange($query, $request, 'credit_note_date');

        $creditNotes = $query->paginate(15)->withQueryString();

        return view('sales.credit-notes.index', compact('creditNotes'));
    }

    public function create()
    {
        $products = Product::all();
        $creditNoteNumber = DocumentNumberService::preview('avoir');
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';

        return view('sales.credit-notes.create', compact('products', 'creditNoteNumber', 'pricesAreTtc'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateCreditNote($request);

        DB::beginTransaction();
        try {
            $creditNote = CreditNote::create([
                'credit_note_number' => DocumentNumberService::generate('avoir'),
                'client_id' => $validated['client_id'],
                'invoice_id' => $validated['invoice_id'] ?? null,
                'credit_note_date' => $validated['credit_note_date'],
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
                'subtotal' => 0,
                'discount' => 0,
                'adjustment' => 0,
                'total' => 0,
            ]);

            $subtotal = $this->syncItems($creditNote, $validated['items']);

            $creditNote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            $creditNote->load('items');
            $this->stockMovement->increaseFromItems(
                $creditNote->items,
                $validated['stock_location']
            );

            DB::commit();

            return redirect()->route('credit-notes.index')->with('success', 'Avoir créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Erreur lors de la création de l\'avoir: '.$e->getMessage());
        }
    }

    public function show(CreditNote $creditNote)
    {
        $creditNote->load('client', 'invoice', 'items');

        return view('sales.credit-notes.show', compact('creditNote'));
    }

    public function edit(CreditNote $creditNote)
    {
        $creditNote->load('client', 'invoice', 'items');
        $products = Product::all();
        $pricesAreTtc = Setting::getShopifyPriceType() === 'ttc';
        $existingItems = $creditNote->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'ref' => $item->ref,
            'designation' => $item->designation,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
            'discount' => $item->discount,
            'discount_type' => $item->discount_type ?? 'fixed',
        ])->values();
        $clientInvoices = Invoice::where('client_id', $creditNote->client_id)
            ->orderByDesc('invoice_date')
            ->get(['id', 'invoice_number']);

        return view('sales.credit-notes.edit', compact(
            'creditNote',
            'products',
            'pricesAreTtc',
            'existingItems',
            'clientInvoices'
        ));
    }

    public function update(Request $request, CreditNote $creditNote)
    {
        $validated = $this->validateCreditNote($request);

        DB::beginTransaction();
        try {
            $creditNote->update([
                'client_id' => $validated['client_id'],
                'invoice_id' => $validated['invoice_id'] ?? null,
                'credit_note_date' => $validated['credit_note_date'],
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
            ]);

            $creditNote->items()->delete();
            $subtotal = $this->syncItems($creditNote, $validated['items']);

            $creditNote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();

            return redirect()->route('credit-notes.show', $creditNote)
                ->with('success', 'Avoir modifié avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Erreur lors de la modification de l\'avoir: '.$e->getMessage());
        }
    }

    public function print(CreditNote $creditNote)
    {
        $creditNote->load('client', 'invoice', 'items');
        $printData = $this->printViewData($creditNote, $creditNote->items);

        return view('sales.credit-notes.print', array_merge(
            CommercialDocumentView::forCreditNote($creditNote, $printData['taxes']),
            $printData,
            compact('creditNote'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(CreditNote $creditNote)
    {
        $creditNote->load('client', 'invoice', 'items');
        $printData = $this->printViewData($creditNote, $creditNote->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forCreditNote($creditNote, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'avoir',
            $creditNote->credit_note_number
        );
    }

    public function destroy(CreditNote $creditNote)
    {
        $creditNote->delete();

        return redirect()->route('credit-notes.index')->with('success', 'Avoir supprimé avec succès!');
    }

    protected function validateCreditNote(Request $request): array
    {
        return $request->validate([
            'client_id' => 'required|exists:clients,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'credit_note_date' => 'required|date',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
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

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function syncItems(CreditNote $creditNote, array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $computed = LineItemCalculator::compute($item);

            $creditNote->items()->create([
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
