<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRecordingService
{
    public function __construct(
        protected SupplierAccountService $supplierAccounts
    ) {}

    /**
     * @param  array{
     *   payment_date: string,
     *   amount: float|int|string,
     *   payment_method: string,
     *   payment_reference?: ?string,
     *   notes?: ?string,
     *   payment_file_path?: ?string,
     *   source?: string,
     *   tracking_number?: ?string,
     *   carrier?: ?string,
     *   payment_import_id?: ?int,
     *   payment_import_line_id?: ?int,
     *   dedupe_key?: ?string,
     *   allow_overpayment?: bool,
     *   user_id?: ?int,
     *   gross_amount?: ?float,
     *   delivery_fees?: ?float,
     *   net_received?: ?float,
     *   payment_batch_id?: ?string,
     *   reconciliation_metadata?: array,
     * }  $data
     */
    public function recordInvoicePayment(Invoice $invoice, array $data): InvoicePayment
    {
        $breakdown = PaymentFeeBreakdown::normalize([
            'amount' => $data['amount'] ?? null,
            'gross_amount' => $data['gross_amount'] ?? $data['amount'] ?? null,
            'delivery_fees' => array_key_exists('delivery_fees', $data) ? $data['delivery_fees'] : null,
            'net_received' => array_key_exists('net_received', $data) ? $data['net_received'] : null,
        ]);

        // Invoice balance always uses the gross (montant facture / CRBT).
        $amount = $breakdown['gross_amount'];
        $allowOverpayment = (bool) ($data['allow_overpayment'] ?? false);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant du paiement doit être supérieur à 0.',
            ]);
        }

        $remaining = round($invoice->remaining_balance, 2);
        if ($amount > $remaining + 0.009 && ! $allowOverpayment) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Le montant (%.2f) dépasse le solde restant (%.2f). Autorisez le trop-perçu pour continuer.',
                    $amount,
                    $remaining
                ),
            ]);
        }

        $dedupeKey = $data['dedupe_key'] ?? $this->buildDedupeKey([
            'scope' => 'sales',
            'invoice_id' => $invoice->id,
            'reference' => $data['payment_reference'] ?? null,
            'tracking' => $data['tracking_number'] ?? null,
            'import_line_id' => $data['payment_import_line_id'] ?? null,
            'amount' => $amount,
            'date' => $data['payment_date'] ?? null,
            'source' => $data['source'] ?? InvoicePayment::SOURCE_MANUAL,
            'bulk_batch' => $data['payment_batch_id'] ?? $data['bulk_batch'] ?? null,
        ]);

        if ($dedupeKey && InvoicePayment::query()->where('dedupe_key', $dedupeKey)->exists()) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Un paiement identique a déjà été enregistré (anti-doublon).',
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $amount, $breakdown, $dedupeKey, $allowOverpayment) {
            $payment = $invoice->payments()->create([
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'gross_amount' => $breakdown['gross_amount'],
                'delivery_fees' => $breakdown['delivery_fees'],
                'net_received' => $breakdown['net_received'],
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_file_path' => $data['payment_file_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? InvoicePayment::SOURCE_MANUAL,
                'payment_batch_id' => $data['payment_batch_id'] ?? $data['bulk_batch'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? $invoice->posSale?->primaryTrackingNumber(),
                'carrier' => $data['carrier'] ?? null,
                'created_by' => $data['user_id'] ?? $data['created_by'] ?? Auth::id(),
                'pos_sale_id' => $invoice->pos_sale_id,
                'payment_import_id' => $data['payment_import_id'] ?? null,
                'payment_import_row_id' => $data['payment_import_line_id'] ?? $data['payment_import_row_id'] ?? null,
                'dedupe_key' => $dedupeKey,
                'allow_overpayment' => $allowOverpayment,
            ]);

            $invoice->refresh();
            $invoice->load('items');
            $invoice->syncPaymentStatus();

            if (($data['source'] ?? '') === InvoicePayment::SOURCE_IMPORT && ! empty($data['reconciliation_metadata'])) {
                $this->recordImportReconciliationActivity($invoice, $payment, $data['reconciliation_metadata']);
            }

            return $payment;
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordImportReconciliationActivity(Invoice $invoice, InvoicePayment $payment, array $metadata): void
    {
        $gross = $metadata['gross_amount'] ?? $payment->gross_amount ?? $payment->amount;
        $fees = $metadata['delivery_fees'] ?? $payment->delivery_fees;
        $net = $metadata['net_received'] ?? $payment->net_received;
        $tracking = $metadata['tracking'] ?? $payment->tracking_number;
        $carrier = $metadata['carrier'] ?? $payment->carrier;
        $importFile = $metadata['import_file'] ?? null;

        $description = sprintf(
            'Paiement rapproché via import transporteur — Montant facture : %s DH — Frais livraison : %s DH — Net encaissé : %s DH — Tracking : %s — Date : %s.',
            number_format((float) $gross, 2, '.', ''),
            $fees !== null ? number_format((float) $fees, 2, '.', '') : '—',
            $net !== null ? number_format((float) $net, 2, '.', '') : '—',
            $tracking ?: '—',
            $payment->payment_date?->format('d/m/Y') ?? now()->format('d/m/Y')
        );

        $invoice->recordActivity(
            \App\Models\InvoiceActivity::EVENT_PAYMENT_RECONCILED,
            $description,
            $payment->created_by,
            array_filter([
                'payment_id' => $payment->id,
                'import_file' => $importFile,
                'order_number' => $metadata['order_number'] ?? null,
                'tracking' => $tracking,
                'carrier' => $carrier,
                'gross_amount' => $gross,
                'delivery_fees' => $fees,
                'net_received' => $net,
                'match_criteria' => $metadata['match_criteria'] ?? null,
                'match_score' => $metadata['match_score'] ?? null,
            ], fn ($value) => $value !== null && $value !== '')
        );
    }

    /**
     * @param  list<array{
     *   invoice_id:int,
     *   amount:float|int|string,
     *   delivery_fees?:float|int|string|null,
     *   net_received?:float|int|string|null,
     *   allow_overpayment?:bool,
     *   tracking_number?:?string
     * }>  $lines
     * @return list<InvoicePayment>
     */
    public function recordBulkInvoicePayments(array $lines, array $shared): array
    {
        $payments = [];
        $batchId = $shared['payment_batch_id'] ?? $shared['bulk_batch'] ?? (string) \Illuminate\Support\Str::uuid();

        DB::transaction(function () use ($lines, $shared, $batchId, &$payments) {
            foreach ($lines as $line) {
                $invoice = Invoice::query()->with(['items', 'posSale.fulfillments'])->findOrFail($line['invoice_id']);
                $payments[] = $this->recordInvoicePayment($invoice, array_merge($shared, [
                    'amount' => $line['amount'],
                    'gross_amount' => $line['amount'],
                    'delivery_fees' => $line['delivery_fees'] ?? null,
                    'net_received' => $line['net_received'] ?? null,
                    'allow_overpayment' => (bool) ($line['allow_overpayment'] ?? false),
                    'tracking_number' => $line['tracking_number'] ?? $invoice->posSale?->primaryTrackingNumber(),
                    'source' => $shared['source'] ?? InvoicePayment::SOURCE_BULK,
                    'payment_batch_id' => $batchId,
                    'dedupe_key' => $this->buildDedupeKey([
                        'scope' => 'sales',
                        'invoice_id' => $invoice->id,
                        'reference' => $shared['payment_reference'] ?? null,
                        'tracking' => $line['tracking_number'] ?? $invoice->posSale?->primaryTrackingNumber(),
                        'amount' => round((float) $line['amount'], 2),
                        'date' => $shared['payment_date'] ?? null,
                        'source' => 'bulk',
                        'bulk_batch' => $batchId,
                    ]),
                ]));
            }
        });

        return $payments;
    }

    /**
     * @param  array{
     *   payment_date: string,
     *   amount: float|int|string,
     *   payment_method: string,
     *   payment_reference?: ?string,
     *   notes?: ?string,
     *   payment_file_path?: ?string,
     *   source?: string,
     *   tracking_number?: ?string,
     *   payment_import_id?: ?int,
     *   payment_import_line_id?: ?int,
     *   dedupe_key?: ?string,
     *   allow_overpayment?: bool,
     *   user_id?: ?int,
     * }  $data
     */
    public function recordSupplierPayment(SupplierInvoice $invoice, array $data): SupplierInvoicePayment
    {
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant du paiement doit être supérieur à 0.',
            ]);
        }

        $dedupeKey = $data['dedupe_key'] ?? $this->buildDedupeKey([
            'scope' => 'purchases',
            'invoice_id' => $invoice->id,
            'reference' => $data['payment_reference'] ?? null,
            'tracking' => $data['tracking_number'] ?? null,
            'import_line_id' => $data['payment_import_line_id'] ?? null,
            'amount' => $amount,
            'date' => $data['payment_date'] ?? null,
            'source' => $data['source'] ?? SupplierInvoicePayment::SOURCE_MANUAL,
        ]);

        if ($dedupeKey && SupplierInvoicePayment::query()->where('dedupe_key', $dedupeKey)->exists()) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Un paiement identique a déjà été enregistré (anti-doublon).',
            ]);
        }

        $header = $this->supplierAccounts->recordSettlement($invoice->supplier, array_merge($data, [
            'amount' => $amount,
            'invoice_ids' => [$invoice->id],
            'use_credits' => (bool) ($data['use_credits'] ?? false),
            'use_advances' => (bool) ($data['use_advances'] ?? true),
            'dedupe_key' => $dedupeKey,
        ]));

        return $header->invoicePayments->first() ?? new SupplierInvoicePayment([
            'supplier_payment_id' => $header->id,
            'supplier_id' => $invoice->supplier_id,
            'amount' => $header->unallocated_amount,
        ]);
    }

    /**
     * @param  list<array{supplier_invoice_id:int, amount:float|int|string, allow_overpayment?:bool}>  $lines
     * @return list<SupplierInvoicePayment>
     */
    public function recordBulkSupplierPayments(array $lines, array $shared): array
    {
        $payments = [];

        DB::transaction(function () use ($lines, $shared, &$payments) {
            $invoices = SupplierInvoice::query()
                ->whereIn('id', collect($lines)->pluck('supplier_invoice_id'))
                ->get()
                ->keyBy('id');

            $groups = collect($lines)->groupBy(function (array $line) use ($invoices) {
                $invoice = $invoices->get($line['supplier_invoice_id']);

                return $invoice?->supplier_id ?: 0;
            });

            foreach ($groups as $supplierId => $group) {
                $first = $invoices->get($group->first()['supplier_invoice_id']);
                if (! $first) {
                    continue;
                }

                $cashAllocations = [];
                $total = 0.0;
                foreach ($group as $line) {
                    $amount = round((float) $line['amount'], 2);
                    $cashAllocations[(int) $line['supplier_invoice_id']] = $amount;
                    $total += $amount;
                }

                $header = $this->supplierAccounts->recordSettlement($first->supplier, array_merge($shared, [
                    'amount' => round($total, 2),
                    'invoice_ids' => $group->pluck('supplier_invoice_id')->map(fn ($id) => (int) $id)->all(),
                    'cash_allocations' => $cashAllocations,
                    'use_credits' => (bool) ($shared['use_credits'] ?? false),
                    'use_advances' => (bool) ($shared['use_advances'] ?? true),
                    'source' => $shared['source'] ?? SupplierInvoicePayment::SOURCE_BULK,
                    'dedupe_key' => $this->buildDedupeKey([
                        'scope' => 'purchases',
                        'reference' => $shared['payment_reference'] ?? null,
                        'amount' => round($total, 2),
                        'date' => $shared['payment_date'] ?? null,
                        'source' => 'bulk',
                        'bulk_batch' => $shared['bulk_batch'] ?? null,
                        'supplier_id' => $supplierId,
                    ]),
                ]));

                foreach ($header->invoicePayments as $payment) {
                    $payments[] = $payment;
                }
            }
        });

        return $payments;
    }

    public function buildDedupeKey(array $parts): ?string
    {
        $normalized = [];
        foreach ($parts as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $normalized[$key] = is_string($value) ? mb_strtolower(trim($value)) : $value;
        }

        // Manual one-off payments without reference/tracking should not block future payments
        $hasIdentity = isset($normalized['reference'])
            || isset($normalized['tracking'])
            || isset($normalized['import_line_id']);

        if (! $hasIdentity && ($normalized['source'] ?? '') === 'manual') {
            return null;
        }

        if (! $hasIdentity && ($normalized['source'] ?? '') === 'bulk') {
            // Bulk without reference: include invoice + amount + date + batch
            if (! isset($normalized['bulk_batch'])) {
                $normalized['bulk_batch'] = uniqid('bulk_', true);
            }
        }

        if ($normalized === []) {
            return null;
        }

        ksort($normalized);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    }
}
