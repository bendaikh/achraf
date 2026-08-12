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
     * }  $data
     */
    public function recordInvoicePayment(Invoice $invoice, array $data): InvoicePayment
    {
        $amount = round((float) $data['amount'], 2);
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
        ]);

        if ($dedupeKey && InvoicePayment::query()->where('dedupe_key', $dedupeKey)->exists()) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Un paiement identique a déjà été enregistré (anti-doublon).',
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $amount, $dedupeKey, $allowOverpayment) {
            $payment = $invoice->payments()->create([
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_file_path' => $data['payment_file_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? InvoicePayment::SOURCE_MANUAL,
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

            return $payment;
        });
    }

    /**
     * @param  list<array{invoice_id:int, amount:float|int|string, allow_overpayment?:bool, tracking_number?:?string}>  $lines
     * @return list<InvoicePayment>
     */
    public function recordBulkInvoicePayments(array $lines, array $shared): array
    {
        $payments = [];

        DB::transaction(function () use ($lines, $shared, &$payments) {
            foreach ($lines as $line) {
                $invoice = Invoice::query()->with(['items', 'posSale.fulfillments'])->findOrFail($line['invoice_id']);
                $payments[] = $this->recordInvoicePayment($invoice, array_merge($shared, [
                    'amount' => $line['amount'],
                    'allow_overpayment' => (bool) ($line['allow_overpayment'] ?? false),
                    'tracking_number' => $line['tracking_number'] ?? $invoice->posSale?->primaryTrackingNumber(),
                    'source' => $shared['source'] ?? InvoicePayment::SOURCE_BULK,
                    'dedupe_key' => $this->buildDedupeKey([
                        'scope' => 'sales',
                        'invoice_id' => $invoice->id,
                        'reference' => $shared['payment_reference'] ?? null,
                        'tracking' => $line['tracking_number'] ?? $invoice->posSale?->primaryTrackingNumber(),
                        'amount' => round((float) $line['amount'], 2),
                        'date' => $shared['payment_date'] ?? null,
                        'source' => 'bulk',
                        'bulk_batch' => $shared['bulk_batch'] ?? null,
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
        $allowOverpayment = (bool) ($data['allow_overpayment'] ?? false);
        $remaining = max(0, round((float) $invoice->total - (float) $invoice->payments()->sum('amount'), 2));

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant du paiement doit être supérieur à 0.',
            ]);
        }

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

        return DB::transaction(function () use ($invoice, $data, $amount, $dedupeKey, $allowOverpayment) {
            return $invoice->payments()->create([
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_file_path' => $data['payment_file_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? SupplierInvoicePayment::SOURCE_MANUAL,
                'tracking_number' => $data['tracking_number'] ?? null,
                'user_id' => $data['user_id'] ?? Auth::id(),
                'payment_import_id' => $data['payment_import_id'] ?? null,
                'payment_import_row_id' => $data['payment_import_line_id'] ?? $data['payment_import_row_id'] ?? null,
                'dedupe_key' => $dedupeKey,
                'allow_overpayment' => $allowOverpayment,
            ]);
        });
    }

    /**
     * @param  list<array{supplier_invoice_id:int, amount:float|int|string, allow_overpayment?:bool}>  $lines
     * @return list<SupplierInvoicePayment>
     */
    public function recordBulkSupplierPayments(array $lines, array $shared): array
    {
        $payments = [];

        DB::transaction(function () use ($lines, $shared, &$payments) {
            foreach ($lines as $line) {
                $invoice = SupplierInvoice::query()->findOrFail($line['supplier_invoice_id']);
                $payments[] = $this->recordSupplierPayment($invoice, array_merge($shared, [
                    'amount' => $line['amount'],
                    'allow_overpayment' => (bool) ($line['allow_overpayment'] ?? false),
                    'source' => $shared['source'] ?? SupplierInvoicePayment::SOURCE_BULK,
                    'dedupe_key' => $this->buildDedupeKey([
                        'scope' => 'purchases',
                        'invoice_id' => $invoice->id,
                        'reference' => $shared['payment_reference'] ?? null,
                        'amount' => round((float) $line['amount'], 2),
                        'date' => $shared['payment_date'] ?? null,
                        'source' => 'bulk',
                        'bulk_batch' => $shared['bulk_batch'] ?? null,
                    ]),
                ]));
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
