<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierInvoiceController extends Controller
{
    public function index()
    {
        $invoices = SupplierInvoice::with('supplier')->latest()->paginate(15);
        return view('purchases.supplier-invoices.index', compact('invoices'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $products = Product::all();
        $invoiceNumber = 'FSI-' . date('Y') . '/' . str_pad(SupplierInvoice::whereYear('created_at', date('Y'))->count() + 1, 6, '0', STR_PAD_LEFT);
        
        return view('purchases.supplier-invoices.create', compact('suppliers', 'products', 'invoiceNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
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
            $invoice = SupplierInvoice::create([
                'invoice_number' => 'FSI-' . date('Y') . '/' . str_pad(SupplierInvoice::whereYear('created_at', date('Y'))->count() + 1, 6, '0', STR_PAD_LEFT),
                'supplier_id' => $validated['supplier_id'],
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
            return redirect()->route('supplier-invoices.index')->with('success', 'Facture fournisseur créée avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la création de la Facture fournisseur: ' . $e->getMessage());
        }
    }

    public function show(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load('supplier', 'items');
        return view('purchases.supplier-invoices.show', compact('supplierInvoice'));
    }

    public function destroy(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->delete();
        return redirect()->route('supplier-invoices.index')->with('success', 'Facture fournisseur supprimée avec succès!');
    }
}
