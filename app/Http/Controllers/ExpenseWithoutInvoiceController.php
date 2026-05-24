<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsExpenseFormOptions;
use App\Models\Client;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseWithoutInvoiceController extends Controller
{
    use LoadsExpenseFormOptions;

    public function index()
    {
        $expenses = Expense::where('expense_type', 'without_invoice')->with('client')->latest()->paginate(15);

        return view('purchases.expenses-without-invoice.index', compact('expenses'));
    }

    public function create()
    {
        $clients = Client::orderBy('name')->get();

        return view('purchases.expenses-without-invoice.create', array_merge(
            compact('clients'),
            $this->expenseFormOptions()
        ));
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
        ]);

        $validated['expense_type'] = 'without_invoice';

        Expense::create($validated);

        return redirect()->route('expenses-without-invoice.index')->with('success', 'Dépense sans facture créée avec succès!');
    }

    public function show(Expense $expenseWithoutInvoice)
    {
        $expenseWithoutInvoice->load('client');

        return view('purchases.expenses-without-invoice.show', compact('expenseWithoutInvoice'));
    }

    public function edit(Expense $expenseWithoutInvoice)
    {
        $clients = Client::orderBy('name')->get();

        return view('purchases.expenses-without-invoice.edit', array_merge(
            ['expense' => $expenseWithoutInvoice, 'clients' => $clients],
            $this->expenseFormOptions()
        ));
    }

    public function update(Request $request, Expense $expenseWithoutInvoice)
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
        ]);

        $expenseWithoutInvoice->update($validated);

        return redirect()->route('expenses-without-invoice.index')->with('success', 'Dépense sans facture modifiée avec succès!');
    }

    public function destroy(Expense $expenseWithoutInvoice)
    {
        $expenseWithoutInvoice->delete();

        return redirect()->route('expenses-without-invoice.index')->with('success', 'Dépense sans facture supprimée avec succès!');
    }
}
