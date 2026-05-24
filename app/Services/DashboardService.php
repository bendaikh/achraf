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
  public function getStats(): array
  {
    $now = Carbon::now();
    $startOfMonth = $now->copy()->startOfMonth();
    $startOfYear = $now->copy()->startOfYear();

    $completedSales = PosSale::query()->where('status', PosSale::STATUS_COMPLETED);

    $revenueMonth = (float) (clone $completedSales)
      ->where('sold_at', '>=', $startOfMonth)
      ->sum('total');

    $revenueYear = (float) (clone $completedSales)
      ->where('sold_at', '>=', $startOfYear)
      ->sum('total');

    $invoiceRevenueMonth = (float) Invoice::query()
      ->where('invoice_date', '>=', $startOfMonth)
      ->sum('total');

    $expensesMonth = (float) Expense::query()
      ->where('expense_date', '>=', $startOfMonth)
      ->sum('amount');

    $expensesYear = (float) Expense::query()
      ->where('expense_date', '>=', $startOfYear)
      ->sum('amount');

    $supplierPaidMonth = (float) SupplierInvoicePayment::query()
      ->where('payment_date', '>=', $startOfMonth)
      ->sum('amount');

    $openSupplierBalance = (float) SupplierInvoice::query()
      ->get()
      ->sum(fn (SupplierInvoice $inv) => (float) $inv->remaining_balance);

    return [
      'clients_count' => Client::count(),
      'suppliers_count' => Supplier::count(),
      'products_count' => Product::count(),
      'low_stock_count' => Product::lowStock()->where('stock_quantity', '>', 0)->count(),
      'out_of_stock_count' => Product::where('stock_quantity', '<=', 0)->count(),
      'orders_total' => PosSale::count(),
      'orders_month' => PosSale::where('sold_at', '>=', $startOfMonth)->count(),
      'orders_completed' => PosSale::where('status', PosSale::STATUS_COMPLETED)->count(),
      'invoices_count' => Invoice::count(),
      'invoices_month' => Invoice::where('invoice_date', '>=', $startOfMonth)->count(),
      'quotes_count' => Quote::count(),
      'quotes_pending' => Quote::whereNotIn('status', ['accepté', 'accepte', 'refusé', 'refuse', 'annulé', 'annule'])->count(),
      'revenue_month' => $revenueMonth + $invoiceRevenueMonth,
      'revenue_year' => $revenueYear + (float) Invoice::where('invoice_date', '>=', $startOfYear)->sum('total'),
      'expenses_month' => $expensesMonth,
      'expenses_year' => $expensesYear,
      'profit_month' => ($revenueMonth + $invoiceRevenueMonth) - $expensesMonth,
      'supplier_payments_month' => $supplierPaidMonth,
      'supplier_balance_due' => $openSupplierBalance,
      'shopify_orders' => PosSale::where('source', 'shopify')->count(),
      'pos_orders' => PosSale::where('source', 'pos')->orWhereNull('source')->count(),
    ];
  }

  /**
   * @return array{labels: list<string>, revenue: list<float>, expenses: list<float>}
   */
  public function getMonthlyChart(int $months = 6): array
  {
    $labels = [];
    $revenue = [];
    $expenses = [];

    for ($i = $months - 1; $i >= 0; $i--) {
      $date = Carbon::now()->subMonths($i);
      $start = $date->copy()->startOfMonth();
      $end = $date->copy()->endOfMonth();

      $labels[] = $date->translatedFormat('M Y');

      $salesRevenue = (float) PosSale::query()
        ->where('status', PosSale::STATUS_COMPLETED)
        ->whereBetween('sold_at', [$start, $end])
        ->sum('total');

      $invoiceRevenue = (float) Invoice::query()
        ->whereBetween('invoice_date', [$start, $end])
        ->sum('total');

      $revenue[] = round($salesRevenue + $invoiceRevenue, 2);

      $expenses[] = round((float) Expense::query()
        ->whereBetween('expense_date', [$start, $end])
        ->sum('amount'), 2);
    }

    return compact('labels', 'revenue', 'expenses');
  }

  /**
   * @return array{labels: list<string>, values: list<int>}
   */
  public function getPaymentMethodsChart(): array
  {
    $rows = PosSale::query()
      ->where('status', PosSale::STATUS_COMPLETED)
      ->where('sold_at', '>=', Carbon::now()->subMonths(3))
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
  public function getRecentOrders(int $limit = 8): array
  {
    return PosSale::with('client')
      ->latest('sold_at')
      ->limit($limit)
      ->get()
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
  public function getRecentInvoices(int $limit = 6): array
  {
    return Invoice::with('client')
      ->latest('invoice_date')
      ->limit($limit)
      ->get()
      ->map(fn (Invoice $invoice) => [
        'number' => $invoice->invoice_number,
        'client' => $invoice->client?->name ?? '—',
        'total' => (float) $invoice->total,
        'date' => $invoice->invoice_date?->format('d/m/Y'),
        'url' => route('invoices.show', $invoice),
      ])
      ->all();
  }
}
