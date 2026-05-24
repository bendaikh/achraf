<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Client;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = Expense::with(['client', 'supplier'])->latest();

        $this->applyTableSearch($query, $request, ['designation', 'reference', 'client.name', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'expense_date');
        $this->applyTableFilter($query, $request, 'expense_type', 'expense_type');

        $expenses = $query->paginate(15)->withQueryString();

        return view('purchases.expenses.index', compact('expenses'));
    }

    public function create()
    {
        $clients = Client::all();
        return view('purchases.expenses.create', compact('clients'));
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

        Expense::create($validated);

        return redirect()->route('expenses.index')->with('success', 'Dépense créée avec succès!');
    }

    public function show(Expense $expense)
    {
        $expense->load('client');
        return view('purchases.expenses.show', compact('expense'));
    }

    public function edit(Expense $expense)
    {
        $clients = Client::all();
        return view('purchases.expenses.edit', compact('expense', 'clients'));
    }

    public function update(Request $request, Expense $expense)
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

        $expense->update($validated);

        return redirect()->route('expenses.index')->with('success', 'Dépense modifiée avec succès!');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Dépense supprimée avec succès!');
    }
}
