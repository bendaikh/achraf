<?php

namespace App\Services;

use App\Models\InvoicePayment;
use Illuminate\Support\Collection;

/**
 * Recover fee / tracking / net details already present on import lines
 * (or computable from gross − fees) onto invoice_payments for history display.
 */
class PaymentTraceabilityService
{
    /**
     * @return array{updated:int, scanned:int, batches:int}
     */
    public function backfillMissingDetails(?int $limit = null): array
    {
        $scanned = 0;
        $updated = 0;

        $query = InvoicePayment::query()
            ->with(['paymentImportLine', 'invoice.posSale.fulfillments'])
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->chunkById(200, function (Collection $payments) use (&$scanned, &$updated) {
            foreach ($payments as $payment) {
                $scanned++;
                $changes = $this->resolveMissingFields($payment);
                if ($changes === []) {
                    continue;
                }
                $payment->forceFill($changes)->save();
                $updated++;
            }
        });

        $batches = $this->backfillImportBatches();
        $batches += $this->backfillBulkBatches();
        $importLines = $this->backfillImportLineFees();

        return [
            'updated' => $updated,
            'scanned' => $scanned,
            'batches' => $batches,
            'import_lines' => $importLines,
        ];
    }

    /**
     * Re-extract fees/net from stored raw carrier rows onto import line columns.
     */
    protected function backfillImportLineFees(): int
    {
        $updated = 0;
        $service = app(PaymentImportService::class);

        \App\Models\PaymentImportLine::query()
            ->orderBy('id')
            ->chunkById(200, function (Collection $lines) use ($service, &$updated) {
                foreach ($lines as $line) {
                    $fields = $service->extractRowFields($line->file_raw ?? []);
                    $changes = [];

                    if ($line->file_delivery_fees === null && ($fields['delivery_fees'] ?? null) !== null) {
                        $changes['file_delivery_fees'] = round((float) $fields['delivery_fees'], 2);
                    }
                    if ($line->file_net_amount === null && ($fields['net_amount'] ?? null) !== null) {
                        $changes['file_net_amount'] = round((float) $fields['net_amount'], 2);
                    }
                    if ($line->file_amount === null && ($fields['gross_amount'] ?? null) !== null) {
                        $changes['file_amount'] = round((float) $fields['gross_amount'], 2);
                    }

                    if ($changes === []) {
                        continue;
                    }

                    $line->forceFill($changes)->save();
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Group already-imported payments that share a payment_import_id under one batch.
     */
    protected function backfillImportBatches(): int
    {
        $batches = 0;

        InvoicePayment::query()
            ->select('payment_import_id')
            ->whereNotNull('payment_import_id')
            ->whereNull('payment_batch_id')
            ->groupBy('payment_import_id')
            ->pluck('payment_import_id')
            ->each(function ($importId) use (&$batches) {
                $batchId = (string) \Illuminate\Support\Str::uuid();
                $count = InvoicePayment::query()
                    ->where('payment_import_id', $importId)
                    ->whereNull('payment_batch_id')
                    ->update(['payment_batch_id' => $batchId]);
                if ($count > 0) {
                    $batches++;
                }
            });

        return $batches;
    }

    /**
     * Reconstruct bulk settlement groups for legacy rows that only shared date/reference/source.
     */
    protected function backfillBulkBatches(): int
    {
        $batches = 0;

        $groups = InvoicePayment::query()
            ->where('source', InvoicePayment::SOURCE_BULK)
            ->whereNull('payment_batch_id')
            ->get()
            ->groupBy(function (InvoicePayment $payment) {
                $created = $payment->created_at?->format('Y-m-d H:i') ?? 'unknown';

                return implode('|', [
                    $payment->payment_date?->format('Y-m-d') ?? '',
                    (string) ($payment->payment_reference ?? ''),
                    (string) ($payment->payment_method ?? ''),
                    $created,
                ]);
            });

        foreach ($groups as $payments) {
            if ($payments->count() < 2) {
                continue;
            }
            $batchId = (string) \Illuminate\Support\Str::uuid();
            InvoicePayment::query()
                ->whereIn('id', $payments->pluck('id'))
                ->update(['payment_batch_id' => $batchId]);
            $batches++;
        }

        return $batches;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveMissingFields(InvoicePayment $payment): array
    {
        $line = $payment->paymentImportLine
            ?? $this->findBestImportLine($payment);

        $changes = [];

        if ($line && ! $payment->payment_import_row_id) {
            $changes['payment_import_row_id'] = $line->id;
            if (! $payment->payment_import_id) {
                $changes['payment_import_id'] = $line->payment_import_id;
            }
        }

        $rawFees = null;
        $rawNet = null;
        $rawGross = null;
        if ($line) {
            $fields = app(PaymentImportService::class)->extractRowFields($line->file_raw ?? []);
            $rawFees = $fields['delivery_fees'] ?? null;
            $rawNet = $fields['net_amount'] ?? null;
            $rawGross = $fields['gross_amount'] ?? null;
        }

        $gross = $payment->gross_amount !== null
            ? (float) $payment->gross_amount
            : ($line?->file_amount !== null ? (float) $line->file_amount : ($rawGross ?? (float) $payment->amount));

        if ($payment->gross_amount === null) {
            $changes['gross_amount'] = round($gross, 2);
        }

        $fees = $payment->delivery_fees !== null
            ? (float) $payment->delivery_fees
            : ($line?->file_delivery_fees !== null ? (float) $line->file_delivery_fees : $rawFees);

        $net = $payment->net_received !== null
            ? (float) $payment->net_received
            : ($line?->file_net_amount !== null ? (float) $line->file_net_amount : $rawNet);

        $breakdown = PaymentFeeBreakdown::normalize([
            'amount' => $gross,
            'gross_amount' => $gross,
            'delivery_fees' => $fees,
            'net_received' => $net,
        ]);

        if ($payment->delivery_fees === null && $breakdown['delivery_fees'] !== null) {
            $changes['delivery_fees'] = $breakdown['delivery_fees'];
        }

        if ($payment->net_received === null && $breakdown['net_received'] !== null) {
            $changes['net_received'] = $breakdown['net_received'];
        }

        if (! $payment->tracking_number) {
            $tracking = $line?->resolved_tracking
                ?? $line?->file_tracking
                ?? $payment->invoice?->posSale?->primaryTrackingNumber();
            if ($tracking) {
                $changes['tracking_number'] = $tracking;
            }
        }

        if (! $payment->payment_reference && $line?->file_reference) {
            $changes['payment_reference'] = $line->file_reference;
        }

        if (! $payment->carrier && $line) {
            $carrier = app(PaymentImportService::class)->extractRowFields($line->file_raw ?? [])['carrier'] ?? null;
            if ($carrier) {
                $changes['carrier'] = $carrier;
            }
        }

        // Recover from reconciliation activity metadata when available.
        if (($changes['delivery_fees'] ?? $payment->delivery_fees) === null
            || ($changes['net_received'] ?? $payment->net_received) === null) {
            $meta = $this->findReconciliationMetadata($payment);
            if ($meta) {
                if (($changes['delivery_fees'] ?? $payment->delivery_fees) === null && isset($meta['delivery_fees'])) {
                    $changes['delivery_fees'] = round((float) $meta['delivery_fees'], 2);
                }
                if (($changes['net_received'] ?? $payment->net_received) === null && isset($meta['net_received'])) {
                    $changes['net_received'] = round((float) $meta['net_received'], 2);
                }
                if (! ($changes['tracking_number'] ?? $payment->tracking_number) && ! empty($meta['tracking'])) {
                    $changes['tracking_number'] = $meta['tracking'];
                }
            }
        }

        return $changes;
    }

    protected function findBestImportLine(InvoicePayment $payment): ?\App\Models\PaymentImportLine
    {
        if (! $payment->invoice_id) {
            return null;
        }

        $query = \App\Models\PaymentImportLine::query()
            ->where('invoice_id', $payment->invoice_id)
            ->orderByDesc('id');

        if ($payment->tracking_number) {
            $tracking = $payment->tracking_number;
            $matched = (clone $query)->where(function ($q) use ($tracking) {
                $q->where('file_tracking', $tracking)
                    ->orWhere('resolved_tracking', $tracking);
            })->first();
            if ($matched) {
                return $matched;
            }
        }

        $byAmount = (clone $query)
            ->whereNotNull('file_amount')
            ->whereRaw('ABS(file_amount - ?) < 0.02', [(float) $payment->amount])
            ->first();

        return $byAmount ?? $query->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findReconciliationMetadata(InvoicePayment $payment): ?array
    {
        if (! $payment->invoice_id) {
            return null;
        }

        $activity = \App\Models\InvoiceActivity::query()
            ->where('invoice_id', $payment->invoice_id)
            ->where('event', \App\Models\InvoiceActivity::EVENT_PAYMENT_RECONCILED)
            ->latest('id')
            ->first();

        return is_array($activity?->metadata) ? $activity->metadata : null;
    }
}
