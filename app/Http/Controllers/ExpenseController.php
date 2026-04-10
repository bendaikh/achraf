<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::with('client')->latest()->paginate(15);
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
