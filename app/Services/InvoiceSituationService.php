<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Support\InvoiceCommercialStatus;

class InvoiceSituationService
{
    public function forInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['creditNotes', 'payments', 'refunds', 'posSale', 'items', 'adjustments']);

        $initialAmount = round((float) $invoice->computed_total, 2);
        $totalCredits = round((float) $invoice->creditNotes->sum(fn (CreditNote $cn) => (float) $cn->computed_total), 2);
        $netSale = round(max(0, $initialAmount - $totalCredits), 2);
        $totalCollected = round((float) $invoice->total_paid, 2);
        $totalRefunded = round((float) $invoice->refunds->sum('amount'), 2);
        $remainingToRefund = round(max(0, $totalCredits - $totalRefunded), 2);

        $latestCreditNote = $invoice->creditNotes->sortByDesc('credit_note_date')->first();
        $lastReturnDate = $latestCreditNote?->credit_note_date;

        $source = $invoice->source ?? $invoice->posSale?->source;

        return [
            'source' => $source,
            'source_label' => $this->sourceLabel($source),
            'commercial_status' => $invoice->commercial_status,
            'commercial_status_label' => InvoiceCommercialStatus::labels()[$invoice->commercial_status] ?? $invoice->commercial_status,
            'initial_amount' => $initialAmount,
            'total_credits' => $totalCredits,
            'net_sale' => $netSale,
            'total_collected' => $totalCollected,
            'total_refunded' => $totalRefunded,
            'remaining_to_refund' => $remainingToRefund,
            'order_reference' => $invoice->posSale?->ticket_number,
            'order_external_id' => $invoice->posSale?->external_id,
            'credit_notes' => $invoice->creditNotes,
            'refunds' => $invoice->refunds,
            'last_return_date' => $lastReturnDate,
            'currency' => $invoice->currency,
        ];
    }

    public function syncCommercialStatus(Invoice $invoice): string
    {
        $invoice->loadMissing('creditNotes');

        $initialAmount = round((float) $invoice->computed_total, 2);
        $totalCredits = round((float) $invoice->creditNotes->sum(fn (CreditNote $cn) => (float) $cn->computed_total), 2);

        $status = InvoiceCommercialStatus::NORMAL;

        if ($totalCredits > 0.009) {
            $hasExchange = $invoice->creditNotes->contains(fn (CreditNote $cn) => $cn->return_type === 'exchange');

            if ($hasExchange) {
                $status = InvoiceCommercialStatus::EXCHANGE;
            } elseif ($totalCredits >= $initialAmount - 0.009) {
                $latestType = $invoice->creditNotes->sortByDesc('credit_note_date')->first()?->return_type;
                $status = in_array($latestType, ['refund_only', 'partial_refund'], true)
                    ? InvoiceCommercialStatus::FULLY_REFUNDED
                    : InvoiceCommercialStatus::TOTAL_RETURN;
            } else {
                $latestType = $invoice->creditNotes->sortByDesc('credit_note_date')->first()?->return_type;
                $status = in_array($latestType, ['refund_only', 'partial_refund'], true)
                    ? InvoiceCommercialStatus::PARTIAL_REFUND
                    : InvoiceCommercialStatus::PARTIAL_RETURN;
            }
        }

        if ($invoice->commercial_status !== $status) {
            $invoice->update(['commercial_status' => $status]);
        }

        return $status;
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            'shopify' => 'Shopify',
            'jumia' => 'Jumia',
            'libromart' => 'Vente directe',
            'pos' => 'Point de vente',
            null, '' => 'Vente directe',
            default => ucfirst($source),
        };
    }
}
