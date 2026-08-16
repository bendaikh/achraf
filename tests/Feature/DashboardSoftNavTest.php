<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
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
                'dateFrom',
                'dateTo',
                'period',
                'chartPeriod',
                'periodLabel',
                'previousPeriodLabel',
                'todayLabel',
                'url',
                'kpis' => [['key', 'label', 'hint', 'format', 'tone', 'url', 'value', 'variation']],
                'todo' => ['items' => [['key', 'label', 'count', 'url']], 'total'],
                'chart' => ['labels', 'revenue', 'cash_in', 'expenses', 'result', 'period'],
                'channels' => ['total', 'items' => [['key', 'label', 'amount', 'url', 'share']]],
                'paymentMethods' => ['total', 'items' => [['key', 'label', 'amount', 'share']]],
                'activity' => ['items' => [['key', 'label', 'value', 'format', 'url']], 'average_basket', 'orders_url'],
                'stock' => ['total', 'stocked', 'non_stocked', 'services', 'in_stock', 'low_stock', 'out_of_stock', 'stock_value', 'urls', 'restock'],
                'treasury' => ['available', 'caisse', 'banque', 'total', 'in', 'out', 'net', 'urls'],
                'receivables' => ['count', 'total', 'items', 'url'],
                'payables' => ['count', 'total', 'items', 'url'],
                'recent' => [
                    'orders' => ['items', 'url'],
                    'invoices' => ['items', 'url'],
                    'payments' => ['items', 'url'],
                    'movements' => ['items', 'url'],
                ],
            ]);

        $this->assertCount(8, $response->json('kpis'));
        $this->assertSame([
            'revenue',
            'cash_in',
            'expenses',
            'result',
            'treasury',
            'receivables',
            'payables',
            'open_orders',
        ], array_column($response->json('kpis'), 'key'));
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
            'dateFrom' => $from->toDateString(),
            'dateTo' => $to->toDateString(),
            'kpis' => $service->getKpis($from, $to),
            'todo' => $service->getTodo(),
            'chart' => $service->getChart('6', $to),
            'channels' => $service->getChannels($from, $to),
            'paymentMethods' => $service->getPaymentMethods($from, $to),
            'activity' => $service->getCommercialActivity($from, $to),
            'stock' => $service->getStockOverview(),
            'treasury' => $service->getTreasury($from, $to),
            'receivables' => $service->getReceivables(),
            'payables' => $service->getPayables(),
            'recent' => $service->getRecentOperations($from, $to),
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
        $this->assertStringNotContainsString('"revenue"', $shell->json('html'));
    }

    public function test_revenue_combines_pos_sales_and_independent_invoices_without_double_counting(): void
    {
        $client = Client::create(['name' => 'Client CA']);
        $from = Carbon::create(2026, 3, 1)->startOfDay();
        $to = Carbon::create(2026, 3, 31)->endOfDay();

        $posSale = $this->posSale('T-1', 400, '2026-03-05 10:00:00');
        // Facture générée depuis le ticket POS : ne doit pas être recomptée.
        $this->invoice('F-POS-1', $client, 400, '2026-03-05', ['pos_sale_id' => $posSale->id]);
        $this->invoice('F-1', $client, 250, '2026-03-10');
        // Hors période.
        $this->invoice('F-2', $client, 999, '2026-04-02');

        $revenue = app(DashboardService::class)->getRevenue($from, $to);

        $this->assertSame(400.0, $revenue['pos']);
        $this->assertSame(250.0, $revenue['invoices']);
        $this->assertSame(650.0, $revenue['total']);
    }

    public function test_cash_in_uses_invoice_payments_plus_uninvoiced_pos_sales(): void
    {
        $client = Client::create(['name' => 'Client encaissements']);
        $from = Carbon::create(2026, 3, 1)->startOfDay();
        $to = Carbon::create(2026, 3, 31)->endOfDay();

        $invoice = $this->invoice('F-10', $client, 1000, '2026-03-02');
        $this->payment($invoice, 300, '2026-03-08');

        $invoiced = $this->posSale('T-10', 500, '2026-03-09 09:00:00');
        $this->invoice('F-11', $client, 500, '2026-03-09', ['pos_sale_id' => $invoiced->id]);

        $this->posSale('T-11', 120, '2026-03-11 09:00:00');

        $this->assertSame(420.0, app(DashboardService::class)->getCashIn($from, $to));
    }

    public function test_receivables_and_payables_include_partial_payments(): void
    {
        $client = Client::create(['name' => 'Client solde']);
        $supplier = Supplier::create(['name' => 'Fournisseur solde']);

        $unpaid = $this->invoice('F-20', $client, 1000, '2026-03-02');
        $partial = $this->invoice('F-21', $client, 800, '2026-03-03');
        $this->payment($partial, 300, '2026-03-04');
        $paid = $this->invoice('F-22', $client, 500, '2026-03-05');
        $this->payment($paid, 500, '2026-03-06');

        $supplierInvoice = SupplierInvoice::create([
            'invoice_number' => 'FF-1',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-03-02',
            'total' => 600,
        ]);
        SupplierInvoicePayment::create([
            'supplier_invoice_id' => $supplierInvoice->id,
            'payment_date' => '2026-03-05',
            'amount' => 200,
            'payment_method' => 'Virement bancaire',
        ]);

        $service = app(DashboardService::class);

        $receivables = $service->getReceivables();
        $this->assertSame(2, $receivables['count']);
        $this->assertSame(1500.0, $receivables['total']);
        $this->assertContains($unpaid->invoice_number, array_column($receivables['items'], 'number'));

        $payables = $service->getPayables();
        $this->assertSame(1, $payables['count']);
        $this->assertSame(400.0, $payables['total']);
    }

    public function test_sales_payments_open_status_returns_unpaid_and_partial_invoices(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client open']);

        $unpaid = $this->invoice('F-30', $client, 1000, '2026-03-02');
        $partial = $this->invoice('F-31', $client, 800, '2026-03-03');
        $this->payment($partial, 300, '2026-03-04');
        $paid = $this->invoice('F-32', $client, 500, '2026-03-05');
        $this->payment($paid, 500, '2026-03-06');

        $response = $this->actingAs($user)->get(route('sales.payments.index', ['payment_status' => 'open']));

        $response->assertOk();
        $response->assertSee($unpaid->invoice_number);
        $response->assertSee($partial->invoice_number);
        $this->assertSame(2, $response->viewData('invoices')->total());
    }

    public function test_purchase_payments_open_status_returns_unpaid_and_partial_invoices(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur open']);

        $unpaid = SupplierInvoice::create([
            'invoice_number' => 'FF-10',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-03-02',
            'total' => 900,
        ]);
        $partial = SupplierInvoice::create([
            'invoice_number' => 'FF-11',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-03-03',
            'total' => 700,
        ]);
        SupplierInvoicePayment::create([
            'supplier_invoice_id' => $partial->id,
            'payment_date' => '2026-03-04',
            'amount' => 200,
            'payment_method' => 'Virement bancaire',
        ]);
        $settled = SupplierInvoice::create([
            'invoice_number' => 'FF-12',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-03-05',
            'total' => 400,
        ]);
        SupplierInvoicePayment::create([
            'supplier_invoice_id' => $settled->id,
            'payment_date' => '2026-03-06',
            'amount' => 400,
            'payment_method' => 'Virement bancaire',
        ]);

        $response = $this->actingAs($user)->get(route('purchases.payments.index', ['payment_status' => 'open']));

        $response->assertOk();
        $numbers = $response->viewData('invoices')->pluck('invoice_number')->all();
        $this->assertEqualsCanonicalizing([$unpaid->invoice_number, $partial->invoice_number], $numbers);
    }

    public function test_date_filter_scopes_aggregates_and_keeps_url_parameters(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client période']);

        $this->invoice('F-40', $client, 1000, '2026-03-10');
        $this->invoice('F-41', $client, 700, '2026-04-10');

        $response = $this->actingAs($user)->getJson(route('dashboard.data', [
            'period' => 'custom',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'chart_period' => '12',
        ]));

        $response->assertOk();
        $this->assertSame('2026-03-01', $response->json('dateFrom'));
        $this->assertSame('2026-03-31', $response->json('dateTo'));
        $this->assertSame('12', $response->json('chartPeriod'));

        $revenue = collect($response->json('kpis'))->firstWhere('key', 'revenue');
        $this->assertEqualsWithDelta(1000.0, $revenue['value'], 0.001);

        $this->assertStringContainsString('date_from=2026-03-01', $response->json('url'));
        $this->assertStringContainsString('date_to=2026-03-31', $response->json('url'));
        $this->assertStringContainsString('chart_period=12', $response->json('url'));
    }

    public function test_quick_period_presets_resolve_server_side(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('dashboard.data', ['period' => 'year']));

        $response->assertOk();
        $this->assertSame(Carbon::now()->startOfYear()->toDateString(), $response->json('dateFrom'));
        $this->assertSame('year', $response->json('period'));
    }

    private function invoice(string $number, Client $client, float $total, string $date, array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'invoice_number' => $number,
            'client_id' => $client->id,
            'invoice_date' => $date,
            'total' => $total,
            'subtotal' => $total,
        ], $attributes));
    }

    private function payment(Invoice $invoice, float $amount, string $date): InvoicePayment
    {
        return InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => $date,
            'amount' => $amount,
            'payment_method' => 'Espèces',
        ]);
    }

    private function posSale(string $ticket, float $total, string $soldAt): PosSale
    {
        return PosSale::create([
            'ticket_number' => $ticket,
            'sold_at' => $soldAt,
            'total' => $total,
            'subtotal' => $total,
            'payment_method' => PosSale::PAYMENT_CASH,
            'status' => PosSale::STATUS_COMPLETED,
            'source' => 'pos',
        ]);
    }
}
