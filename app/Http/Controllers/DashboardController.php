<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Support\SoftNavigation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboard
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $shell = [
            'dataUrl' => route('dashboard.data'),
            'bootstrap' => [
                'dateFrom' => $dateFrom->toDateString(),
                'dateTo' => $dateTo->toDateString(),
                'todayLabel' => now()->translatedFormat('d F Y'),
            ],
        ];

        if (SoftNavigation::wants($request)) {
            $dashboardPageScript = public_path('js/dashboard-page.js');
            $dashboardPageVersion = is_readable($dashboardPageScript) ? filemtime($dashboardPageScript) : time();

            return SoftNavigation::response([
                'title' => 'Tableau de bord',
                'page_title' => 'Tableau de bord',
                'url' => route('dashboard', array_filter([
                    'date_from' => $request->input('date_from'),
                    'date_to' => $request->input('date_to'),
                ])),
                'html' => view('dashboard.panel', $shell)->render(),
                'module' => 'dashboard',
                'tabs_html' => '',
                'assets' => [
                    [
                        'type' => 'script',
                        'src' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                    ],
                    [
                        'type' => 'script',
                        'src' => asset('js/dashboard-page.js').'?v='.$dashboardPageVersion,
                    ],
                ],
            ]);
        }

        return view('dashboard', $shell);
    }

    public function data(Request $request): JsonResponse
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        return response()->json($this->payload($dateFrom, $dateTo));
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
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$dateFrom, $dateTo];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Carbon $dateFrom, Carbon $dateTo): array
    {
        return [
            'stats' => $this->dashboard->getStats($dateFrom, $dateTo),
            'chart' => $this->dashboard->getMonthlyChart(6, $dateFrom, $dateTo),
            'paymentChart' => $this->dashboard->getPaymentMethodsChart($dateFrom, $dateTo),
            'recentOrders' => $this->dashboard->getRecentOrders(8, $dateFrom, $dateTo),
            'recentInvoices' => $this->dashboard->getRecentInvoices(6, $dateFrom, $dateTo),
            'unpaidInvoices' => $this->dashboard->getUnpaidInvoices(8),
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'todayLabel' => now()->translatedFormat('d F Y'),
        ];
    }
}
