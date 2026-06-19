<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\DocumentNumberService;
use App\Services\StockMovementService;
use App\Support\CommercialDocumentView;
use App\Support\LineItemCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function __construct(
        protected StockMovementService $stockMovement
    ) {}

    public function index(Request $request)
    {
        $query = Invoice::with(['client', 'posSale', 'items'])->latest();

        $this->applyTableSearch($query, $request, ['invoice_number', 'client.name', 'posSale.ticket_number']);
        $this->applyTableDateRange($query, $request, 'invoice_date');

        $invoices = $query->paginate(15)->withQueryString();

        return view('sales.invoices.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::all();
        $invoiceNumber = DocumentNumberService::preview('facture');
        $pricesAreTtc = \App\Models\Setting::getShopifyPriceType() === 'ttc';

        return view('sales.invoices.create', compact('products', 'invoiceNumber', 'pricesAreTtc'));
    }

    public function byClient(\App\Models\Client $client)
    {
        $invoices = Invoice::query()
            ->where('client_id', $client->id)
            ->orderByDesc('invoice_date')
            ->get(['id', 'invoice_number'])
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'label' => $invoice->invoice_number,
            ]);

        return response()->json(['invoices' => $invoices]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
            'commercial_contact' => 'nullable|string',
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
            $invoice = Invoice::create([
                'invoice_number' => DocumentNumberService::generate('facture'),
                'client_id' => $validated['client_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'commercial_contact' => $validated['commercial_contact'] ?? null,
                'model' => $validated['model'] ?? null,
                'matricule' => $validated['matricule'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
                'subtotal' => 0,
                'discount' => 0,
                'adjustment' => 0,
                'total' => 0,
                'payment_status' => Invoice::PAYMENT_UNPAID,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item);

                $invoice->items()->create([
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

            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            $this->stockMovement->decreaseForSale(
                $validated['items'],
                $validated['stock_location']
            );

            DB::commit();
            return redirect()->route('invoices.index')->with('success', 'Facture créée avec succès!');
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la création de la facture: ' . $e->getMessage());
        }
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('client', 'items', 'posSale', 'payments');
        return view('sales.invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load('client', 'items');
        $products = Product::all();
        $existingItems = $invoice->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'ref' => $item->ref,
            'designation' => $item->designation,
            'quantity' => $item->quantity,
            'unit_price' => $item->display_unit_price_ht,
            'tax_rate' => $item->tax_rate,
            'discount' => $item->discount,
            'discount_type' => $item->discount_type ?? 'fixed',
        ])->values();

        $pricesAreTtc = \App\Models\Setting::getShopifyPriceType() === 'ttc';

        return view('sales.invoices.edit', compact('invoice', 'products', 'existingItems', 'pricesAreTtc'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
            'commercial_contact' => 'nullable|string',
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
            $invoice->update([
                'client_id' => $validated['client_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'commercial_contact' => $validated['commercial_contact'] ?? null,
                'model' => $validated['model'] ?? null,
                'matricule' => $validated['matricule'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
            ]);

            $invoice->items()->delete();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $computed = LineItemCalculator::compute($item);

                $invoice->items()->create([
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

            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();

            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Facture modifiée avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Erreur lors de la modification de la facture: ' . $e->getMessage());
        }
    }

    public function print(Invoice $invoice)
    {
        $invoice->load('client', 'items');
        $printData = $this->printViewData($invoice, $invoice->items);

        return view('sales.invoices.print', array_merge(
            CommercialDocumentView::forInvoice($invoice, $printData['taxes']),
            $printData,
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(Invoice $invoice)
    {
        $invoice->load('client', 'items');
        $printData = $this->printViewData($invoice, $invoice->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forInvoice($invoice, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'facture',
            $invoice->invoice_number
        );
    }

    public function updatePaymentStatus(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'payment_status' => 'required|in:unpaid,paid',
        ]);

        $invoice->update(['payment_status' => $validated['payment_status']]);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Statut de paiement mis à jour.');
    }

    public function destroy(Invoice $invoice)
    {
        DB::beginTransaction();
        try {
            $invoice->load('items');
            $this->stockMovement->increaseFromItems($invoice->items, $invoice->stock_location);
            $invoice->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Erreur lors de la suppression : '.$e->getMessage());
        }

        return redirect()->route('invoices.index')->with('success', 'Facture supprimée avec succès!');
    }
}
