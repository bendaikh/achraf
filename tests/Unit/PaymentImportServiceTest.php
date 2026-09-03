<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentImportLine;
use App\Models\PosSale;
use App\Services\PaymentImportService;
use App\Services\PaymentMatchingService;
use App\Services\PaymentRecordingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PaymentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_the_carrier_settlement_workbook_format(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'payment-import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        // Real carrier exports often lose the apostrophe: "Code denvoi".
        $spreadsheet->getActiveSheet()->fromArray([
            ['N°', 'Code denvoi', 'Date de ramassage', 'Date de livraison', 'Status', 'Ville', 'Crbt', 'Frais', 'Total'],
            [1, 'EGRFTC11849', '2026-08-07', '2026-08-08', 'Livré', 'Tanger', 300, 35, 265],
            [2, 'EGRFTC11868_remboursement', null, null, 'Remboursé', 'ElHajeb VILLE', 0, 219, -219],
        ]);
        (new Xlsx($spreadsheet))->save($path);

        try {
            $rows = $this->service()->readFile($path, 'xlsx');
        } finally {
            @unlink($path);
        }

        $this->assertCount(2, $rows);
        $fields = $this->service()->extractRowFields($rows[0]);
        $this->assertSame('EGRFTC11849', $fields['tracking']);
        $this->assertSame('2026-08-08', $fields['delivery_date']);
        $this->assertSame('Livré', $fields['status']);
        $this->assertSame(300.0, $fields['gross_amount']);
        $this->assertSame(35.0, $fields['delivery_fees']);
        $this->assertSame(265.0, $fields['net_amount']);
        $this->assertNull($this->service()->exclusionReason($rows[0], 300.0));
        $this->assertNotNull($this->service()->exclusionReason($rows[1], 0.0));
    }

    public function test_it_reads_code_d_envoi_with_apostrophe(): void
    {
        $row = ['code_d_envoi' => 'EGRFTC12001', 'crbt' => '200', 'status' => 'Livré'];
        $fields = $this->service()->extractRowFields($row);
        $this->assertSame('EGRFTC12001', $fields['tracking']);
    }

    public function test_crbt_is_used_instead_of_the_net_total(): void
    {
        $service = $this->service();
        $row = [
            'code_d_envoi' => 'EGRFTC12002',
            'status' => 'Livré',
            'crbt' => '430,00',
            'frais' => '35,00',
            'total' => '395,00',
        ];

        $fields = $service->extractRowFields($row);
        $this->assertSame('EGRFTC12002', $fields['tracking']);
        $this->assertSame(430.0, $fields['gross_amount']);
        $this->assertSame(395.0, $fields['net_amount']);
        $this->assertNull($service->exclusionReason($row, 430.0));
    }

    public function test_net_is_derived_from_crbt_minus_fees_when_total_missing(): void
    {
        $fields = $this->service()->extractRowFields([
            'code_d_envoi' => 'EGRFTC12003',
            'status' => 'Livré',
            'crbt' => '408',
            'frais' => '35',
        ]);

        $this->assertSame(408.0, $fields['gross_amount']);
        $this->assertSame(35.0, $fields['delivery_fees']);
        $this->assertSame(373.0, $fields['net_amount']);
    }

    public function test_fee_aware_amount_comparison_eliminates_false_discrepancy(): void
    {
        $matcher = app(PaymentMatchingService::class);

        [$status, $variance] = $matcher->compareAmountsWithFees(300.0, 45.0, 255.0, 300.0);

        $this->assertSame(PaymentImportLine::AMOUNT_OK, $status);
        $this->assertSame(0.0, $variance);
    }

    public function test_it_matches_by_tracking_order_code(): void
    {
        $client = Client::create(['name' => 'Client Tracking', 'city' => 'Tanger']);
        $sale = PosSale::create([
            'client_id' => $client->id,
            'ticket_number' => 'FTC11849',
            'total' => 300,
            'shipping_city' => 'Tanger',
            'sold_at' => Carbon::parse('2026-08-07'),
            'status' => PosSale::STATUS_COMPLETED,
            'payment_method' => PosSale::PAYMENT_CASH,
        ]);
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'pos_sale_id' => $sale->id,
            'invoice_number' => 'FA-2026/1849',
            'invoice_date' => '2026-08-07',
            'total' => 300,
            'currency' => 'MAD',
        ]);

        // Another invoice with same amount/city must not create ambiguity once tracking is present.
        $otherClient = Client::create(['name' => 'Autre', 'city' => 'Tanger']);
        $otherSale = PosSale::create([
            'client_id' => $otherClient->id,
            'ticket_number' => 'FTC99901',
            'total' => 300,
            'shipping_city' => 'Tanger',
            'sold_at' => Carbon::parse('2026-08-08'),
            'status' => PosSale::STATUS_COMPLETED,
            'payment_method' => PosSale::PAYMENT_CASH,
        ]);
        Invoice::create([
            'client_id' => $otherClient->id,
            'pos_sale_id' => $otherSale->id,
            'invoice_number' => 'FA-2026/9901',
            'invoice_date' => '2026-08-08',
            'total' => 300,
            'currency' => 'MAD',
        ]);

        $matcher = app(PaymentMatchingService::class);
        $result = $matcher->matchSalesLine([
            'tracking' => 'EGRFTC11849',
            'gross_amount' => 300.0,
            'city' => 'Tanger',
            'delivery_date' => '2026-08-08',
        ]);

        $this->assertSame(PaymentImportLine::MATCH_MATCHED, $result['status']);
        $this->assertSame($invoice->id, $result['invoice_id']);
        $this->assertContains(PaymentMatchingService::CRITERION_ORDER, $result['criteria']);
    }

    public function test_amount_city_period_alone_never_auto_matches(): void
    {
        $client = Client::create(['name' => 'Seul', 'city' => 'Tanger']);
        $sale = PosSale::create([
            'client_id' => $client->id,
            'ticket_number' => 'FTC70001',
            'total' => 300,
            'shipping_city' => 'Tanger',
            'sold_at' => Carbon::parse('2026-08-08'),
            'status' => PosSale::STATUS_COMPLETED,
            'payment_method' => PosSale::PAYMENT_CASH,
        ]);
        Invoice::create([
            'client_id' => $client->id,
            'pos_sale_id' => $sale->id,
            'invoice_number' => 'FA-2026/7001',
            'invoice_date' => '2026-08-08',
            'total' => 300,
            'currency' => 'MAD',
        ]);

        $matcher = app(PaymentMatchingService::class);
        $result = $matcher->matchSalesLine([
            'gross_amount' => 300.0,
            'city' => 'Tanger',
            'delivery_date' => '2026-08-08',
        ]);

        $this->assertNotSame(PaymentImportLine::MATCH_MATCHED, $result['status']);
        $this->assertSame(PaymentImportLine::MATCH_UNMATCHED, $result['status']);
    }

    public function test_it_matches_by_phone_and_amount(): void
    {
        $client = Client::create(['name' => 'Khalid A', 'phone' => '0612345678', 'city' => 'Rabat']);
        $sale = PosSale::create([
            'client_id' => $client->id,
            'ticket_number' => 'FTC99999',
            'total' => 300,
            'sold_at' => Carbon::parse('2026-08-16'),
            'status' => PosSale::STATUS_COMPLETED,
            'payment_method' => PosSale::PAYMENT_CASH,
        ]);
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'pos_sale_id' => $sale->id,
            'invoice_number' => 'FA-2026/0099',
            'invoice_date' => '2026-08-16',
            'total' => 300,
            'currency' => 'MAD',
        ]);

        $matcher = app(PaymentMatchingService::class);
        $result = $matcher->matchSalesLine([
            'client_phone' => '0612345678',
            'client_name' => 'Khalid A',
            'gross_amount' => 300.0,
        ]);

        $this->assertSame(PaymentImportLine::MATCH_MATCHED, $result['status']);
        $this->assertSame($invoice->id, $result['invoice_id']);
        $this->assertContains(PaymentMatchingService::CRITERION_PHONE_AMOUNT, $result['criteria']);
    }

    public function test_it_reuses_memorized_manual_match(): void
    {
        $client = Client::create(['name' => 'Sara B', 'phone' => '0699887766']);
        $sale = PosSale::create([
            'client_id' => $client->id,
            'ticket_number' => 'FTC55555',
            'total' => 450,
            'sold_at' => Carbon::parse('2026-08-10'),
            'status' => PosSale::STATUS_COMPLETED,
            'payment_method' => PosSale::PAYMENT_CASH,
        ]);
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'pos_sale_id' => $sale->id,
            'invoice_number' => 'FA-2026/0555',
            'invoice_date' => '2026-08-10',
            'total' => 450,
            'currency' => 'MAD',
        ]);

        app(\App\Services\PaymentMatchMemoryService::class)->rememberFromLine(
            PaymentImportLine::make([
                'file_raw' => ['telephone' => '0699887766', 'client' => 'Sara B'],
                'file_amount' => 450,
            ]),
            $invoice
        );

        $matcher = app(PaymentMatchingService::class);
        $result = $matcher->matchSalesLine([
            'client_phone' => '0699887766',
            'client_name' => 'Sara B',
            'gross_amount' => 450.0,
        ]);

        $this->assertSame(PaymentImportLine::MATCH_MATCHED, $result['status']);
        $this->assertSame($invoice->id, $result['invoice_id']);
        $this->assertContains(PaymentMatchingService::CRITERION_MEMORY, $result['criteria']);
    }

    public function test_it_matches_by_client_name_and_amount_without_tracking(): void
    {
        $client = Client::create(['name' => 'Ahmed X', 'city' => 'Casablanca']);
        $sale = PosSale::create([
            'client_id' => $client->id,
            'ticket_number' => 'FTC12345',
            'total' => 300,
            'shipping_city' => 'Casablanca',
            'sold_at' => Carbon::parse('2026-08-16'),
            'status' => PosSale::STATUS_COMPLETED,
            'payment_method' => PosSale::PAYMENT_CASH,
        ]);
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'pos_sale_id' => $sale->id,
            'invoice_number' => 'FA-2026/0001',
            'invoice_date' => '2026-08-16',
            'total' => 300,
            'currency' => 'MAD',
        ]);

        $matcher = app(PaymentMatchingService::class);
        $result = $matcher->matchSalesLine([
            'client_name' => 'Ahmed X',
            'gross_amount' => 300.0,
            'city' => 'Casablanca',
            'delivery_date' => '18/08/2026',
        ]);

        $this->assertSame(PaymentImportLine::MATCH_MATCHED, $result['status']);
        $this->assertSame($invoice->id, $result['invoice_id']);
        $this->assertContains(PaymentMatchingService::CRITERION_NAME_AMOUNT, $result['criteria']);
    }

    private function service(): TestablePaymentImportService
    {
        return new TestablePaymentImportService(
            $this->createMock(PaymentRecordingService::class),
            app(PaymentMatchingService::class),
            app(\App\Services\PaymentMatchMemoryService::class)
        );
    }
}

class TestablePaymentImportService extends PaymentImportService
{
    public function readFile(string $path, string $extension): array
    {
        return $this->parseFile($path, $extension);
    }

    public function paymentAmount(array $row): ?float
    {
        return $this->extractPaymentAmount($row);
    }

    public function exclusionReason(array $row, ?float $amount): ?string
    {
        return $this->carrierExclusionReason($row, $amount);
    }

    public function field(array $row, array $keys): ?string
    {
        return $this->pickField($row, $keys);
    }
}
