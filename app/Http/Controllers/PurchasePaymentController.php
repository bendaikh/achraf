<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PurchasePaymentController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = SupplierInvoice::query()
            ->with(['supplier'])
            ->withSum('payments as payments_sum', 'amount')
            ->latest('invoice_date');

        $this->applyTableSearch($query, $request, ['invoice_number', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'invoice_date');
        $this->applyPaymentStatusFilter($query, $request, 'supplier_invoice_payments', 'supplier_invoice_id', 'supplier_invoices');

        $invoices = $query->paginate(15)->withQueryString();

        $stats = $this->buildStats($request);

        return view('purchases.payments.index', compact('invoices', 'stats'));
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
        $query = SupplierInvoice::query();
        $this->applyTableSearch($query, $request, ['invoice_number', 'supplier.name']);
        $this->applyTableDateRange($query, $request, 'invoice_date');
        $this->applyPaymentStatusFilter($query, $request, 'supplier_invoice_payments', 'supplier_invoice_id', 'supplier_invoices');

        $invoices = $query->withSum('payments as payments_sum', 'amount')->get();

        $totalAmount = $invoices->sum(fn (SupplierInvoice $invoice) => (float) $invoice->total);
        $totalPaid = $invoices->sum(fn (SupplierInvoice $invoice) => (float) ($invoice->payments_sum ?? 0));
        $totalRemaining = $invoices->sum(fn (SupplierInvoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0)));

        return [
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'invoice_count' => $invoices->count(),
        ];
    }
}
