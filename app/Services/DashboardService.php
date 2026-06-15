<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getStats(?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $dateFrom ??= Carbon::now()->startOfMonth();
        $dateTo ??= Carbon::now()->endOfDay();

        $completedSales = PosSale::query()->where('status', PosSale::STATUS_COMPLETED);

        $revenuePeriod = (float) (clone $completedSales)
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->sum('total');

        $invoiceRevenuePeriod = (float) Invoice::query()
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->sum('total');

        $expensesPeriod = (float) Expense::query()
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $startOfYear = $dateTo->copy()->startOfYear();
        $revenueYear = (float) (clone $completedSales)
            ->where('sold_at', '>=', $startOfYear)
            ->sum('total');
        $expensesYear = (float) Expense::query()
            ->where('expense_date', '>=', $startOfYear)
            ->sum('amount');

        $supplierPaidPeriod = (float) SupplierInvoicePayment::query()
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $openSupplierBalance = (float) SupplierInvoice::query()
            ->withSum('payments as payments_sum', 'amount')
            ->get()
            ->sum(fn (SupplierInvoice $inv) => max(0, (float) $inv->total - (float) ($inv->payments_sum ?? 0)));

        return [
            'clients_count' => Client::count(),
            'suppliers_count' => Supplier::count(),
            'products_count' => Product::count(),
            'low_stock_count' => Product::lowStock()->where('stock_quantity', '>', 0)->count(),
            'out_of_stock_count' => Product::where('stock_quantity', '<=', 0)->count(),
            'orders_total' => PosSale::count(),
            'orders_month' => PosSale::whereBetween('sold_at', [$dateFrom, $dateTo])->count(),
            'orders_completed' => PosSale::where('status', PosSale::STATUS_COMPLETED)->count(),
            'invoices_count' => Invoice::count(),
            'invoices_month' => Invoice::whereBetween('invoice_date', [$dateFrom, $dateTo])->count(),
            'quotes_count' => Quote::count(),
            'quotes_pending' => Quote::whereNotIn('status', ['accepté', 'accepte', 'refusé', 'refuse', 'annulé', 'annule'])->count(),
            'revenue_month' => $revenuePeriod + $invoiceRevenuePeriod,
            'revenue_year' => $revenueYear + (float) Invoice::where('invoice_date', '>=', $startOfYear)->sum('total'),
            'expenses_month' => $expensesPeriod,
            'expenses_year' => $expensesYear,
            'profit_month' => ($revenuePeriod + $invoiceRevenuePeriod) - $expensesPeriod,
            'supplier_payments_month' => $supplierPaidPeriod,
            'supplier_balance_due' => $openSupplierBalance,
            'shopify_orders' => PosSale::where('source', 'shopify')->count(),
            'pos_orders' => PosSale::where('source', 'pos')->orWhereNull('source')->count(),
        ];
    }

    /**
     * @return array{labels: list<string>, revenue: list<float>, expenses: list<float>}
     */
    public function getMonthlyChart(int $months = 6, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $dateTo ??= Carbon::now();
        $dateFrom ??= $dateTo->copy()->subMonths($months - 1)->startOfMonth();

        $labels = [];
        $revenue = [];
        $expenses = [];

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

            $cursor->addMonth();
        }

        return compact('labels', 'revenue', 'expenses');
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function getPaymentMethodsChart(?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $dateFrom ??= Carbon::now()->subMonths(3);
        $dateTo ??= Carbon::now();

        $rows = PosSale::query()
            ->where('status', PosSale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->select('payment_method', DB::raw('COUNT(*) as total'))
            ->groupBy('payment_method')
            ->get();

        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            $labels[] = PosSale::paymentLabels()[$row->payment_method] ?? ($row->payment_method ?: 'Autre');
            $values[] = (int) $row->total;
        }

        return compact('labels', 'values');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRecentOrders(int $limit = 8, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $query = PosSale::with('client')->latest('sold_at');

        if ($dateFrom && $dateTo) {
            $query->whereBetween('sold_at', [$dateFrom, $dateTo]);
        }

        return $query->limit($limit)->get()
            ->map(fn (PosSale $sale) => [
                'ticket' => $sale->ticket_number,
                'client' => $sale->client?->name ?? '—',
                'total' => (float) $sale->total,
                'status' => $sale->status,
                'source' => $sale->source ?? 'pos',
                'date' => $sale->sold_at?->format('d/m/Y H:i'),
                'url' => route('orders.show', $sale),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRecentInvoices(int $limit = 6, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $query = Invoice::with('client')->latest('invoice_date');

        if ($dateFrom && $dateTo) {
            $query->whereBetween('invoice_date', [$dateFrom, $dateTo]);
        }

        return $query->limit($limit)->get()
            ->map(fn (Invoice $invoice) => [
                'number' => $invoice->invoice_number,
                'client' => $invoice->client?->name ?? '—',
                'total' => (float) $invoice->total,
                'date' => $invoice->invoice_date?->format('d/m/Y'),
                'url' => route('invoices.show', $invoice),
            ])
            ->all();
    }

    /**
     * @return array{count: int, total: float, items: list<array<string, mixed>>}
     */
    public function getUnpaidInvoices(int $limit = 8): array
    {
        $query = Invoice::with('client')->unpaid()->latest('invoice_date');

        $items = (clone $query)
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'number' => $invoice->invoice_number,
                'client' => $invoice->client?->name ?? '—',
                'total' => (float) $invoice->total,
                'date' => $invoice->invoice_date?->format('d/m/Y'),
                'due_date' => $invoice->due_date?->format('d/m/Y'),
                'url' => route('invoices.show', $invoice),
            ])
            ->all();

        return [
            'count' => (clone $query)->count(),
            'total' => (float) (clone $query)->sum('total'),
            'items' => $items,
        ];
    }
}
