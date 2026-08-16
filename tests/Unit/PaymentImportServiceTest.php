<?php

namespace Tests\Unit;

use App\Services\PaymentImportService;
use App\Services\PaymentRecordingService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PaymentImportServiceTest extends TestCase
{
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

    private function service(): TestablePaymentImportService
    {
        return new TestablePaymentImportService($this->createMock(PaymentRecordingService::class));
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
