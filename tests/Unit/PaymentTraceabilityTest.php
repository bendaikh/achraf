<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentImport;
use App\Models\PaymentImportLine;
use App\Models\User;
use App\Services\PaymentFeeBreakdown;
use App\Services\PaymentRecordingService;
use App\Services\PaymentTraceabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_breakdown_computes_net_from_gross_minus_fees(): void
    {
        $result = PaymentFeeBreakdown::normalize([
            'amount' => 408,
            'delivery_fees' => 35,
        ]);

        $this->assertSame(408.0, $result['gross_amount']);
        $this->assertSame(35.0, $result['delivery_fees']);
        $this->assertSame(373.0, $result['net_received']);
    }

    public function test_fee_breakdown_computes_fees_from_gross_minus_net(): void
    {
        $result = PaymentFeeBreakdown::normalize([
            'gross_amount' => 408,
            'net_received' => 373,
        ]);

        $this->assertSame(35.0, $result['delivery_fees']);
        $this->assertSame(373.0, $result['net_received']);
    }

    public function test_manual_payment_persists_fee_breakdown(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::create(['name' => 'Client Test']);
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'FAC-TRACE-1',
            'invoice_date' => now()->toDateString(),
            'total' => 408,
            'currency' => 'MAD',
        ]);

        $payment = app(PaymentRecordingService::class)->recordInvoicePayment($invoice, [
            'payment_date' => now()->toDateString(),
            'amount' => 408,
            'delivery_fees' => 35,
            'payment_method' => 'Espèces',
            'payment_reference' => 'REF-1',
            'tracking_number' => 'TRK-1',
            'source' => InvoicePayment::SOURCE_MANUAL,
        ]);

        $this->assertSame(408.0, (float) $payment->amount);
        $this->assertSame(408.0, (float) $payment->gross_amount);
        $this->assertSame(35.0, (float) $payment->delivery_fees);
        $this->assertSame(373.0, (float) $payment->net_received);
        $this->assertSame('TRK-1', $payment->tracking_number);
    }

    public function test_bulk_payment_stores_batch_and_per_invoice_fees(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::create(['name' => 'Client Bulk']);
        $invoiceA = Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'FAC-A',
            'invoice_date' => now()->toDateString(),
            'total' => 408,
            'currency' => 'MAD',
        ]);
        $invoiceB = Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'FAC-B',
            'invoice_date' => now()->toDateString(),
            'total' => 250,
            'currency' => 'MAD',
        ]);

        $batchId = (string) Str::uuid();
        $payments = app(PaymentRecordingService::class)->recordBulkInvoicePayments([
            ['invoice_id' => $invoiceA->id, 'amount' => 408, 'delivery_fees' => 35],
            ['invoice_id' => $invoiceB->id, 'amount' => 250, 'delivery_fees' => 15],
        ], [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Virement bancaire',
            'payment_reference' => 'BATCH-1',
            'source' => InvoicePayment::SOURCE_BULK,
            'payment_batch_id' => $batchId,
        ]);

        $this->assertCount(2, $payments);
        $this->assertTrue($payments[0]->payment_batch_id === $batchId);
        $this->assertTrue($payments[1]->payment_batch_id === $batchId);
        $this->assertSame(373.0, (float) $payments[0]->net_received);
        $this->assertSame(235.0, (float) $payments[1]->net_received);
    }

    public function test_backfill_recovers_fees_from_import_line(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client Import']);
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'FAC-IMP-1',
            'invoice_date' => now()->toDateString(),
            'total' => 408,
            'currency' => 'MAD',
            'payment_status' => Invoice::PAYMENT_PAID,
        ]);

        $import = PaymentImport::create([
            'scope' => PaymentImport::SCOPE_SALES,
            'status' => PaymentImport::STATUS_VALIDATED,
            'file_name' => 'carrier.xlsx',
            'file_path' => 'imports/carrier.xlsx',
            'file_hash' => 'hash-test',
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'total_rows' => 1,
            'matched_count' => 1,
        ]);

        $line = PaymentImportLine::create([
            'payment_import_id' => $import->id,
            'row_number' => 1,
            'file_reference' => 'REF-IMP',
            'file_tracking' => 'TRK-IMP',
            'file_amount' => 408,
            'file_delivery_fees' => 35,
            'file_net_amount' => 373,
            'match_status' => PaymentImportLine::MATCH_MATCHED,
            'include_in_validation' => true,
            'invoice_id' => $invoice->id,
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'amount' => 408,
            'payment_method' => 'Virement bancaire',
            'source' => InvoicePayment::SOURCE_IMPORT,
            'created_by' => $user->id,
            'payment_import_id' => $import->id,
            'payment_import_row_id' => $line->id,
        ]);

        $this->assertNull($payment->delivery_fees);
        $this->assertNull($payment->net_received);

        $result = app(PaymentTraceabilityService::class)->backfillMissingDetails();
        $this->assertSame(1, $result['updated']);

        $payment->refresh();
        $this->assertSame(408.0, (float) $payment->gross_amount);
        $this->assertSame(35.0, (float) $payment->delivery_fees);
        $this->assertSame(373.0, (float) $payment->net_received);
        $this->assertSame('TRK-IMP', $payment->tracking_number);
        $this->assertSame('REF-IMP', $payment->payment_reference);

        $this->assertSame(35.0, $payment->resolvedDeliveryFees());
        $this->assertSame(373.0, $payment->resolvedNetReceived());
    }

    public function test_resolved_helpers_fall_back_to_import_line_without_backfill(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client Fallback']);
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'FAC-FB-1',
            'invoice_date' => now()->toDateString(),
            'total' => 408,
            'currency' => 'MAD',
            'payment_status' => Invoice::PAYMENT_PAID,
        ]);

        $import = PaymentImport::create([
            'scope' => PaymentImport::SCOPE_SALES,
            'status' => PaymentImport::STATUS_VALIDATED,
            'file_name' => 'carrier2.xlsx',
            'file_path' => 'imports/carrier2.xlsx',
            'file_hash' => 'hash-test-2',
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'total_rows' => 1,
            'matched_count' => 1,
        ]);

        $line = PaymentImportLine::create([
            'payment_import_id' => $import->id,
            'row_number' => 1,
            'file_tracking' => 'TRK-FB',
            'file_amount' => 408,
            'file_delivery_fees' => 35,
            'file_net_amount' => null,
            'match_status' => PaymentImportLine::MATCH_MATCHED,
            'include_in_validation' => true,
            'invoice_id' => $invoice->id,
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'amount' => 408,
            'payment_method' => 'Virement bancaire',
            'source' => InvoicePayment::SOURCE_IMPORT,
            'created_by' => $user->id,
            'payment_import_id' => $import->id,
            'payment_import_row_id' => $line->id,
        ]);

        $payment->load('paymentImportLine');

        $this->assertSame(408.0, $payment->resolvedGrossAmount());
        $this->assertSame(35.0, $payment->resolvedDeliveryFees());
        $this->assertSame(373.0, $payment->resolvedNetReceived());
        $this->assertSame('TRK-FB', $payment->resolvedTrackingNumber($invoice));
    }
}
