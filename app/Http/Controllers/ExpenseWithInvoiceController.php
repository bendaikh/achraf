<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\LoadsExpenseFormOptions;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseWithInvoiceController extends Controller
{
    use FiltersIndexTables, LoadsExpenseFormOptions;

    public function index(Request $request)
    {
        $query = Expense::where('expense_type', 'with_invoice')->with('supplier')->latest();

        $this->applyTableSearch($query, $request, ['designation', 'reference', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'expense_date');

        $expenses = $query->paginate(15)->withQueryString();

        return view('purchases.expenses-with-invoice.index', compact('expenses'));
    }

    public function create()
    {
        return view('purchases.expenses-with-invoice.create', $this->expenseFormOptions());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'designation' => 'required|string',
            'expense_category' => 'nullable|string',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string',
            'reference' => 'nullable|string',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'payment_method' => 'nullable|string',
            'account' => 'nullable|string',
            'tax_type' => 'required|string',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $validated['expense_type'] = 'with_invoice';

        if ($request->hasFile('invoice_file')) {
            $validated['invoice_file_path'] = $request->file('invoice_file')->store('expenses/invoices', 'public');
        }

        Expense::create($validated);

        return redirect()->route('expenses-with-invoice.index')->with('success', 'Dépense avec facture créée avec succès!');
    }

    public function show(Expense $expenseWithInvoice)
    {
        $expenseWithInvoice->load('supplier');

        return view('purchases.expenses-with-invoice.show', compact('expenseWithInvoice'));
    }

    public function edit(Expense $expenseWithInvoice)
    {
        return view('purchases.expenses-with-invoice.edit', array_merge(
            ['expense' => $expenseWithInvoice],
            $this->expenseFormOptions()
        ));
    }

    public function update(Request $request, Expense $expenseWithInvoice)
    {
        $validated = $request->validate([
            'designation' => 'required|string',
            'expense_category' => 'nullable|string',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string',
            'reference' => 'nullable|string',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'payment_method' => 'nullable|string',
            'account' => 'nullable|string',
            'tax_type' => 'required|string',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('invoice_file')) {
            if ($expenseWithInvoice->invoice_file_path) {
                Storage::disk('public')->delete($expenseWithInvoice->invoice_file_path);
            }
            $validated['invoice_file_path'] = $request->file('invoice_file')->store('expenses/invoices', 'public');
        }

        $expenseWithInvoice->update($validated);

        return redirect()->route('expenses-with-invoice.index')->with('success', 'Dépense avec facture modifiée avec succès!');
    }

    public function destroy(Expense $expenseWithInvoice)
    {
        if ($expenseWithInvoice->invoice_file_path) {
            Storage::disk('public')->delete($expenseWithInvoice->invoice_file_path);
        }
        $expenseWithInvoice->delete();

        return redirect()->route('expenses-with-invoice.index')->with('success', 'Dépense avec facture supprimée avec succès!');
    }
}
