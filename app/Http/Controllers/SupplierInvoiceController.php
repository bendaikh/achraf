<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Product;
use App\Services\StockMovementService;
use App\Support\CommercialDocumentView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SupplierInvoiceController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function __construct(
        protected StockMovementService $stockMovement
    ) {}

    public function index(Request $request)
    {
        $query = SupplierInvoice::with('supplier')->latest();

        $this->applyTableSearch($query, $request, ['invoice_number', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'invoice_date');

        $invoices = $query->paginate(15)->withQueryString();

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
            'invoice_number' => 'required|string|unique:supplier_invoices,invoice_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
            'commercial_contact' => 'nullable|string',
            'model' => 'nullable|string',
            'remarks' => 'nullable|string',
            'conditions' => 'nullable|string',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
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
            $invoiceFilePath = null;
            if ($request->hasFile('invoice_file')) {
                $invoiceFilePath = $request->file('invoice_file')->store('supplier_invoices', 'public');
            }

            $invoice = SupplierInvoice::create([
                'invoice_number' => $validated['invoice_number'],
                'supplier_id' => $validated['supplier_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'commercial_contact' => $validated['commercial_contact'] ?? null,
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
                'invoice_file_path' => $invoiceFilePath,
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

            $invoice->load('items');
            $this->stockMovement->increaseFromItems(
                $invoice->items,
                $validated['stock_location']
            );

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

    public function edit(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load('supplier', 'items');
        $suppliers = Supplier::all();
        $products = Product::all();
        return view('purchases.supplier-invoices.edit', compact('supplierInvoice', 'suppliers', 'products'));
    }

    public function update(Request $request, SupplierInvoice $supplierInvoice)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:supplier_invoices,invoice_number,' . $supplierInvoice->id,
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'required|string',
            'stock_location' => 'required|string',
            'commercial_contact' => 'nullable|string',
            'model' => 'nullable|string',
            'remarks' => 'nullable|string',
            'conditions' => 'nullable|string',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
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
            if ($request->hasFile('invoice_file')) {
                if ($supplierInvoice->invoice_file_path) {
                    Storage::disk('public')->delete($supplierInvoice->invoice_file_path);
                }
                $validated['invoice_file_path'] = $request->file('invoice_file')->store('supplier_invoices', 'public');
            }

            $supplierInvoice->update([
                'invoice_number' => $validated['invoice_number'],
                'supplier_id' => $validated['supplier_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'],
                'stock_location' => $validated['stock_location'],
                'commercial_contact' => $validated['commercial_contact'] ?? null,
                'model' => $validated['model'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'conditions' => $validated['conditions'] ?? null,
                'invoice_file_path' => $validated['invoice_file_path'] ?? $supplierInvoice->invoice_file_path,
            ]);

            $supplierInvoice->items()->delete();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $lineTotal -= $discount;
                $lineTotal += $lineTotal * ($item['tax_rate'] / 100);
                
                $supplierInvoice->items()->create([
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

            $supplierInvoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + ($request->adjustment ?? 0),
            ]);

            DB::commit();
            return redirect()->route('supplier-invoices.index')->with('success', 'Facture fournisseur modifiée avec succès!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur lors de la modification de la Facture fournisseur: ' . $e->getMessage());
        }
    }

    public function print(SupplierInvoice $supplierInvoice)
    {
        if ($supplierInvoice->invoice_file_path && Storage::disk('public')->exists($supplierInvoice->invoice_file_path)) {
            $path = Storage::disk('public')->path($supplierInvoice->invoice_file_path);
            $filename = $supplierInvoice->invoice_number . '.' . pathinfo($path, PATHINFO_EXTENSION);

            return response()->file($path, [
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        $supplierInvoice->load('supplier', 'items');
        $printData = $this->printViewData($supplierInvoice, $supplierInvoice->items);

        return view('purchases.supplier-invoices.print', array_merge(
            CommercialDocumentView::forSupplierInvoice($supplierInvoice, $printData['taxes']),
            $printData,
            compact('supplierInvoice'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(SupplierInvoice $supplierInvoice)
    {
        $supplierInvoice->load('supplier', 'items');
        $printData = $this->printViewData($supplierInvoice, $supplierInvoice->items);

        return $this->downloadCommercialPdf(
            array_merge(
                CommercialDocumentView::forSupplierInvoice($supplierInvoice, $printData['taxes']),
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'facture-fournisseur',
            $supplierInvoice->invoice_number
        );
    }

    public function destroy(SupplierInvoice $supplierInvoice)
    {
        if ($supplierInvoice->invoice_file_path) {
            Storage::disk('public')->delete($supplierInvoice->invoice_file_path);
        }
        $supplierInvoice->delete();
        return redirect()->route('supplier-invoices.index')->with('success', 'Facture fournisseur supprimée avec succès!');
    }
}
