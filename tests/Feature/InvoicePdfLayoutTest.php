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
        $this->assertStringNotContainsString('facture-products-totals-keep', $html);
        $this->assertStringContainsString('facture-page-footer', $html);
        $this->assertStringContainsString('Règlement / Paiement', $html);
        $this->assertStringContainsString('Cachet de la société', $html);
        $this->assertStringContainsString('Mode de règlement', $html);
        $this->assertStringContainsString('Espèces', $html);
        $this->assertStringContainsString('Sous-total TTC', $html);
        $this->assertStringContainsString('TOTAL FACTURE TTC', $html);

        $itemsPos = strpos($html, 'class="facture-items"');
        $totalsPos = strpos($html, 'class="facture-totals-block"');
        $notesPos = strpos($html, 'class="facture-notes-box"');
        $footerPos = strpos($html, 'class="facture-page-footer"');
        $closingPos = strpos($html, 'class="facture-closing-table"');
        $settlementPos = strpos($html, 'class="facture-settlement-box"');
        $signaturePos = strpos($html, 'class="facture-signature-box"');

        $this->assertNotFalse($itemsPos);
        $this->assertNotFalse($totalsPos);
        $this->assertNotFalse($notesPos);
        $this->assertNotFalse($footerPos);
        $this->assertNotFalse($closingPos);
        $this->assertNotFalse($settlementPos);
        $this->assertNotFalse($signaturePos);

        $this->assertTrue($itemsPos < $totalsPos, 'Totaux après le tableau des articles');
        $this->assertTrue($totalsPos < $notesPos, 'Notes après les totaux');
        $this->assertTrue($notesPos < $footerPos, 'Pied de page après les notes');
        $this->assertTrue($footerPos < $closingPos);
        $this->assertTrue($settlementPos > $closingPos && $signaturePos > $closingPos);
    }

    public function test_invoice_pdf_page_counts_for_small_and_large_product_lists(): void
    {
        $cases = [
            '1 produit / 0 paiement' => ['items' => 1, 'payments' => 0, 'exact_pages' => 1],
            '3 produits / 1 paiement' => ['items' => 3, 'payments' => 1, 'exact_pages' => 1],
            '3 produits / 2 paiements' => ['items' => 3, 'payments' => 2, 'exact_pages' => 1],
            '40 produits / 0 paiement' => ['items' => 40, 'payments' => 0, 'min_pages' => 2, 'max_pages' => 3],
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

    public function test_large_invoice_fills_each_page_above_the_fixed_footer(): void
    {
        $invoice = $this->makeInvoiceWithItems(40);
        $this->attachPayments($invoice, 2);
        $invoice = $invoice->fresh(['client', 'items', 'payments', 'adjustments']);

        $layout = $this->renderLayout($invoice);

        $this->assertGreaterThanOrEqual(2, $layout['page_count']);
        $this->assertLessThanOrEqual(3, $layout['page_count']);
        $this->assertFixedFooterPagination($layout);
        $this->assertNotNull($layout['totals_page']);
        $this->assertNotNull($layout['last_item_page']);
        $this->assertGreaterThanOrEqual($layout['totals_page'], $layout['last_item_page']);
        $this->assertLessThanOrEqual($layout['last_item_page'] + 1, $layout['totals_page']);
    }

    public function test_example_invoice_continues_rows_only_when_page_one_is_full(): void
    {
        $designations = [
            'ESSUIE GLACE SUR MESURE',
            'Tapis 7D sur mesure Peugeot 208 Hybrid 2025 (FAST-T7D-0145)',
            'AREON',
            'AlloyGator – Protections de Jantes Premium Rouges | Sécurité & Style pour vos Jantes – 22 POUCES – FTC-ALLOYGATOR-RED-22',
            'Tapis de coffre 4D Range Rover Sport 2013 (FAST-TCOF-144)',
            'Tapis de sol Proline Mercedes GLC X253 2015-2022 (FAST-TAP-PRO-0102)',
            'Tapis de sol PROLINE Land Rover Range Rover Sport III 2022 (FAST-TAP-PRO-092)',
            'Tapis de sol Proline Mercedes GLE W167 SUV 2019 (FAST-TAP-PRO-0106)',
        ];
        $invoice = $this->makeInvoiceWithItems(count($designations), $designations);
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => '2026-10-08',
            'amount' => (float) $invoice->total,
            'payment_method' => 'Virement bancaire',
            'source' => InvoicePayment::SOURCE_MANUAL,
        ]);
        $invoice = $invoice->fresh(['client', 'items', 'payments', 'adjustments']);

        $layout = $this->renderLayout($invoice);

        $this->assertSame(2, $layout['page_count'], 'Huit lignes longues doivent tenir sur deux pages');
        $this->assertFixedFooterPagination($layout);
        $this->assertGreaterThan(0, count($layout['pages'][1]['item_rows'] ?? []));
        $this->assertGreaterThan(0, count($layout['pages'][2]['item_rows'] ?? []));
        $this->assertLessThan(140, $layout['pages'][2]['item_rows'][0]['y'], 'La page 2 commence par les lignes, sans en-tête répété');
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

    /**
     * @param  array<string, mixed>  $layout
     */
    private function assertFixedFooterPagination(array $layout): void
    {
        $pageHeight = $layout['page_height'];
        $edge = 16 * 72 / 25.4;
        $footerTops = [];

        $this->assertNotEmpty($layout['pages']);

        foreach ($layout['pages'] as $pageNumber => $page) {
            $this->assertNotNull($page['footer'], "Pied de page absent de la page {$pageNumber}");
            $footer = $page['footer'];
            $footerTops[] = $footer['y'];
            $footerBottom = $footer['y'] + $footer['h'];

            $this->assertLessThanOrEqual(
                $footer['y'] + 1.5,
                $page['flow_bottom'],
                "Le contenu de la page {$pageNumber} chevauche le pied de page"
            );
            $this->assertEqualsWithDelta(
                $pageHeight - $edge,
                $footerBottom,
                10,
                "Le pied de page de la page {$pageNumber} n'est pas calé en bas"
            );

            $next = $layout['pages'][$pageNumber + 1] ?? null;
            if ($next && $next['first_flow'] !== null && $page['flow_bottom'] > 0) {
                $gap = $footer['y'] - $page['flow_bottom'];
                $this->assertLessThan(
                    $next['first_flow']['h'] + 6,
                    $gap,
                    "La page {$pageNumber} laisse un vide assez grand pour le bloc suivant"
                );
            }
        }

        $this->assertEqualsWithDelta(
            $footerTops[0],
            min($footerTops),
            2,
            'Le pied de page ne reste pas à la même hauteur sur chaque page'
        );
        $this->assertEqualsWithDelta(max($footerTops), min($footerTops), 2);
    }

    /**
     * @param  list<string>|null  $designations
     */
    private function makeInvoiceWithItems(int $count, ?array $designations = null): Invoice
    {
        $client = Client::create(['name' => 'NEVIS CAR']);
        $unit = 100.0;
        $lineTotal = $unit * 1.2;
        $subtotal = $unit * $count;
        $total = $lineTotal * $count;

        $invoice = Invoice::create([
            'invoice_number' => 'FAC-LAYOUT-'.uniqid(),
            'client_id' => $client->id,
            'invoice_date' => '2026-10-08',
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
                'designation' => $designations[$i - 1] ?? ('Produit test '.$i),
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

    /**
     * @return array<string, mixed>
     */
    private function renderLayout(Invoice $invoice): array
    {
        $pdf = Pdf::loadView('documents.pdf', $this->viewData($invoice));
        $pdf->setPaper('a4', 'portrait');
        $dompdf = $pdf->getDomPDF();

        $pages = [];
        $dompdf->setCallbacks([
            [
                'event' => 'end_page_render',
                'f' => function ($frame, $canvas) use (&$pages) {
                    $pageNumber = (int) $canvas->get_page_number();
                    $boxes = [];
                    $this->collectFrames($frame, $boxes);
                    $pages[$pageNumber] = $this->summarizePage($boxes);
                },
            ],
        ]);
        $dompdf->render();

        $totalsPage = null;
        $lastItemPage = null;
        foreach ($pages as $number => $page) {
            if ($page['has_totals']) {
                $totalsPage = $number;
            }
            if ($page['item_rows'] !== []) {
                $lastItemPage = $number;
            }
        }

        return [
            'page_count' => (int) $dompdf->getCanvas()->get_page_count(),
            'page_height' => (float) $dompdf->getCanvas()->get_height(),
            'pages' => $pages,
            'totals_page' => $totalsPage,
            'last_item_page' => $lastItemPage,
        ];
    }

    /**
     * @param  list<array{class: string, node: string, y: float, h: float, bottom: float}>  $boxes
     * @return array<string, mixed>
     */
    private function summarizePage(array $boxes): array
    {
        $footer = null;
        $flowBottom = 0.0;
        $itemRows = [];
        $hasTotals = false;
        $firstFlow = null;

        foreach ($boxes as $box) {
            if (str_contains($box['class'], 'facture-page-footer') && $box['node'] === 'div') {
                $footer = $box;
                continue;
            }
            if (str_contains($box['class'], 'facture-doc') && $box['node'] === 'div') {
                $flowBottom = max($flowBottom, $box['bottom']);
            }
            if ($box['class'] === 'item-row') {
                $itemRows[] = $box;
                $firstFlow = $this->earlierFlow($firstFlow, $box);
            }
            if (str_contains($box['class'], 'facture-totals-block')) {
                $hasTotals = true;
                $firstFlow = $this->earlierFlow($firstFlow, $box);
            }
            if (str_contains($box['class'], 'facture-notes-box') || str_contains($box['class'], 'facture-amount-words')) {
                $firstFlow = $this->earlierFlow($firstFlow, $box);
            }
        }

        usort($itemRows, fn ($a, $b) => $a['y'] <=> $b['y']);

        return [
            'footer' => $footer,
            'flow_bottom' => $flowBottom,
            'item_rows' => $itemRows,
            'has_totals' => $hasTotals,
            'first_flow' => $firstFlow,
        ];
    }

    /**
     * @param  array{y: float, h: float}|null  $current
     * @param  array{y: float, h: float}  $candidate
     * @return array{y: float, h: float}
     */
    private function earlierFlow(?array $current, array $candidate): array
    {
        if ($current === null || $candidate['y'] < $current['y']) {
            return $candidate;
        }

        return $current;
    }

    /**
     * @param  list<array{class: string, node: string, y: float, h: float, bottom: float}>  $boxes
     */
    private function collectFrames($frame, array &$boxes): void
    {
        $node = $frame->get_node();
        if ($node instanceof \DOMElement) {
            $y = $frame->get_position('y');
            $h = (float) $frame->get_margin_height();
            if ($y !== null) {
                $class = (string) $node->getAttribute('class');
                if ($class !== '') {
                    $boxes[] = [
                        'class' => $class,
                        'node' => $node->nodeName,
                        'y' => (float) $y,
                        'h' => $h,
                        'bottom' => (float) $y + $h,
                    ];
                }
                $parent = $node->parentNode;
                $table = $parent?->parentNode;
                if (
                    $node->nodeName === 'tr'
                    && $parent instanceof \DOMElement
                    && $parent->nodeName === 'tbody'
                    && $table instanceof \DOMElement
                    && str_contains((string) $table->getAttribute('class'), 'facture-items')
                ) {
                    $boxes[] = [
                        'class' => 'item-row',
                        'node' => 'tr',
                        'y' => (float) $y,
                        'h' => $h,
                        'bottom' => (float) $y + $h,
                    ];
                }
            }
        }

        foreach ($frame->get_children() as $child) {
            $this->collectFrames($child, $boxes);
        }
    }
}
