<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardService;
use App\Support\SoftNavigation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSoftNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_data_endpoint_returns_json_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('dashboard.data'));

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => ['revenue_month', 'expenses_month', 'clients_count'],
                'chart' => ['labels', 'revenue', 'expenses'],
                'paymentChart' => ['labels', 'values'],
                'recentOrders',
                'recentInvoices',
                'unpaidInvoices' => ['count', 'total', 'items'],
                'dateFrom',
                'dateTo',
                'todayLabel',
            ]);
    }

    public function test_dashboard_soft_nav_returns_shell_without_layout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'), [
            SoftNavigation::HEADER => '1',
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'title',
                'page_title',
                'url',
                'html',
                'module',
                'tabs_html',
                'assets',
            ]);

        $this->assertSame('dashboard', $response->json('module'));
        $this->assertStringContainsString('dashboardPage', $response->json('html'));
        $this->assertStringNotContainsString('app-shell-aside', $response->json('html'));
    }

    public function test_soft_nav_shell_skips_heavy_stats_queries_and_is_much_smaller(): void
    {
        $user = User::factory()->create();
        $service = app(DashboardService::class);
        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfDay();

        // Simulate previous synchronous dashboard controller work.
        $legacyStarted = hrtime(true);
        $legacyPayload = [
            'stats' => $service->getStats($from, $to),
            'chart' => $service->getMonthlyChart(6, $from, $to),
            'paymentChart' => $service->getPaymentMethodsChart($from, $to),
            'recentOrders' => $service->getRecentOrders(8, $from, $to),
            'recentInvoices' => $service->getRecentInvoices(6, $from, $to),
            'unpaidInvoices' => $service->getUnpaidInvoices(8),
            'dateFrom' => $from->toDateString(),
            'dateTo' => $to->toDateString(),
            'todayLabel' => now()->translatedFormat('d F Y'),
        ];
        $legacyHtml = view('dashboard.panel', [
            'dataUrl' => route('dashboard.data'),
            'bootstrap' => $legacyPayload,
        ])->render();
        $legacyMs = (hrtime(true) - $legacyStarted) / 1e6;

        $shellStarted = hrtime(true);
        $shell = $this->actingAs($user)->get(route('dashboard'), [
            SoftNavigation::HEADER => '1',
            'Accept' => 'application/json',
        ]);
        $shellMs = (hrtime(true) - $shellStarted) / 1e6;

        $dataStarted = hrtime(true);
        $data = $this->actingAs($user)->getJson(route('dashboard.data'));
        $dataMs = (hrtime(true) - $dataStarted) / 1e6;

        $shell->assertOk();
        $data->assertOk();

        $summary = [
            'legacy_sync_controller_work_ms' => round($legacyMs, 2),
            'soft_nav_shell_ms' => round($shellMs, 2),
            'async_data_ms' => round($dataMs, 2),
            'shell_then_data_ms' => round($shellMs + $dataMs, 2),
            'legacy_hydrated_panel_bytes' => strlen($legacyHtml),
            'soft_nav_html_bytes' => strlen($shell->json('html')),
            'data_json_bytes' => strlen($data->getContent()),
            'shell_size_reduction_pct' => round((1 - (strlen($shell->json('html')) / max(strlen($legacyHtml), 1))) * 100, 1),
        ];

        fwrite(STDOUT, PHP_EOL.'[Dashboard soft-nav performance] '.json_encode($summary, JSON_PRETTY_PRINT).PHP_EOL);

        $this->assertLessThan($legacyMs, $shellMs, 'Soft-nav shell should avoid the heavy stats work.');
        $this->assertLessThan(strlen($legacyHtml), strlen($shell->json('html')));
        $this->assertStringNotContainsString('"revenue_month"', $shell->json('html'));
    }
}