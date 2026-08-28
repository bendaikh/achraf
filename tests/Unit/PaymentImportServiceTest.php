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
        $spreadsheet->getActiveSheet()->fromArray([
            ['N°', 'Code d\'envoi', 'Date de ramassage', 'Date de livraison', 'Status', 'Ville', 'Crbt', 'Frais', 'Total'],
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
        $this->assertSame('EGRFTC11849', $rows[0]['code_d_envoi']);
        $this->assertSame('2026-08-08', $rows[0]['date_de_livraison']);
        $this->assertSame('Livré', $rows[0]['status']);
        $this->assertSame(300.0, $this->service()->paymentAmount($rows[0]));
        $this->assertNull($this->service()->exclusionReason($rows[0], 300.0));
        $this->assertNotNull($this->service()->exclusionReason($rows[1], 0.0));
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

        $this->assertSame('EGRFTC12002', $service->field($row, ['code d\'envoi']));
        $this->assertSame(430.0, $service->paymentAmount($row));
        $this->assertNull($service->exclusionReason($row, 430.0));
    }

    public function test_fee_aware_amount_comparison_eliminates_false_discrepancy(): void
    {
        $matcher = app(PaymentMatchingService::class);

        [$status, $variance] = $matcher->compareAmountsWithFees(300.0, 45.0, 255.0, 300.0);

        $this->assertSame(PaymentImportLine::AMOUNT_OK, $status);
        $this->assertSame(0.0, $variance);
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
