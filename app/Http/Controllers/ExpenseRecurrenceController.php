<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExpenseRecurrenceController extends Controller
{
    public function markPaid(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->isPendingPayment(), 422, 'Cette dépense est déjà réglée.');

        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:255'],
            'account' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $expense->update([
            'payment_status' => Expense::PAYMENT_PAID,
            'payment_method' => $validated['payment_method'] ?? $expense->payment_method,
            'account' => $validated['account'] ?? $expense->account,
            'paid_at' => $validated['paid_at'] ?? now(),
        ]);

        return back()->with('success', 'Paiement enregistré. La trésorerie a été mise à jour.');
    }

    public function suspend(Expense $expense): RedirectResponse
    {
        $this->ensureTemplate($expense);
        $expense->update(['recurrence_status' => Expense::RECURRENCE_SUSPENDED]);

        return back()->with('success', 'Récurrence suspendue.');
    }

    public function resume(Expense $expense): RedirectResponse
    {
        $this->ensureTemplate($expense);
        abort_if($expense->recurrence_status === Expense::RECURRENCE_STOPPED, 422, 'Une récurrence arrêtée ne peut pas être reprise.');

        $expense->update(['recurrence_status' => Expense::RECURRENCE_ACTIVE]);

        return back()->with('success', 'Récurrence reprise.');
    }

    public function stop(Expense $expense): RedirectResponse
    {
        $this->ensureTemplate($expense);
        $expense->update([
            'recurrence_status' => Expense::RECURRENCE_STOPPED,
            'next_due_date' => null,
        ]);

        return back()->with('success', 'Récurrence arrêtée. Les dépenses déjà créées restent inchangées.');
    }

    private function ensureTemplate(Expense $expense): void
    {
        abort_unless($expense->isRecurrenceTemplate(), 404);
    }
}
