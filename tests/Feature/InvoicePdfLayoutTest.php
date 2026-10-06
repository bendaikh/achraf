<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Support\CommercialDocumentView;
use App\Support\DocumentTaxBreakdown;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_pdf_layout_has_no_filler_rows_and_keeps_settlement_with_cachet(): void
    {
        $user = User::factory()->create();
        $invoice = $this->makeInvoiceWithItems(3);
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'amount' => 50,
            'payment_method' => 'Espèces',
            'payment_reference' => 'ESP-1',
            'source' => InvoicePayment::SOURCE_MANUAL,
        ]);
        $invoice = $invoice->fresh(['client', 'items', 'payments', 'adjustments']);

        $html = $this->actingAs($user)
            ->get(route('invoices.print', $invoice).'?no_print=1')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('empty-row', $html);
        $this->assertStringNotContainsString('facture-after-items', $html);
        $this->assertStringContainsString('facture-products-totals-keep', $html);
        $this->assertStringContainsString('Règlement / Paiement', $html);
        $this->assertStringContainsString('Cachet de la société', $html);
        $this->assertStringContainsString('Mode de règlement', $html);
        $this->assertStringContainsString('Espèces', $html);
        $this->assertStringContainsString('Sous-total TTC', $html);
        $this->assertStringContainsString('TOTAL FACTURE TTC', $html);

        $itemsPos = strpos($html, 'facture-items');
        $keepPos = strpos($html, 'facture-products-totals-keep');
        $totalsPos = strpos($html, 'facture-totals-wrap');
        $notesPos = strpos($html, 'facture-notes-box');
        $closingPos = strpos($html, 'facture-closing-table');
        $settlementPos = strpos($html, 'facture-settlement-box');
        $signaturePos = strpos($html, 'facture-signature-box');

        $this->assertNotFalse($itemsPos);
        $this->assertNotFalse($keepPos);
        $this->assertNotFalse($totalsPos);
        $this->assertNotFalse($notesPos);
        $this->assertNotFalse($closingPos);
        $this->assertNotFalse($settlementPos);
        $this->assertNotFalse($signaturePos);

        $this->assertTrue($keepPos < $totalsPos, 'Totaux dans le bloc collé aux derniers produits');
        $this->assertTrue($totalsPos < $notesPos, 'Notes après les totaux');
        $this->assertTrue($notesPos < $closingPos, 'Règlement+Cachet après les notes');
        $this->assertTrue($settlementPos > $closingPos && $signaturePos > $closingPos);
    }

    public function test_invoice_pdf_page_counts_for_small_and_large_product_lists(): void
    {
        $cases = [
            '1 produit / 0 paiement' => ['items' => 1, 'payments' => 0, 'exact_pages' => 1],
            '3 produits / 1 paiement' => ['items' => 3, 'payments' => 1, 'exact_pages' => 1],
            '3 produits / 2 paiements' => ['items' => 3, 'payments' => 2, 'exact_pages' => 1],
            '40 produits / 0 paiement' => ['items' => 40, 'payments' => 0, 'exact_pages' => 2],
            // 2 paiements : Règlement+Cachet peut passer seul en dernière page si l'espace manque.
            '40 produits / 2 paiements' => ['items' => 40, 'payments' => 2, 'min_pages' => 2, 'max_pages' => 3],
        ];

        foreach ($cases as $label => $case) {
            $invoice = $this->makeInvoiceWithItems($case['items']);
            $this->attachPayments($invoice, $case['payments']);
            $invoice = $invoice->fresh(['client', 'items', 'payments', 'adjustments']);

            $pageCount = $this->renderPdfPageCount($invoice);

            if (isset($case['exact_pages'])) {
                $this->assertSame(
                    $case['exact_pages'],
                    $pageCount,
                    "{$label} : attendu {$case['exact_pages']} page(s), obtenu {$pageCount}"
                );
            }
            if (isset($case['min_pages'])) {
                $this->assertGreaterThanOrEqual(
                    $case['min_pages'],
                    $pageCount,
                    "{$label} : attendu ≥ {$case['min_pages']} page(s), obtenu {$pageCount}"
                );
            }
            if (isset($case['max_pages'])) {
                $this->assertLessThanOrEqual(
                    $case['max_pages'],
                    $pageCount,
                    "{$label} : attendu ≤ {$case['max_pages']} page(s), obtenu {$pageCount}"
                );
            }

            $binary = $this->renderPdfBinary($invoice);
            $this->assertStringStartsWith('%PDF', $binary, "{$label} : PDF invalide");
        }
    }

    public function test_large_invoice_keeps_totals_with_last_products_not_orphaned(): void
    {
        $invoice = $this->makeInvoiceWithItems(40);
        $this->attachPayments($invoice, 2);
        $invoice = $invoice->fresh(['client', 'items', 'payments', 'adjustments']);

        $path = storage_path('app/pdf-layout-check-test-40.pdf');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $this->renderPdfBinary($invoice));

        $pageCount = (int) trim((string) shell_exec('pdfinfo '.escapeshellarg($path).' | awk \'/^Pages:/ {print $2}\''));
        $this->assertGreaterThanOrEqual(2, $pageCount);
        $this->assertLessThanOrEqual(3, $pageCount);

        $totalsPage = null;
        $lastProductPage = null;
        $settlementPage = null;

        for ($page = 1; $page <= $pageCount; $page++) {
            $text = (string) shell_exec(
                'pdftotext -layout -f '.$page.' -l '.$page.' '.escapeshellarg($path).' -'
            );

            if (str_contains($text, 'TOTAL FACTURE') || str_contains($text, 'Sous-total HT')) {
                $totalsPage = $page;
                $this->assertMatchesRegularExpression(
                    '/REF-\d+|SKU-\d+|Produit test \d+/',
                    $text,
                    'Les totaux ne doivent pas être seuls en haut de page sans produit'
                );
                $this->assertTrue(
                    str_contains($text, 'Produit test 40') || str_contains($text, 'REF-40'),
                    'Le dernier produit doit figurer sur la page des totaux'
                );
            }
            if (str_contains($text, 'Produit test 40') || str_contains($text, 'REF-40')) {
                $lastProductPage = $page;
            }
            if (
                str_contains($text, 'Règlement / Paiement')
                || str_contains($text, 'RÈGLEMENT / PAIEMENT')
                || str_contains($text, 'Reste à payer')
            ) {
                $settlementPage = $page;
            }
        }

        $this->assertNotNull($totalsPage);
        $this->assertNotNull($lastProductPage);
        $this->assertNotNull($settlementPage);
        $this->assertSame($lastProductPage, $totalsPage, 'Dernier produit et totaux sur la même page');
        $this->assertGreaterThanOrEqual($totalsPage, $settlementPage - 1, 'Règlement suit les totaux (même page ou page suivante)');

        @unlink($path);
    }

    public function test_invoice_pdf_route_returns_pdf_with_real_payment_modes(): void
    {
        $user = User::factory()->create();
        $invoice = $this->makeInvoiceWithItems(1);
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'amount' => (float) $invoice->total,
            'payment_method' => 'Carte bancaire',
            'payment_reference' => 'CB-99',
            'source' => InvoicePayment::SOURCE_MANUAL,
        ]);

        $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('%PDF', $response->getContent());
    }

    private function makeInvoiceWithItems(int $count): Invoice
    {
        $client = Client::create(['name' => 'Client PDF Layout']);
        $unit = 100.0;
        $lineTotal = $unit * 1.2;
        $subtotal = $unit * $count;
        $total = $lineTotal * $count;

        $invoice = Invoice::create([
            'invoice_number' => 'FAC-LAYOUT-'.uniqid(),
            'client_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => $subtotal,
            'discount' => 0,
            'adjustment' => 0,
            'total' => $total,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'remarks' => 'Note de test PDF',
        ]);

        for ($i = 1; $i <= $count; $i++) {
            $invoice->items()->create([
                'ref' => 'REF-'.$i,
                'designation' => 'Produit test '.$i,
                'quantity' => 1,
                'unit_price' => $unit,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'line_total' => $lineTotal,
            ]);
        }

        return $invoice->fresh(['client', 'items', 'payments', 'adjustments']);
    }

    private function attachPayments(Invoice $invoice, int $count): void
    {
        $methods = ['Espèces', 'Virement bancaire', 'Chèque'];
        $amount = round(((float) $invoice->total) / max(1, $count + 1), 2);

        for ($i = 0; $i < $count; $i++) {
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_date' => now()->subDays($count - $i)->toDateString(),
                'amount' => $amount,
                'payment_method' => $methods[$i % count($methods)],
                'payment_reference' => $count > 0 ? 'REF-PAY-'.($i + 1) : null,
                'source' => InvoicePayment::SOURCE_MANUAL,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Invoice $invoice): array
    {
        $taxes = DocumentTaxBreakdown::fromDocument($invoice, $invoice->items);

        return array_merge(
            CommercialDocumentView::forInvoice($invoice, $taxes),
            [
                'invoice' => $invoice,
                'taxes' => $taxes,
                'generatedBy' => 'Test PDF',
            ]
        );
    }

    private function renderPdfPageCount(Invoice $invoice): int
    {
        $pdf = Pdf::loadView('documents.pdf', $this->viewData($invoice));
        $pdf->setPaper('a4', 'portrait');
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        return (int) $dompdf->getCanvas()->get_page_count();
    }

    private function renderPdfBinary(Invoice $invoice): string
    {
        $pdf = Pdf::loadView('documents.pdf', $this->viewData($invoice));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
