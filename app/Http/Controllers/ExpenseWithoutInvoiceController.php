<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AttachesManagedDocuments;
use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Concerns\HandlesExpenseRecurrence;
use App\Http\Controllers\Concerns\LoadsExpenseFormOptions;
use App\Models\Expense;
use App\Services\RecurringExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseWithoutInvoiceController extends Controller
{
    use AttachesManagedDocuments, FiltersIndexTables, HandlesExpenseRecurrence, LoadsExpenseFormOptions;

    public function index(Request $request)
    {
        $query = Expense::where('expense_type', 'without_invoice')->with('client');

        $this->applyTableSearch($query, $request, ['designation', 'reference', 'client.name']);
        $this->applyTableDateRange($query, $request, 'expense_date');
        $this->applyRecurringFilter($query, $request);
        $this->applyTableSort($query, $request, [
            'expense_date' => 'expense_date',
        ], 'expense_date', 'desc');

        $expenses = $this->paginateTable($query, $request);

        return view('purchases.expenses-without-invoice.index', compact('expenses'));
    }

    public function create()
    {
        return view('purchases.expenses-without-invoice.create', $this->expenseFormOptions());
    }

    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
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
        ], $this->recurrenceRules()));

        $validated['expense_type'] = 'without_invoice';
        $validated = $this->prepareRecurrence($request, $validated);
        unset($validated['invoice_file']);

        $expense = Expense::create($validated);
        $this->attachManagedDocument('expenses-without-invoice', $expense, $request->file('invoice_file'));

        return redirect()->route('expenses-without-invoice.index')->with('success', 'Dépense sans facture créée avec succès!');
    }

    public function show(Expense $expenseWithoutInvoice)
    {
        $this->ensureExpenseType($expenseWithoutInvoice, 'without_invoice');
        $expenseWithoutInvoice->load(['client', 'recurrenceParent']);

        return view('purchases.expenses-without-invoice.show', [
            'expense' => $expenseWithoutInvoice,
        ]);
    }

    public function edit(Expense $expenseWithoutInvoice)
    {
        $this->ensureExpenseType($expenseWithoutInvoice, 'without_invoice');
        $expenseWithoutInvoice->load('client');

        return view('purchases.expenses-without-invoice.edit', array_merge(
            ['expense' => $expenseWithoutInvoice],
            $this->expenseFormOptions()
        ));
    }

    public function update(
        Request $request,
        Expense $expenseWithoutInvoice,
        RecurringExpenseService $recurringExpenses
    ) {
        $this->ensureExpenseType($expenseWithoutInvoice, 'without_invoice');

        $rules = [
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
        ];

        if ($expenseWithoutInvoice->recurrence_parent_id === null) {
            $rules = array_merge($rules, $this->recurrenceRules());
        }

        $validated = $request->validate($rules);
        $justificatif = $request->file('invoice_file');
        unset($validated['invoice_file']);

        if ($expenseWithoutInvoice->isRecurrenceTemplate()) {
            if (! $request->boolean('is_recurring')) {
                $expenseWithoutInvoice->update([
                    'recurrence_status' => Expense::RECURRENCE_STOPPED,
                    'next_due_date' => null,
                ]);

                return redirect()->route('expenses-without-invoice.index')
                    ->with('success', 'Récurrence arrêtée. L’historique reste inchangé.');
            }

            $validated['expense_type'] = 'without_invoice';
            $validated = $this->prepareRecurrence($request, $validated);
            $this->createFutureRecurrenceVersion($expenseWithoutInvoice, $validated, $recurringExpenses);

            return redirect()->route('expenses-without-invoice.index')
                ->with('success', 'La modification s’appliquera aux prochaines échéances. L’historique reste inchangé.');
        }

        if ($expenseWithoutInvoice->recurrence_parent_id === null) {
            $validated = $this->prepareRecurrence($request, $validated);
        }

        $expenseWithoutInvoice->update($validated);
        if (! $expenseWithoutInvoice->isRecurrenceTemplate()) {
            $this->attachManagedDocument('expenses-without-invoice', $expenseWithoutInvoice, $justificatif);
        }

        return redirect()->route('expenses-without-invoice.index')->with('success', 'Dépense sans facture modifiée avec succès!');
    }

    public function destroy(Expense $expenseWithoutInvoice)
    {
        $this->ensureExpenseType($expenseWithoutInvoice, 'without_invoice');

        if ($expenseWithoutInvoice->invoice_file_path) {
            Storage::disk('public')->delete($expenseWithoutInvoice->invoice_file_path);
        }

        $expenseWithoutInvoice->delete();

        return redirect()->route('expenses-without-invoice.index')->with('success', 'Dépense sans facture supprimée avec succès!');
    }
}
