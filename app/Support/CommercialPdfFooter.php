<?php

namespace App\Support;

/**
 * Reserved height of the fixed commercial-document footer for DomPDF.
 *
 * DomPDF repeats position:fixed elements on every page and paginates flowing
 * content inside the @page content box. The bottom margin must therefore equal
 * the rendered footer height plus the gap above it and the physical page edge,
 * and the footer is pulled into that margin with a negative bottom offset.
 */
class CommercialPdfFooter
{
    private const PX_TO_PT = 0.75;

    private const LINE_HEIGHT = 1.2;

    /** Distance from the page edge to the bottom of the accent bar (matches @page). */
    private const EDGE_MM = 16.0;

    /** Space kept between the last flowing row and the top of the footer. */
    private const GAP_PT = 8.0;

    /**
     * Absorbs sub-pixel border and font-metric differences so content never
     * overlaps the footer. Kept small so page 1 is not left blank.
     */
    private const SAFETY_PT = 10.0;

    /**
     * @param  array<string, mixed>|null  $settlement
     * @return array{footer_pt: float, pull_mm: string, reserve_mm: string}
     */
    public static function forSettlement(?array $settlement): array
    {
        $footerPt = self::footerHeightPt($settlement);
        $pullPt = $footerPt + self::GAP_PT;
        $edgePt = self::EDGE_MM * 72 / 25.4;
        $reservePt = $edgePt + $pullPt;

        return [
            'footer_pt' => $footerPt,
            'pull_mm' => self::mm($pullPt),
            'reserve_mm' => self::mm($reservePt),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settlement
     */
    public static function footerHeightPt(?array $settlement): float
    {
        $columns = self::signatureColumnPt();
        if ($settlement !== null) {
            $columns = max($columns, self::settlementColumnPt($settlement));
        }

        return $columns + self::footerChromePt() + self::SAFETY_PT;
    }

    private static function signatureColumnPt(): float
    {
        return self::linePt(9, marginBottomPx: 4) + self::px(110 + 4);
    }

    /**
     * @param  array<string, mixed>  $settlement
     */
    private static function settlementColumnPt(array $settlement): float
    {
        $payments = array_values($settlement['payments'] ?? []);
        $count = count($payments);

        $height = self::px(8 + 8 + 2 + 2);
        $height += self::linePt(11, marginBottomPx: 6, paddingBottomPx: 3, borderBottomPx: 1);
        $height += self::linePt(10, marginBottomPx: 2);

        if ($count === 0) {
            $height += self::linePt(10, marginBottomPx: 2);
        } elseif ($count === 1) {
            $height += self::linePt(10, marginBottomPx: 2);
            $height += self::linePt(10, marginBottomPx: 2);
            $height += self::linePt(10, marginBottomPx: 2);
            if (self::hasReference($payments[0])) {
                $height += self::linePt(10, marginBottomPx: 2);
            }
        } else {
            foreach ($payments as $payment) {
                $height += self::px(6 + 4 + 1);
                $height += self::linePt(10, marginBottomPx: 3);
                $height += self::linePt(10, marginBottomPx: 2);
                $height += self::linePt(10, marginBottomPx: 2);
                $height += self::linePt(10, marginBottomPx: 2);
                if (self::hasReference($payment)) {
                    $height += self::linePt(10, marginBottomPx: 2);
                }
            }
            $height += self::linePt(11, marginTopPx: 6, marginBottomPx: 2);
        }

        $height += self::linePt(11, marginTopPx: 4, marginBottomPx: 2);

        return $height;
    }

    private static function footerChromePt(): float
    {
        return self::px(14) + self::linePt(9) + self::px(10 + 10 + 10);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private static function hasReference(array $payment): bool
    {
        return isset($payment['reference']) && trim((string) $payment['reference']) !== '';
    }

    private static function linePt(
        float $fontPx,
        float $marginTopPx = 0,
        float $marginBottomPx = 0,
        float $paddingTopPx = 0,
        float $paddingBottomPx = 0,
        float $borderTopPx = 0,
        float $borderBottomPx = 0,
    ): float {
        return self::px(
            ($fontPx * self::LINE_HEIGHT)
            + $marginTopPx
            + $marginBottomPx
            + $paddingTopPx
            + $paddingBottomPx
            + $borderTopPx
            + $borderBottomPx
        );
    }

    private static function px(float $px): float
    {
        return $px * self::PX_TO_PT;
    }

    private static function mm(float $pt): string
    {
        return number_format($pt * 25.4 / 72, 3, '.', '');
    }
}
