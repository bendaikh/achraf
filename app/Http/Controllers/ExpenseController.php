<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\GeneratesCommercialPdf;
use App\Http\Controllers\Concerns\PreparesPrintView;
use App\Models\Client;
use App\Models\Expense;
use App\Support\CommercialDocumentView;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use FiltersIndexTables, GeneratesCommercialPdf, PreparesPrintView;

    public function index(Request $request)
    {
        $query = Expense::with(['client', 'supplier']);

        $this->applyTableSearch($query, $request, ['designation', 'reference', 'client.name', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'expense_date');
        $this->applyTableFilter($query, $request, 'expense_type', 'expense_type');
        $this->applyTableSort($query, $request, [
            'expense_date' => 'expense_date',
        ], 'expense_date', 'desc');

        $expenses = $this->paginateTable($query, $request);

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

    public function print(Expense $expense)
    {
        $expense->load('supplier', 'client');
        $view = CommercialDocumentView::forExpense($expense, []);
        $printData = $this->printViewData($expense, $view['doc']['items']);
        $view = CommercialDocumentView::forExpense($expense, $printData['taxes']);

        return view('purchases.expenses.print', array_merge(
            $view,
            $printData,
            compact('expense'),
            ['generatedBy' => auth()->user()?->name]
        ));
    }

    public function downloadPdf(Expense $expense)
    {
        $expense->load('supplier', 'client');
        $view = CommercialDocumentView::forExpense($expense, []);
        $printData = $this->printViewData($expense, $view['doc']['items']);
        $view = CommercialDocumentView::forExpense($expense, $printData['taxes']);
        $number = $view['doc']['number'];

        return $this->downloadCommercialPdf(
            array_merge(
                $view,
                $printData,
                ['generatedBy' => auth()->user()?->name]
            ),
            'depense',
            $number
        );
    }
}
