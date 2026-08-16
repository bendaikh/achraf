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
    private const PERIODS = ['month', 'quarter', 'year', 'previous_month', 'custom'];

    private const CHART_PERIODS = ['6', '12', 'year'];

    public function __construct(
        private DashboardService $dashboard
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = $this->resolveFilters($request);

        $shell = [
            'dataUrl' => route('dashboard.data'),
            'bootstrap' => [
                'dateFrom' => $filters['from']->toDateString(),
                'dateTo' => $filters['to']->toDateString(),
                'period' => $filters['period'],
                'chartPeriod' => $filters['chartPeriod'],
                'periodLabel' => $filters['label'],
                'todayLabel' => now()->translatedFormat('d F Y'),
            ],
        ];

        if (SoftNavigation::wants($request)) {
            $dashboardPageScript = public_path('js/dashboard-page.js');
            $dashboardPageVersion = is_readable($dashboardPageScript) ? filemtime($dashboardPageScript) : time();

            return SoftNavigation::response([
                'title' => 'Tableau de bord',
                'page_title' => 'Tableau de bord',
                'url' => route('dashboard', $this->queryParams($filters)),
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
        $filters = $this->resolveFilters($request);

        return response()->json($this->payload($filters));
    }

    /**
     * @return array{from: Carbon, to: Carbon, period: string, chartPeriod: string, label: string}
     */
    private function resolveFilters(Request $request): array
    {
        $period = (string) $request->input('period', '');
        if (! in_array($period, self::PERIODS, true)) {
            $period = ($request->filled('date_from') || $request->filled('date_to')) ? 'custom' : 'month';
        }

        $now = Carbon::now();

        [$from, $to] = match ($period) {
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()->endOfDay()],
            'previous_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
            'custom' => [
                $request->filled('date_from')
                    ? Carbon::parse($request->input('date_from'))->startOfDay()
                    : $now->copy()->startOfMonth(),
                $request->filled('date_to')
                    ? Carbon::parse($request->input('date_to'))->endOfDay()
                    : $now->copy()->endOfDay(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $chartPeriod = (string) $request->input('chart_period', '6');
        if (! in_array($chartPeriod, self::CHART_PERIODS, true)) {
            $chartPeriod = '6';
        }

        return [
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'chartPeriod' => $chartPeriod,
            'label' => $this->periodLabel($period, $from, $to),
        ];
    }

    private function periodLabel(string $period, Carbon $from, Carbon $to): string
    {
        return match ($period) {
            'quarter' => 'Trimestre en cours ('.$from->translatedFormat('d M').' → '.$to->translatedFormat('d M Y').')',
            'year' => 'Année '.$from->year,
            'previous_month' => ucfirst($from->translatedFormat('F Y')),
            'custom' => $from->translatedFormat('d M Y').' → '.$to->translatedFormat('d M Y'),
            default => ucfirst($from->translatedFormat('F Y')),
        };
    }

    /**
     * @param  array{from: Carbon, to: Carbon, period: string, chartPeriod: string, label: string}  $filters
     * @return array<string, string>
     */
    private function queryParams(array $filters): array
    {
        return array_filter([
            'period' => $filters['period'],
            'date_from' => $filters['from']->toDateString(),
            'date_to' => $filters['to']->toDateString(),
            'chart_period' => $filters['chartPeriod'],
        ]);
    }

    /**
     * @param  array{from: Carbon, to: Carbon, period: string, chartPeriod: string, label: string}  $filters
     * @return array<string, mixed>
     */
    private function payload(array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];
        [$previousFrom, $previousTo] = $this->dashboard->previousPeriod($from, $to);

        return [
            'dateFrom' => $from->toDateString(),
            'dateTo' => $to->toDateString(),
            'period' => $filters['period'],
            'chartPeriod' => $filters['chartPeriod'],
            'periodLabel' => $filters['label'],
            'previousPeriodLabel' => $previousFrom->translatedFormat('d M Y').' → '.$previousTo->translatedFormat('d M Y'),
            'todayLabel' => now()->translatedFormat('d F Y'),
            'url' => route('dashboard', $this->queryParams($filters)),
            'kpis' => $this->dashboard->getKpis($from, $to),
            'todo' => $this->dashboard->getTodo(),
            'chart' => $this->dashboard->getChart($filters['chartPeriod'], $to),
            'channels' => $this->dashboard->getChannels($from, $to),
            'paymentMethods' => $this->dashboard->getPaymentMethods($from, $to),
            'activity' => $this->dashboard->getCommercialActivity($from, $to),
            'stock' => $this->dashboard->getStockOverview(),
            'treasury' => $this->dashboard->getTreasury($from, $to),
            'receivables' => $this->dashboard->getReceivables(),
            'payables' => $this->dashboard->getPayables(),
            'recent' => $this->dashboard->getRecentOperations($from, $to),
        ];
    }
}
