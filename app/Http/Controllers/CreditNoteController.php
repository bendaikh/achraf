<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditNoteController extends Controller
{
    use FiltersIndexTables, PreparesPrintView;

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
        $clients = Client::all();
        $products = Product::all();
        $invoices = Invoice::all();
        $creditNoteNumber = DocumentNumberService::preview('avoir');
        
        return view('sales.credit-notes.create', compact('clients', 'products', 'invoices', 'creditNoteNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
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
        ]);

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

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $lineTotal -= $discount;
                $lineTotal += $lineTotal * ($item['tax_rate'] / 100);
                
                $creditNote->items()->create([
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

            $creditNote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();
            return redirect()->route('credit-notes.index')->with('success', 'Avoir créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la création de l\'avoir: ' . $e->getMessage());
        }
    }

    public function show(CreditNote $creditNote)
    {
        $creditNote->load('client', 'invoice', 'items');
        return view('sales.credit-notes.show', compact('creditNote'));
    }

    public function print(CreditNote $creditNote)
    {
        $creditNote->load('client', 'invoice', 'items');

        return view('sales.credit-notes.print', array_merge(
            compact('creditNote'),
            $this->printViewData($creditNote, $creditNote->items)
        ));
    }

    public function destroy(CreditNote $creditNote)
    {
        $creditNote->delete();
        return redirect()->route('credit-notes.index')->with('success', 'Avoir supprimé avec succès!');
    }
}
