<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use Carbon\Carbon;

class FinancialManagementService
{
    /**
     * @return array<string, mixed>
     */
    public function getOverview(?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $dateFrom ??= Carbon::now()->startOfMonth();
        $dateTo ??= Carbon::now()->endOfDay();

        $posRevenue = (float) PosSale::query()
            ->where('status', PosSale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->sum('total');

        $invoiceRevenue = (float) Invoice::query()
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->sum('total');

        $expensesTotal = (float) Expense::query()
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $expensesWithInvoice = (float) Expense::query()
            ->where('expense_type', 'with_invoice')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $expensesWithoutInvoice = (float) Expense::query()
            ->where('expense_type', 'without_invoice')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $clientPayments = (float) InvoicePayment::query()
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $supplierPayments = (float) SupplierInvoicePayment::query()
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $supplierPurchases = (float) SupplierInvoice::query()
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->sum('total');

        $revenue = $posRevenue + $invoiceRevenue;
        $netResult = $revenue - $expensesTotal;
        $netCashFlow = $clientPayments + $posRevenue - $supplierPayments - $expensesTotal;

        return [
            'revenue' => $revenue,
            'revenue_pos' => $posRevenue,
            'revenue_invoices' => $invoiceRevenue,
            'expenses' => $expensesTotal,
            'expenses_with_invoice' => $expensesWithInvoice,
            'expenses_without_invoice' => $expensesWithoutInvoice,
            'supplier_purchases' => $supplierPurchases,
            'client_payments' => $clientPayments,
            'supplier_payments' => $supplierPayments,
            'net_result' => $netResult,
            'net_cash_flow' => $netCashFlow,
            'client_receivables' => $this->getClientReceivables(),
            'supplier_payables' => $this->getSupplierPayables(),
        ];
    }

    public function getClientReceivables(): float
    {
        return (float) Invoice::query()
            ->withSum('payments as payments_sum', 'amount')
            ->get()
            ->sum(fn (Invoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0)));
    }

    public function getSupplierPayables(): float
    {
        return (float) SupplierInvoice::query()
            ->withSum('payments as payments_sum', 'amount')
            ->get()
            ->sum(fn (SupplierInvoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0)));
    }

    /**
     * @return array{labels: list<string>, revenue: list<float>, expenses: list<float>, cash_in: list<float>, cash_out: list<float>}
     */
    public function getMonthlyChart(int $months = 6, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $dateTo ??= Carbon::now();
        $dateFrom ??= $dateTo->copy()->subMonths($months - 1)->startOfMonth();

        $labels = [];
        $revenue = [];
        $expenses = [];
        $cashIn = [];
        $cashOut = [];

        $cursor = $dateFrom->copy()->startOfMonth();
        $end = $dateTo->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            $start = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $labels[] = $cursor->translatedFormat('M Y');

            $salesRevenue = (float) PosSale::query()
                ->where('status', PosSale::STATUS_COMPLETED)
                ->whereBetween('sold_at', [$start, $monthEnd])
                ->sum('total');

            $invoiceRevenue = (float) Invoice::query()
                ->whereBetween('invoice_date', [$start, $monthEnd])
                ->sum('total');

            $revenue[] = round($salesRevenue + $invoiceRevenue, 2);

            $expenses[] = round((float) Expense::query()
                ->whereBetween('expense_date', [$start, $monthEnd])
                ->sum('amount'), 2);

            $clientPaymentsMonth = (float) InvoicePayment::query()
                ->whereBetween('payment_date', [$start, $monthEnd])
                ->sum('amount');

            $cashIn[] = round($salesRevenue + $clientPaymentsMonth, 2);

            $supplierPaymentsMonth = (float) SupplierInvoicePayment::query()
                ->whereBetween('payment_date', [$start, $monthEnd])
                ->sum('amount');

            $expensesMonth = (float) Expense::query()
                ->whereBetween('expense_date', [$start, $monthEnd])
                ->sum('amount');

            $cashOut[] = round($supplierPaymentsMonth + $expensesMonth, 2);

            $cursor->addMonth();
        }

        return compact('labels', 'revenue', 'expenses', 'cashIn', 'cashOut');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRecentTransactions(int $limit = 12, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $clientPayments = InvoicePayment::with('invoice.client')
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('payment_date', [$dateFrom, $dateTo]))
            ->latest('payment_date')
            ->limit($limit)
            ->get()
            ->map(fn (InvoicePayment $payment) => [
                'date' => $payment->payment_date,
                'label' => 'Encaissement client',
                'reference' => $payment->invoice?->invoice_number ?? '—',
                'party' => $payment->invoice?->client?->name ?? '—',
                'amount' => (float) $payment->amount,
                'direction' => 'in',
                'url' => $payment->invoice ? route('invoices.payments.index', $payment->invoice) : null,
            ]);

        $supplierPayments = SupplierInvoicePayment::with('supplierInvoice.supplier')
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('payment_date', [$dateFrom, $dateTo]))
            ->latest('payment_date')
            ->limit($limit)
            ->get()
            ->map(fn (SupplierInvoicePayment $payment) => [
                'date' => $payment->payment_date,
                'label' => 'Paiement fournisseur',
                'reference' => $payment->supplierInvoice?->invoice_number ?? '—',
                'party' => $payment->supplierInvoice?->supplier?->name ?? '—',
                'amount' => (float) $payment->amount,
                'direction' => 'out',
                'url' => $payment->supplierInvoice ? route('supplier-invoices.payments.index', $payment->supplierInvoice) : null,
            ]);

        $expenses = Expense::with(['supplier', 'client'])
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('expense_date', [$dateFrom, $dateTo]))
            ->latest('expense_date')
            ->limit($limit)
            ->get()
            ->map(fn (Expense $expense) => [
                'date' => $expense->expense_date,
                'label' => $expense->expense_type === 'with_invoice' ? 'Dépense avec facture' : 'Dépense sans facture',
                'reference' => $expense->reference ?? $expense->designation,
                'party' => $expense->supplier?->name ?? $expense->client?->name ?? '—',
                'amount' => (float) $expense->amount,
                'direction' => 'out',
                'url' => $expense->expense_type === 'with_invoice'
                    ? route('expenses-with-invoice.show', $expense)
                    : route('expenses-without-invoice.show', $expense),
            ]);

        return $clientPayments
            ->concat($supplierPayments)
            ->concat($expenses)
            ->sortByDesc('date')
            ->take($limit)
            ->map(fn (array $item) => [
                ...$item,
                'date_formatted' => $item['date']?->format('d/m/Y'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{count: int, total: float, items: list<array<string, mixed>>}
     */
    public function getOutstandingClientInvoices(int $limit = 8): array
    {
        $invoices = Invoice::with(['client'])
            ->withSum('payments as payments_sum', 'amount')
            ->latest('invoice_date')
            ->get()
            ->filter(fn (Invoice $invoice) => (float) ($invoice->payments_sum ?? 0) < (float) $invoice->total);

        $items = $invoices->take($limit)->map(fn (Invoice $invoice) => [
            'number' => $invoice->invoice_number,
            'party' => $invoice->client?->name ?? '—',
            'total' => (float) $invoice->total,
            'paid' => (float) ($invoice->payments_sum ?? 0),
            'remaining' => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0)),
            'date' => $invoice->invoice_date?->format('d/m/Y'),
            'due_date' => $invoice->due_date?->format('d/m/Y'),
            'url' => route('invoices.payments.index', $invoice),
        ])->values()->all();

        return [
            'count' => $invoices->count(),
            'total' => $invoices->sum(fn (Invoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0))),
            'items' => $items,
        ];
    }

    /**
     * @return array{count: int, total: float, items: list<array<string, mixed>>}
     */
    public function getOutstandingSupplierInvoices(int $limit = 8): array
    {
        $invoices = SupplierInvoice::with(['supplier'])
            ->withSum('payments as payments_sum', 'amount')
            ->latest('invoice_date')
            ->get()
            ->filter(fn (SupplierInvoice $invoice) => (float) ($invoice->payments_sum ?? 0) < (float) $invoice->total);

        $items = $invoices->take($limit)->map(fn (SupplierInvoice $invoice) => [
            'number' => $invoice->invoice_number,
            'party' => $invoice->supplier?->name ?? '—',
            'total' => (float) $invoice->total,
            'paid' => (float) ($invoice->payments_sum ?? 0),
            'remaining' => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0)),
            'date' => $invoice->invoice_date?->format('d/m/Y'),
            'due_date' => $invoice->due_date?->format('d/m/Y'),
            'url' => route('supplier-invoices.payments.index', $invoice),
        ])->values()->all();

        return [
            'count' => $invoices->count(),
            'total' => $invoices->sum(fn (SupplierInvoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->payments_sum ?? 0))),
            'items' => $items,
        ];
    }
}
