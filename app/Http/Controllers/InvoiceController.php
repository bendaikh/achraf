<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use FiltersIndexTables, PreparesPrintView;

    public function index(Request $request)
    {
        $query = Invoice::with('client')->latest();

        $this->applyTableSearch($query, $request, ['invoice_number', 'client.name']);
        $this->applyTableDateRange($query, $request, 'invoice_date');

        $invoices = $query->paginate(15)->withQueryString();

        return view('sales.invoices.index', compact('invoices'));
    }

    public function create()
    {
        $clients = Client::all();
        $products = Product::all();
        $invoiceNumber = DocumentNumberService::preview('facture');
        
        return view('sales.invoices.create', compact('clients', 'products', 'invoiceNumber'));
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
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $lineTotal -= $discount;
                $lineTotal += $lineTotal * ($item['tax_rate'] / 100);
                
                $invoice->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'ref' => $item['ref'] ?? null,
                    'designation' => $item['designation'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();
            return redirect()->route('invoices.index')->with('success', 'Facture créée avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la création de la facture: ' . $e->getMessage());
        }
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('client', 'items');
        return view('sales.invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $invoice->load('client', 'items');

        return view('sales.invoices.print', array_merge(
            compact('invoice'),
            $this->printViewData($invoice, $invoice->items)
        ));
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return redirect()->route('invoices.index')->with('success', 'Facture supprimée avec succès!');
    }
}
