<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SalesPaymentController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = Invoice::query()
            ->with(['client', 'posSale'])
            ->withSum('payments as payments_sum', 'amount')
            ->latest('invoice_date');

        $this->applyTableSearch($query, $request, ['invoice_number', 'client.name', 'posSale.ticket_number']);
        $this->applyTableDateRange($query, $request, 'invoice_date');
        $this->applyPaymentStatusFilter($query, $request, 'invoice_payments', 'invoice_id', 'invoices');

        $invoices = $query->paginate(15)->withQueryString();

        $stats = $this->buildStats($request);

        return view('sales.payments.index', compact('invoices', 'stats'));
    }

    protected function applyPaymentStatusFilter(
        Builder $query,
        Request $request,
        string $paymentsTable,
        string $foreignKey,
        string $invoicesTable,
    ): void {
        if (! $request->filled('payment_status')) {
            return;
        }

        $paidSubquery = "(SELECT COALESCE(SUM(amount), 0) FROM {$paymentsTable} WHERE {$foreignKey} = {$invoicesTable}.id)";

        match ($request->input('payment_status')) {
            'paid' => $query->whereRaw("{$paidSubquery} >= {$invoicesTable}.total"),
            'partial' => $query->whereRaw("{$paidSubquery} > 0")
                ->whereRaw("{$paidSubquery} < {$invoicesTable}.total"),
            'unpaid' => $query->whereRaw("{$paidSubquery} = 0"),
            default => null,
        };
    }

    protected function buildStats(Request $request): array
    {
        $query = Invoice::query();
        $this->applyTableSearch($query, $request, ['invoice_number', 'client.name', 'posSale.ticket_number']);
        $this->applyTableDateRange($query, $request, 'invoice_date');
        $this->applyPaymentStatusFilter($query, $request, 'invoice_payments', 'invoice_id', 'invoices');

        $invoices = $query->withSum('payments as payments_sum', 'amount')->get();

        $totalAmount = $invoices->sum(fn (Invoice $invoice) => (float) $invoice->total);
        $totalPaid = $invoices->sum(fn (Invoice $invoice) => (float) ($invoice->payments_sum ?? 0));
        $totalRemaining = $invoices->sum(fn (Invoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0)));

        return [
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'invoice_count' => $invoices->count(),
        ];
    }
}
