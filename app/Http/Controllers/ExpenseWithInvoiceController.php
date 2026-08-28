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

class ExpenseWithInvoiceController extends Controller
{
    use AttachesManagedDocuments, FiltersIndexTables, HandlesExpenseRecurrence, LoadsExpenseFormOptions;

    public function index(Request $request)
    {
        $query = Expense::where('expense_type', 'with_invoice')->with('supplier');

        $this->applyTableSearch($query, $request, ['designation', 'reference', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'expense_date');
        $this->applyRecurringFilter($query, $request);
        $this->applyTableSort($query, $request, [
            'expense_date' => 'expense_date',
            'reference' => 'reference',
        ], 'expense_date', 'desc');

        $expenses = $this->paginateTable($query, $request);

        return view('purchases.expenses-with-invoice.index', compact('expenses'));
    }

    public function create()
    {
        return view('purchases.expenses-with-invoice.create', $this->expenseFormOptions());
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
            'supplier_id' => 'nullable|exists:suppliers,id',
            'payment_method' => 'nullable|string',
            'account' => 'nullable|string',
            'tax_type' => 'required|string',
            'invoice_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], $this->recurrenceRules()));

        $validated['expense_type'] = 'with_invoice';
        $validated = $this->prepareRecurrence($request, $validated);
        unset($validated['invoice_file']);

        $expense = Expense::create($validated);
        $this->attachManagedDocument('expenses-with-invoice', $expense, $request->file('invoice_file'));

        return redirect()->route('expenses-with-invoice.index')->with('success', 'Dépense avec facture créée avec succès!');
    }

    public function show(Expense $expenseWithInvoice)
    {
        $this->ensureExpenseType($expenseWithInvoice, 'with_invoice');
        $expenseWithInvoice->load(['supplier', 'recurrenceParent']);

        return view('purchases.expenses-with-invoice.show', [
            'expense' => $expenseWithInvoice,
        ]);
    }

    public function edit(Expense $expenseWithInvoice)
    {
        $this->ensureExpenseType($expenseWithInvoice, 'with_invoice');

        return view('purchases.expenses-with-invoice.edit', array_merge(
            ['expense' => $expenseWithInvoice],
            $this->expenseFormOptions()
        ));
    }

    public function update(
        Request $request,
        Expense $expenseWithInvoice,
        RecurringExpenseService $recurringExpenses
    ) {
        $this->ensureExpenseType($expenseWithInvoice, 'with_invoice');

        $rules = [
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
        ];

        if ($expenseWithInvoice->recurrence_parent_id === null) {
            $rules = array_merge($rules, $this->recurrenceRules());
        }

        $validated = $request->validate($rules);
        $invoiceFile = $request->file('invoice_file');
        unset($validated['invoice_file']);

        if ($expenseWithInvoice->isRecurrenceTemplate()) {
            if (! $request->boolean('is_recurring')) {
                $expenseWithInvoice->update([
                    'recurrence_status' => Expense::RECURRENCE_STOPPED,
                    'next_due_date' => null,
                ]);

                return redirect()->route('expenses-with-invoice.index')
                    ->with('success', 'Récurrence arrêtée. L’historique reste inchangé.');
            }

            $validated['expense_type'] = 'with_invoice';
            $validated = $this->prepareRecurrence($request, $validated);
            $this->createFutureRecurrenceVersion($expenseWithInvoice, $validated, $recurringExpenses);

            return redirect()->route('expenses-with-invoice.index')
                ->with('success', 'La modification s’appliquera aux prochaines échéances. L’historique reste inchangé.');
        }

        if ($expenseWithInvoice->recurrence_parent_id === null) {
            $validated = $this->prepareRecurrence($request, $validated);
        }

        $expenseWithInvoice->update($validated);
        if (! $expenseWithInvoice->isRecurrenceTemplate()) {
            $this->attachManagedDocument('expenses-with-invoice', $expenseWithInvoice, $invoiceFile);
        }

        return redirect()->route('expenses-with-invoice.index')->with('success', 'Dépense avec facture modifiée avec succès!');
    }

    public function destroy(Expense $expenseWithInvoice)
    {
        $this->ensureExpenseType($expenseWithInvoice, 'with_invoice');

        if ($expenseWithInvoice->invoice_file_path) {
            Storage::disk('public')->delete($expenseWithInvoice->invoice_file_path);
        }
        $expenseWithInvoice->delete();

        return redirect()->route('expenses-with-invoice.index')->with('success', 'Dépense avec facture supprimée avec succès!');
    }
}
