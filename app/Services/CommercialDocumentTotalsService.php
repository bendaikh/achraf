<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\DocumentTaxBreakdown;
use App\Support\LineItemCalculator;
use Illuminate\Support\Collection;

class CommercialDocumentTotalsService
{
    public function recalculateInvoice(Invoice $invoice): bool
    {
        $invoice->loadMissing('items');

        if ($invoice->items->isEmpty()) {
            return false;
        }

        $changed = false;
        $linesSubtotal = 0.0;

        foreach ($invoice->items as $item) {
            $unitPriceHt = LineItemCalculator::normalizeStoredUnitPriceToHt($item);

            $computed = LineItemCalculator::compute([
                'quantity' => $item->quantity,
                'unit_price' => $unitPriceHt,
                'tax_rate' => $item->tax_rate,
                'discount' => $item->discount ?? 0,
                'discount_type' => $item->discount_type ?? 'fixed',
            ]);

            $newLineTotal = $computed['line_total'];
            $linesSubtotal += $newLineTotal;

            $itemUpdates = [];

            if (round((float) $item->unit_price, 2) !== round($unitPriceHt, 2)) {
                $itemUpdates['unit_price'] = $unitPriceHt;
            }

            if (round((float) $item->line_total, 2) !== round($newLineTotal, 2)) {
                $itemUpdates['line_total'] = $newLineTotal;
            }

            if ($itemUpdates !== []) {
                $item->update($itemUpdates);
                $changed = true;
            }
        }

        $taxes = DocumentTaxBreakdown::fromDocument($invoice, $invoice->items()->get());
        $newSubtotal = round($linesSubtotal, 2);
        $newTotal = $taxes['total_ttc'];

        if (
            round((float) $invoice->subtotal, 2) !== $newSubtotal
            || round((float) $invoice->total, 2) !== $newTotal
        ) {
            $invoice->update([
                'subtotal' => $newSubtotal,
                'total' => $newTotal,
            ]);
            $changed = true;
        }

        if ($changed) {
            $invoice->syncPaymentStatus();
        }

        return $changed;
    }

    /**
     * @return array{processed: int, updated: int}
     */
    public function recalculateAllInvoices(): array
    {
        $processed = 0;
        $updated = 0;

        Invoice::query()->with('items')->chunkById(100, function (Collection $invoices) use (&$processed, &$updated) {
            foreach ($invoices as $invoice) {
                $processed++;
                if ($this->recalculateInvoice($invoice)) {
                    $updated++;
                }
            }
        });

        return compact('processed', 'updated');
    }
}
