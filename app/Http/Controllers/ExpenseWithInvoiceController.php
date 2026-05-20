<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseWithInvoiceController extends Controller
{
    public function index()
    {
        $expenses = Expense::where('expense_type', 'with_invoice')->with('client')->latest()->paginate(15);
        return view('purchases.expenses-with-invoice.index', compact('expenses'));
    }

    public function create()
    {
        $clients = Client::all();
        return view('purchases.expenses-with-invoice.create', compact('clients'));
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
            'client_id' => 'nullable|exists:clients,id',
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
        $expenseWithInvoice->load('client');
        return view('purchases.expenses-with-invoice.show', compact('expenseWithInvoice'));
    }

    public function edit(Expense $expenseWithInvoice)
    {
        $clients = Client::all();
        return view('purchases.expenses-with-invoice.edit', compact('expenseWithInvoice', 'clients'));
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
            'client_id' => 'nullable|exists:clients,id',
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
