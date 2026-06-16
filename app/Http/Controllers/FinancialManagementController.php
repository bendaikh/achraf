<?php

namespace App\Http\Controllers;

use App\Services\FinancialManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinancialManagementController extends Controller
{
    public function __construct(
        private FinancialManagementService $financial
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

        return view('financial.index', [
            'overview' => $this->financial->getOverview($dateFrom, $dateTo),
            'chart' => $this->financial->getMonthlyChart(6, $dateFrom, $dateTo),
            'recentTransactions' => $this->financial->getRecentTransactions(12, $dateFrom, $dateTo),
            'outstandingClients' => $this->financial->getOutstandingClientInvoices(8),
            'outstandingSuppliers' => $this->financial->getOutstandingSupplierInvoices(8),
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
        ]);
    }
}
