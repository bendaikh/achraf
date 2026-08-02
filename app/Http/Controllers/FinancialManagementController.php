<?php

namespace App\Http\Controllers;

use App\Services\FinancialManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialManagementController extends Controller
{
    public function __construct(
        private FinancialManagementService $financial
    ) {}

    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $operationType = $request->input('operation_type', 'all');
        $paymentStatus = $request->input('payment_status');
        $search = $request->input('q');

        return view('financial.index', [
            'overview' => $this->financial->getOverview($dateFrom, $dateTo),
            'chart' => $this->financial->getMonthlyChart(6, $dateFrom, $dateTo),
            'recentTransactions' => $this->financial->getRecentTransactions(
                40,
                $dateFrom,
                $dateTo,
                $operationType,
                $search
            ),
            'history' => $this->financial->getPeriodHistory($dateFrom, $dateTo, 15),
            'outstandingClients' => $this->financial->getOutstandingClientInvoices(8, $paymentStatus),
            'outstandingSuppliers' => $this->financial->getOutstandingSupplierInvoices(8, $paymentStatus),
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'operationType' => $operationType,
            'paymentStatus' => $paymentStatus,
            'search' => $search,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $rows = $this->financial->getDeclarationRows($dateFrom, $dateTo);
        $filename = sprintf(
            'finance_%s_%s.csv',
            $dateFrom->format('Ymd'),
            $dateTo->format('Ymd')
        );

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Indicateur', 'Montant', 'Détail'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['indicateur'],
                    is_numeric($row['montant']) ? number_format((float) $row['montant'], 2, '.', '') : $row['montant'],
                    $row['detail'],
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            return [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$dateFrom, $dateTo];
    }
}
