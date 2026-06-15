<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboard
    ) {}

    public function index(Request $request)
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return view('dashboard', [
            'stats' => $this->dashboard->getStats($dateFrom, $dateTo),
            'chart' => $this->dashboard->getMonthlyChart(6, $dateFrom, $dateTo),
            'paymentChart' => $this->dashboard->getPaymentMethodsChart($dateFrom, $dateTo),
            'recentOrders' => $this->dashboard->getRecentOrders(8, $dateFrom, $dateTo),
            'recentInvoices' => $this->dashboard->getRecentInvoices(6, $dateFrom, $dateTo),
            'unpaidInvoices' => $this->dashboard->getUnpaidInvoices(8),
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
        ]);
    }
}
