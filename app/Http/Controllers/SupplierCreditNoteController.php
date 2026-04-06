<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierCreditNoteController extends Controller
{
    public function index()
    {
        $supplierCreditNotes = SupplierCreditNote::with('supplier')->latest()->paginate(15);
        return view('purchases.supplier-credit-notes.index', compact('supplierCreditNotes'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $invoices = SupplierInvoice::all();
        $creditNoteNumber = 'AVOIR-FOUR N°' . str_pad(SupplierCreditNote::count() + 1, 6, '0', STR_PAD_LEFT);
        return view('purchases.supplier-credit-notes.create', compact('suppliers', 'products', 'invoices', 'creditNoteNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'credit_note_date' => 'required|date',
            'invoice' => 'nullable|string',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
            'model' => 'nullable|string',
            'items' => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            $creditNote = SupplierCreditNote::create([
                'credit_note_number' => 'AVOIR-FOUR N°' . str_pad(SupplierCreditNote::count() + 1, 6, '0', STR_PAD_LEFT),
                'supplier_id' => $validated['supplier_id'],
                'credit_note_date' => $validated['credit_note_date'],
                'invoice' => $validated['invoice'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'model' => $validated['model'] ?? null,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $lineTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                $lineTotal += $lineTotal * (($item['tax_rate'] ?? 20) / 100);
                
                $creditNote->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'ref' => $item['ref'] ?? null,
                    'designation' => $item['designation'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'tax_rate' => $item['tax_rate'] ?? 20,
                    'line_total' => $lineTotal,
                ]);
                $subtotal += $lineTotal;
            }

            $creditNote->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            DB::commit();
            return redirect()->route('supplier-credit-notes.index')->with('success', 'Avoir fournisseur créé avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    public function destroy(SupplierCreditNote $supplierCreditNote)
    {
        $supplierCreditNote->delete();
        return redirect()->route('supplier-credit-notes.index')->with('success', 'Avoir supprimé!');
    }
}
