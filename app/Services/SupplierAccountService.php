<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierCreditNoteAllocation;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\SupplierPaymentAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierAccountService
{
    public function money(float|int|string|null $value): float
    {
        return round((float) $value, 2);
    }

    public function invoicePaid(SupplierInvoice $invoice): float
    {
        return $this->money($invoice->payments()->sum('amount'));
    }

    public function invoiceCreditsApplied(SupplierInvoice $invoice): float
    {
        return $this->money($invoice->creditNoteAllocations()->sum('amount'));
    }

    public function invoiceRemaining(SupplierInvoice $invoice): float
    {
        return max(0, $this->money(
            (float) $invoice->total - $this->invoicePaid($invoice) - $this->invoiceCreditsApplied($invoice)
        ));
    }

    public function creditNoteApplied(SupplierCreditNote $creditNote): float
    {
        return $this->money($creditNote->allocations()->sum('amount'));
    }

    public function creditNoteRemaining(SupplierCreditNote $creditNote): float
    {
        return max(0, $this->money((float) $creditNote->total - $this->creditNoteApplied($creditNote)));
    }

    public function availableCreditsTotal(Supplier $supplier): float
    {
        $total = $this->money($supplier->creditNotes()->sum('total'));
        $applied = $this->money(
            SupplierCreditNoteAllocation::query()
                ->whereIn('supplier_credit_note_id', $supplier->creditNotes()->select('id'))
                ->sum('amount')
        );

        return max(0, $this->money($total - $applied));
    }

    public function availableAdvancesTotal(Supplier $supplier): float
    {
        return $this->money($this->activePaymentsQuery($supplier)->sum('unallocated_amount'));
    }

    /**
     * @return array{
     *   total_invoices: float,
     *   total_credits: float,
     *   total_payments: float,
     *   total_advances: float,
     *   open_invoices: float,
     *   available_credits: float,
     *   available_advances: float,
     *   balance: float,
     *   ledger: list<array<string, mixed>>
     * }
     */
    public function statement(Supplier $supplier): array
    {
        $totalInvoices = $this->money($supplier->invoices()->sum('total'));
        $totalCredits = $this->money($supplier->creditNotes()->sum('total'));
        $allocatedPayments = $this->money(
            (float) $this->activePaymentsQuery($supplier)->sum('amount')
            - (float) $this->activePaymentsQuery($supplier)->sum('unallocated_amount')
        );
        $legacyPayments = $this->money(
            SupplierInvoicePayment::query()
                ->whereHas('supplierInvoice', fn ($q) => $q->where('supplier_id', $supplier->id))
                ->whereNull('supplier_payment_id')
                ->sum('amount')
        );
        $totalPayments = $this->money($allocatedPayments + $legacyPayments);
        $totalAdvances = $this->availableAdvancesTotal($supplier);
        $availableCredits = $this->availableCreditsTotal($supplier);
        $openInvoices = $this->money(
            $supplier->invoices()
                ->get()
                ->sum(fn (SupplierInvoice $invoice) => $this->invoiceRemaining($invoice))
        );

        $balance = $this->money($totalInvoices - $totalCredits - $totalPayments - $totalAdvances);

        $supplier->load(['invoices', 'creditNotes', 'accountPayments.allocations.invoice']);

        return [
            'total_invoices' => $totalInvoices,
            'total_credits' => $totalCredits,
            'total_payments' => $totalPayments,
            'total_advances' => $totalAdvances,
            'open_invoices' => $openInvoices,
            'available_credits' => $availableCredits,
            'available_advances' => $totalAdvances,
            'balance' => $balance,
            'ledger' => $this->ledger($supplier),
        ];
    }

    /**
     * @return list<array{date:string, type:string, type_label:string, reference:?string, description:string, debit:float, credit:float, balance:float, url:?string}>
     */
    public function ledger(Supplier $supplier): array
    {
        $entries = collect();

        foreach ($supplier->invoices as $invoice) {
            $entries->push([
                'sort' => $invoice->invoice_date?->format('Y-m-d').'-'.$invoice->id.'-invoice',
                'date' => $invoice->invoice_date?->format('Y-m-d'),
                'type' => 'invoice',
                'type_label' => 'Facture',
                'reference' => $invoice->invoice_number,
                'description' => 'Facture fournisseur',
                'debit' => $this->money($invoice->total),
                'credit' => 0.0,
                'url' => route('supplier-invoices.show', $invoice),
            ]);
        }

        foreach ($supplier->creditNotes as $credit) {
            $entries->push([
                'sort' => $credit->credit_note_date?->format('Y-m-d').'-'.$credit->id.'-credit',
                'date' => $credit->credit_note_date?->format('Y-m-d'),
                'type' => 'credit',
                'type_label' => 'Avoir',
                'reference' => $credit->credit_note_number,
                'description' => 'Avoir fournisseur',
                'debit' => 0.0,
                'credit' => $this->money($credit->total),
                'url' => route('supplier-credit-notes.show', $credit),
            ]);
        }

        foreach ($supplier->accountPayments as $payment) {
            if ($payment->isCancelled()) {
                continue;
            }
            if ($this->money($payment->amount) <= 0.009) {
                continue;
            }

            $allocated = $this->money((float) $payment->amount - (float) $payment->unallocated_amount);
            $label = $payment->payment_number ?: ($payment->chequeLabel() ?: ($payment->payment_method ?: 'Règlement'));
            $entries->push([
                'sort' => $payment->payment_date?->format('Y-m-d').'-'.$payment->id.'-payment',
                'date' => $payment->payment_date?->format('Y-m-d'),
                'type' => 'payment',
                'type_label' => 'Paiement',
                'reference' => $label,
                'description' => $payment->payment_method.($allocated > 0.009 ? ' · affecté '.number_format($allocated, 2, ',', ' ').' DH' : ''),
                'debit' => 0.0,
                'credit' => $this->money($payment->amount),
                'url' => route('purchases.payments.show', $payment),
            ]);
        }

        foreach (
            SupplierInvoicePayment::query()
                ->with('supplierInvoice')
                ->whereHas('supplierInvoice', fn ($q) => $q->where('supplier_id', $supplier->id))
                ->whereNull('supplier_payment_id')
                ->get() as $legacy
        ) {
            $entries->push([
                'sort' => $legacy->payment_date?->format('Y-m-d').'-'.$legacy->id.'-legacy',
                'date' => $legacy->payment_date?->format('Y-m-d'),
                'type' => 'payment',
                'type_label' => 'Paiement',
                'reference' => $legacy->payment_reference,
                'description' => $legacy->payment_method,
                'debit' => 0.0,
                'credit' => $this->money($legacy->amount),
                'url' => $legacy->supplier_invoice_id ? route('supplier-invoices.payments.index', $legacy->supplier_invoice_id) : null,
            ]);
        }

        $running = 0.0;
        return $entries
            ->sortBy('sort')
            ->values()
            ->map(function (array $row) use (&$running) {
                $running = $this->money($running + $row['debit'] - $row['credit']);
                $row['balance'] = $running;
                unset($row['sort']);

                return $row;
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openInvoicesPayload(Supplier $supplier): array
    {
        return $supplier->invoices()
            ->with(['payments', 'creditNoteAllocations'])
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->get()
            ->map(function (SupplierInvoice $invoice) {
                $paid = $this->invoicePaid($invoice);
                $credits = $this->invoiceCreditsApplied($invoice);
                $remaining = $this->invoiceRemaining($invoice);

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'date' => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                    'total' => $this->money($invoice->total),
                    'paid' => $paid,
                    'credits_applied' => $credits,
                    'remaining' => $remaining,
                ];
            })
            ->filter(fn (array $row) => $row['remaining'] > 0.009)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function availableCreditsPayload(Supplier $supplier): array
    {
        return $supplier->creditNotes()
            ->with('allocations')
            ->orderBy('credit_note_date')
            ->orderBy('id')
            ->get()
            ->map(function (SupplierCreditNote $credit) {
                $applied = $this->creditNoteApplied($credit);
                $remaining = $this->creditNoteRemaining($credit);

                return [
                    'id' => $credit->id,
                    'number' => $credit->credit_note_number,
                    'date' => $credit->credit_note_date?->format('Y-m-d'),
                    'total' => $this->money($credit->total),
                    'applied' => $applied,
                    'remaining' => $remaining,
                ];
            })
            ->filter(fn (array $row) => $row['remaining'] > 0.009)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   invoice: SupplierInvoice,
     *   total: float,
     *   paid: float,
     *   credits_applied: float,
     *   remaining: float,
     *   available_credits: float,
     *   available_advances: float,
     *   net_to_pay: float,
     *   payments: Collection<int, SupplierInvoicePayment>,
     *   credit_allocations: Collection<int, SupplierCreditNoteAllocation>
     * }
     */
    public function invoiceTrace(SupplierInvoice $invoice): array
    {
        $invoice->loadMissing(['supplier', 'payments.supplierPayment', 'creditNoteAllocations.creditNote']);

        $paid = $this->invoicePaid($invoice);
        $credits = $this->invoiceCreditsApplied($invoice);
        $remaining = $this->invoiceRemaining($invoice);
        $availableCredits = $this->availableCreditsTotal($invoice->supplier);
        $availableAdvances = $this->availableAdvancesTotal($invoice->supplier);

        return [
            'invoice' => $invoice,
            'total' => $this->money($invoice->total),
            'paid' => $paid,
            'credits_applied' => $credits,
            'remaining' => $remaining,
            'available_credits' => $availableCredits,
            'available_advances' => $availableAdvances,
            'net_to_pay' => max(0, $this->money($remaining - $availableCredits - $availableAdvances)),
            'payments' => $invoice->payments,
            'credit_allocations' => $invoice->creditNoteAllocations,
        ];
    }

    /**
     * @param  array{
     *   payment_date: string,
     *   amount?: float|int|string,
     *   payment_method: string,
     *   payment_reference?: ?string,
     *   notes?: ?string,
     *   payment_file_path?: ?string,
     *   invoice_ids?: list<int>,
     *   cash_allocations?: array<int, float|int|string>,
     *   use_credits?: bool,
     *   use_advances?: bool,
     *   source?: string,
     *   user_id?: ?int,
     *   cheque_number?: ?string,
     *   cheque_bank?: ?string,
     *   cheque_date?: ?string,
     *   cheque_due_date?: ?string,
     *   cheque_beneficiary?: ?string,
     *   cheque_status?: ?string,
     *   tracking_number?: ?string,
     *   payment_import_id?: ?int,
     *   payment_import_line_id?: ?int,
     *   payment_import_row_id?: ?int,
     *   dedupe_key?: ?string,
     * }  $data
     */
    public function recordSettlement(Supplier $supplier, array $data): SupplierPayment
    {
        $cashAmount = $this->money($data['amount'] ?? 0);
        $useCredits = (bool) ($data['use_credits'] ?? false);
        $useAdvances = (bool) ($data['use_advances'] ?? true);
        $invoiceIds = collect($data['invoice_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($cashAmount < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant du paiement ne peut pas être négatif.',
            ]);
        }

        if ($cashAmount < 0.01 && ! $useCredits && ! $useAdvances && $invoiceIds->isEmpty()) {
            throw ValidationException::withMessages([
                'amount' => 'Saisissez un montant, ou sélectionnez des factures et des avoirs à imputer.',
            ]);
        }

        return DB::transaction(function () use ($supplier, $data, $cashAmount, $useCredits, $useAdvances, $invoiceIds) {
            $invoices = SupplierInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->whereIn('id', $invoiceIds->all())
                ->orderByRaw('due_date is null')
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($invoiceIds->isNotEmpty() && $invoices->count() !== $invoiceIds->count()) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Une ou plusieurs factures n’appartiennent pas à ce fournisseur.',
                ]);
            }

            $remainings = [];
            foreach ($invoices as $invoice) {
                $remainings[$invoice->id] = $this->invoiceRemaining($invoice);
            }

            $creditPlan = [];
            if ($useCredits) {
                $credits = SupplierCreditNote::query()
                    ->where('supplier_id', $supplier->id)
                    ->orderBy('credit_note_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($credits as $credit) {
                    $available = $this->creditNoteRemaining($credit);
                    foreach ($invoices as $invoice) {
                        if ($available <= 0.009) {
                            break;
                        }
                        $need = $remainings[$invoice->id] ?? 0;
                        if ($need <= 0.009) {
                            continue;
                        }
                        $apply = min($available, $need);
                        $creditPlan[] = ['credit' => $credit, 'invoice' => $invoice, 'amount' => $apply];
                        $remainings[$invoice->id] = $this->money($need - $apply);
                        $available = $this->money($available - $apply);
                    }
                }
            }

            $advancePlan = [];
            if ($useAdvances) {
                $priorPayments = SupplierPayment::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('unallocated_amount', '>', 0)
                    ->orderBy('payment_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($priorPayments as $prior) {
                    $available = $this->money($prior->unallocated_amount);
                    foreach ($invoices as $invoice) {
                        if ($available <= 0.009) {
                            break;
                        }
                        $need = $remainings[$invoice->id] ?? 0;
                        if ($need <= 0.009) {
                            continue;
                        }
                        $apply = min($available, $need);
                        $advancePlan[] = ['payment' => $prior, 'invoice' => $invoice, 'amount' => $apply];
                        $remainings[$invoice->id] = $this->money($need - $apply);
                        $available = $this->money($available - $apply);
                    }
                    $prior->unallocated_amount = $available;
                    $prior->save();
                }
            }

            $explicit = collect($data['cash_allocations'] ?? []);
            $cashPlan = [];
            $cashLeft = $cashAmount;

            if ($explicit->isNotEmpty()) {
                foreach ($invoices as $invoice) {
                    if ($cashLeft <= 0.009) {
                        break;
                    }
                    $requested = $this->money($explicit->get($invoice->id, $explicit->get((string) $invoice->id, 0)));
                    if ($requested <= 0.009) {
                        continue;
                    }
                    $need = $remainings[$invoice->id] ?? 0;
                    $apply = min($requested, $need, $cashLeft);
                    if ($apply <= 0.009) {
                        continue;
                    }
                    $cashPlan[] = ['invoice' => $invoice, 'amount' => $apply];
                    $remainings[$invoice->id] = $this->money($need - $apply);
                    $cashLeft = $this->money($cashLeft - $apply);
                }
            } else {
                foreach ($invoices as $invoice) {
                    if ($cashLeft <= 0.009) {
                        break;
                    }
                    $need = $remainings[$invoice->id] ?? 0;
                    if ($need <= 0.009) {
                        continue;
                    }
                    $apply = min($cashLeft, $need);
                    $cashPlan[] = ['invoice' => $invoice, 'amount' => $apply];
                    $remainings[$invoice->id] = $this->money($need - $apply);
                    $cashLeft = $this->money($cashLeft - $apply);
                }
            }

            $unallocated = $cashLeft;

            if ($cashAmount < 0.01 && $creditPlan === [] && $advancePlan === []) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Aucun avoir, avance ou paiement à affecter sur les factures sélectionnées.',
                ]);
            }

            $invoiceStates = $this->invoiceStatesBeforeApply($invoices);

            $header = SupplierPayment::query()->create([
                'supplier_id' => $supplier->id,
                'payment_number' => $this->nextPaymentNumber($data['payment_date']),
                'payment_date' => $data['payment_date'],
                'amount' => $cashAmount,
                'unallocated_amount' => $unallocated,
                'status' => SupplierPayment::STATUS_VALIDATED,
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? ($data['cheque_number'] ?? null),
                'cheque_number' => $data['cheque_number'] ?? null,
                'cheque_bank' => $data['cheque_bank'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'cheque_due_date' => $data['cheque_due_date'] ?? null,
                'cheque_beneficiary' => $data['cheque_beneficiary'] ?? $supplier->name,
                'cheque_status' => $data['cheque_status'] ?? null,
                'payment_file_path' => $data['payment_file_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? SupplierInvoicePayment::SOURCE_MANUAL,
                'tracking_number' => $data['tracking_number'] ?? null,
                'user_id' => $data['user_id'] ?? Auth::id(),
                'payment_import_id' => $data['payment_import_id'] ?? null,
                'payment_import_row_id' => $data['payment_import_line_id'] ?? $data['payment_import_row_id'] ?? null,
                'dedupe_key' => $data['dedupe_key'] ?? null,
            ]);

            foreach ($creditPlan as $row) {
                SupplierCreditNoteAllocation::query()->create([
                    'supplier_credit_note_id' => $row['credit']->id,
                    'supplier_invoice_id' => $row['invoice']->id,
                    'supplier_payment_id' => $header->id,
                    'amount' => $row['amount'],
                ]);
            }

            foreach ($advancePlan as $row) {
                /** @var SupplierPayment $prior */
                $prior = $row['payment'];
                SupplierPaymentAllocation::query()->create([
                    'supplier_payment_id' => $header->id,
                    'supplier_invoice_id' => $row['invoice']->id,
                    'source_payment_id' => $prior->id,
                    'amount' => $row['amount'],
                    'is_cash' => false,
                ]);

                $this->createInvoicePaymentLine($row['invoice'], $header, $row['amount'], $data, false);
            }

            foreach ($cashPlan as $row) {
                SupplierPaymentAllocation::query()->create([
                    'supplier_payment_id' => $header->id,
                    'supplier_invoice_id' => $row['invoice']->id,
                    'amount' => $row['amount'],
                    'is_cash' => true,
                ]);

                $this->createInvoicePaymentLine($row['invoice'], $header, $row['amount'], $data, true);
            }

            $this->storeAllocationSnapshot($header, $invoices, $invoiceStates, $creditPlan, $advancePlan, $cashPlan, $unallocated);
            $this->audit($header, 'created', null, null, $this->money($cashAmount), null);

            return $header->fresh(['allocations', 'invoicePayments', 'creditNoteAllocations']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettlement(SupplierPayment $payment, array $data): SupplierPayment
    {
        if ($payment->isCancelled()) {
            throw ValidationException::withMessages([
                'payment' => 'Un règlement annulé ne peut pas être modifié.',
            ]);
        }

        $metaKeys = ['payment_date', 'payment_method', 'payment_reference', 'notes', 'cheque_number', 'cheque_bank', 'cheque_date', 'cheque_due_date', 'cheque_beneficiary', 'cheque_status'];
        $amountChanging = array_key_exists('amount', $data) && abs($this->money($data['amount']) - $this->money($payment->amount)) > 0.009;
        $allocationChanging = (bool) ($data['reallocate'] ?? false);

        return DB::transaction(function () use ($payment, $data, $metaKeys, $amountChanging, $allocationChanging) {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $old = $payment->only(array_merge($metaKeys, ['amount']));

            if ($amountChanging || $allocationChanging) {
                $this->reverseLiveEffects($payment);
                $reapplied = $this->recordSettlement($payment->supplier, array_merge($data, [
                    'source' => $payment->source,
                    'user_id' => $payment->user_id,
                    'tracking_number' => $payment->tracking_number,
                    'payment_import_id' => $payment->payment_import_id,
                    'payment_import_row_id' => $payment->payment_import_row_id,
                ]));

                $this->audit($payment, 'replaced', 'montant/affectation', (string) $old['amount'], (string) $reapplied->amount, $data['reason'] ?? null);
                $this->moveReappliedOntoOriginal($payment, $reapplied, $payment->payment_number);

                return $payment->fresh(['allocations.invoice', 'invoicePayments', 'creditNoteAllocations', 'user']);
            }

            $payload = [];
            foreach ($metaKeys as $key) {
                if (array_key_exists($key, $data)) {
                    $payload[$key] = $data[$key];
                }
            }
            $payment->fill($payload);
            $payment->save();
            $payment->invoicePayments()->update([
                'payment_date' => $payment->payment_date,
                'payment_method' => $payment->payment_method,
                'payment_reference' => $payment->payment_reference,
                'notes' => $payment->notes,
                'cheque_number' => $payment->cheque_number,
                'cheque_bank' => $payment->cheque_bank,
                'cheque_date' => $payment->cheque_date,
                'cheque_due_date' => $payment->cheque_due_date,
                'cheque_beneficiary' => $payment->cheque_beneficiary,
                'cheque_status' => $payment->cheque_status,
            ]);

            foreach ($payload as $key => $value) {
                $previous = $old[$key] ?? null;
                if ((string) $previous !== (string) $value) {
                    $this->audit($payment, 'updated', $key, $previous, $value, $data['reason'] ?? null);
                }
            }

            return $payment->fresh(['allocations.invoice', 'invoicePayments', 'creditNoteAllocations', 'user']);
        });
    }

    public function cancelPayment(SupplierPayment $payment, string $reason, ?int $userId = null): SupplierPayment
    {
        if ($payment->isCancelled()) {
            throw ValidationException::withMessages([
                'payment' => 'Ce règlement est déjà annulé.',
            ]);
        }

        return DB::transaction(function () use ($payment, $reason, $userId) {
            $payment = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! $payment->allocation_snapshot) {
                $this->storeLiveSnapshot($payment);
            }

            $this->reverseLiveEffects($payment);

            $payment->status = SupplierPayment::STATUS_CANCELLED;
            $payment->cancelled_at = now();
            $payment->cancelled_by = $userId ?? Auth::id();
            $payment->cancellation_reason = $reason;
            $payment->unallocated_amount = 0;
            $payment->save();

            $this->audit($payment, 'cancelled', 'statut', 'Validé', 'Annulé', $reason);

            return $payment->fresh();
        });
    }

    public function destroyPayment(SupplierPayment $payment): void
    {
        $this->cancelPayment($payment, 'Annulation du règlement', Auth::id());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentHistory(Supplier $supplier): array
    {
        return $supplier->accountPayments()
            ->with([
                'user',
                'allocations.invoice',
                'creditNoteAllocations.invoice',
                'invoicePayments.managedDocuments.currentVersion',
                'managedDocuments.currentVersion',
            ])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SupplierPayment $payment) => $this->summarizeHistoryRow($payment))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeHistoryRow(SupplierPayment $payment): array
    {
        return $this->historyRow($payment);
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentDossier(SupplierPayment $payment): array
    {
        $payment->loadMissing([
            'supplier',
            'user',
            'cancelledByUser',
            'audits.user',
            'allocations.invoice',
            'creditNoteAllocations.creditNote',
            'creditNoteAllocations.invoice',
            'invoicePayments.managedDocuments.currentVersion',
            'managedDocuments.currentVersion',
        ]);

        $snapshot = $payment->allocation_snapshot ?: $this->liveSnapshotArray($payment);
        $justificatifs = $this->justificatifsFor($payment);

        return [
            'payment' => $payment,
            'snapshot' => $snapshot,
            'justificatifs' => $justificatifs,
            'audits' => $payment->audits,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openInvoicesForEdit(Supplier $supplier, SupplierPayment $payment): array
    {
        $payment->loadMissing(['allocations', 'creditNoteAllocations']);
        $appliedCash = $payment->allocations->where('is_cash', true)->groupBy('supplier_invoice_id')->map->sum('amount');
        $appliedAdvance = $payment->allocations->where('is_cash', false)->groupBy('supplier_invoice_id')->map->sum('amount');
        $appliedCredits = $payment->creditNoteAllocations->groupBy('supplier_invoice_id')->map->sum('amount');

        return $supplier->invoices()
            ->with(['payments', 'creditNoteAllocations'])
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->get()
            ->map(function (SupplierInvoice $invoice) use ($appliedCash, $appliedAdvance, $appliedCredits) {
                $extra = $this->money(
                    (float) ($appliedCash[$invoice->id] ?? 0)
                    + (float) ($appliedAdvance[$invoice->id] ?? 0)
                    + (float) ($appliedCredits[$invoice->id] ?? 0)
                );
                $remaining = $this->money($this->invoiceRemaining($invoice) + $extra);

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'date' => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                    'total' => $this->money($invoice->total),
                    'paid' => $this->money($this->invoicePaid($invoice) - (float) ($appliedCash[$invoice->id] ?? 0) - (float) ($appliedAdvance[$invoice->id] ?? 0)),
                    'credits_applied' => $this->money($this->invoiceCreditsApplied($invoice) - (float) ($appliedCredits[$invoice->id] ?? 0)),
                    'remaining' => $remaining,
                    'selected' => $extra > 0.009,
                ];
            })
            ->filter(fn (array $row) => $row['remaining'] > 0.009)
            ->values()
            ->all();
    }

    private function activePaymentsQuery(Supplier $supplier)
    {
        return $supplier->accountPayments()->where('status', '!=', SupplierPayment::STATUS_CANCELLED);
    }

    private function nextPaymentNumber(string $date): string
    {
        $year = date('Y', strtotime($date)) ?: date('Y');
        $prefix = 'REG-'.$year.'-';
        $last = SupplierPayment::query()
            ->where('payment_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('payment_number')
            ->first();

        $next = 1;
        if ($last && preg_match('/REG-\d{4}-(\d+)/', (string) $last->payment_number, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Collection<int, SupplierInvoice>  $invoices
     * @return array<int, array{paid: float, credits: float, remaining: float, total: float, number: ?string, date: ?string}>
     */
    private function invoiceStatesBeforeApply(Collection $invoices): array
    {
        $states = [];
        foreach ($invoices as $invoice) {
            $states[$invoice->id] = [
                'paid' => $this->invoicePaid($invoice),
                'credits' => $this->invoiceCreditsApplied($invoice),
                'remaining' => $this->invoiceRemaining($invoice),
                'total' => $this->money($invoice->total),
                'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date?->format('Y-m-d'),
            ];
        }

        return $states;
    }

    /**
     * @param  Collection<int, SupplierInvoice>  $invoices
     * @param  array<int, array<string, mixed>>  $states
     * @param  list<array{credit: SupplierCreditNote, invoice: SupplierInvoice, amount: float}>  $creditPlan
     * @param  list<array{payment: SupplierPayment, invoice: SupplierInvoice, amount: float}>  $advancePlan
     * @param  list<array{invoice: SupplierInvoice, amount: float}>  $cashPlan
     */
    private function storeAllocationSnapshot(
        SupplierPayment $header,
        Collection $invoices,
        array $states,
        array $creditPlan,
        array $advancePlan,
        array $cashPlan,
        float $unallocated
    ): void {
        $header->allocation_snapshot = $this->buildSnapshot($invoices, $states, $creditPlan, $advancePlan, $cashPlan, $unallocated);
        $header->save();
    }

    /**
     * @param  Collection<int, SupplierInvoice>  $invoices
     * @param  array<int, array<string, mixed>>  $states
     * @return array<string, mixed>
     */
    private function buildSnapshot(
        Collection $invoices,
        array $states,
        array $creditPlan,
        array $advancePlan,
        array $cashPlan,
        float $unallocated
    ): array {
        $rows = [];
        foreach ($invoices as $invoice) {
            $credit = $this->money(collect($creditPlan)->filter(fn ($row) => $row['invoice']->id === $invoice->id)->sum('amount'));
            $advance = $this->money(collect($advancePlan)->filter(fn ($row) => $row['invoice']->id === $invoice->id)->sum('amount'));
            $cash = $this->money(collect($cashPlan)->filter(fn ($row) => $row['invoice']->id === $invoice->id)->sum('amount'));
            if ($credit <= 0.009 && $advance <= 0.009 && $cash <= 0.009) {
                continue;
            }
            $state = $states[$invoice->id] ?? [
                'paid' => 0, 'credits' => 0, 'remaining' => 0, 'total' => $this->money($invoice->total),
                'number' => $invoice->invoice_number, 'date' => $invoice->invoice_date?->format('Y-m-d'),
            ];
            $rows[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $state['number'],
                'invoice_date' => $state['date'],
                'invoice_amount' => $state['total'],
                'paid_before' => $state['paid'],
                'credit_applied' => $credit,
                'advance_applied' => $advance,
                'cash_applied' => $cash,
                'remaining_after' => $this->money($state['remaining'] - $credit - $advance - $cash),
            ];
        }

        $creditsUsed = collect($creditPlan)->map(fn ($row) => [
            'number' => $row['credit']->credit_note_number,
            'invoice' => $row['invoice']->invoice_number,
            'amount' => $row['amount'],
        ])->values()->all();

        $advancesUsed = collect($advancePlan)->map(fn ($row) => [
            'source' => $row['payment']->payment_number ?: ('#'.$row['payment']->id),
            'invoice' => $row['invoice']->invoice_number,
            'amount' => $row['amount'],
        ])->values()->all();

        return [
            'invoices' => $rows,
            'credits' => $creditsUsed,
            'advances' => $advancesUsed,
            'unallocated' => $unallocated,
        ];
    }

    private function storeLiveSnapshot(SupplierPayment $payment): void
    {
        $payment->allocation_snapshot = $this->liveSnapshotArray($payment);
        $payment->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function liveSnapshotArray(SupplierPayment $payment): array
    {
        $payment->loadMissing(['allocations.invoice', 'creditNoteAllocations.creditNote', 'creditNoteAllocations.invoice']);
        $invoiceIds = $payment->allocations->pluck('supplier_invoice_id')
            ->merge($payment->creditNoteAllocations->pluck('supplier_invoice_id'))
            ->unique()
            ->filter();
        $invoices = SupplierInvoice::query()->whereIn('id', $invoiceIds)->get()->keyBy('id');
        $rows = [];
        foreach ($invoiceIds as $id) {
            $invoice = $invoices->get($id);
            if (! $invoice) {
                continue;
            }
            $cash = $this->money($payment->allocations->where('supplier_invoice_id', $id)->where('is_cash', true)->sum('amount'));
            $advance = $this->money($payment->allocations->where('supplier_invoice_id', $id)->where('is_cash', false)->sum('amount'));
            $credit = $this->money($payment->creditNoteAllocations->where('supplier_invoice_id', $id)->sum('amount'));
            $rows[] = [
                'invoice_id' => $id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'invoice_amount' => $this->money($invoice->total),
                'paid_before' => null,
                'credit_applied' => $credit,
                'advance_applied' => $advance,
                'cash_applied' => $cash,
                'remaining_after' => $this->invoiceRemaining($invoice),
            ];
        }

        return [
            'invoices' => $rows,
            'credits' => $payment->creditNoteAllocations->map(fn ($row) => [
                'number' => $row->creditNote?->credit_note_number,
                'invoice' => $row->invoice?->invoice_number,
                'amount' => $this->money($row->amount),
            ])->all(),
            'advances' => $payment->allocations->where('is_cash', false)->map(fn ($row) => [
                'source' => $row->source_payment_id,
                'invoice' => $row->invoice?->invoice_number,
                'amount' => $this->money($row->amount),
            ])->all(),
            'unallocated' => $this->money($payment->unallocated_amount),
        ];
    }

    private function reverseLiveEffects(SupplierPayment $payment): void
    {
        $payment->load(['invoicePayments', 'creditNoteAllocations', 'allocations']);

        $downstream = SupplierPaymentAllocation::query()
            ->where('source_payment_id', $payment->id)
            ->where('supplier_payment_id', '!=', $payment->id)
            ->lockForUpdate()
            ->get();

        foreach ($downstream as $allocation) {
            SupplierInvoicePayment::query()
                ->where('supplier_payment_id', $allocation->supplier_payment_id)
                ->where('supplier_invoice_id', $allocation->supplier_invoice_id)
                ->where('is_cash_movement', false)
                ->where('amount', $allocation->amount)
                ->limit(1)
                ->get()
                ->each(fn (SupplierInvoicePayment $line) => $line->delete());
            $allocation->delete();
        }

        foreach ($payment->allocations as $allocation) {
            if ($allocation->source_payment_id) {
                $source = SupplierPayment::query()->lockForUpdate()->find($allocation->source_payment_id);
                if ($source && $source->isValidated()) {
                    $source->unallocated_amount = $this->money((float) $source->unallocated_amount + (float) $allocation->amount);
                    $source->save();
                }
            }
        }

        $payment->creditNoteAllocations()->delete();
        $payment->invoicePayments()->each(fn (SupplierInvoicePayment $line) => $line->delete());
        $payment->allocations()->delete();
        $payment->unallocated_amount = 0;
        $payment->save();
    }

    private function moveReappliedOntoOriginal(SupplierPayment $original, SupplierPayment $reapplied, ?string $keepNumber): void
    {
        SupplierPaymentAllocation::query()->where('supplier_payment_id', $reapplied->id)->update(['supplier_payment_id' => $original->id]);
        SupplierInvoicePayment::query()->where('supplier_payment_id', $reapplied->id)->update(['supplier_payment_id' => $original->id]);
        SupplierCreditNoteAllocation::query()->where('supplier_payment_id', $reapplied->id)->update(['supplier_payment_id' => $original->id]);
        SupplierPaymentAudit::query()->where('supplier_payment_id', $reapplied->id)->update(['supplier_payment_id' => $original->id]);

        $original->fill([
            'payment_date' => $reapplied->payment_date,
            'amount' => $reapplied->amount,
            'unallocated_amount' => $reapplied->unallocated_amount,
            'payment_method' => $reapplied->payment_method,
            'payment_reference' => $reapplied->payment_reference,
            'cheque_number' => $reapplied->cheque_number,
            'cheque_bank' => $reapplied->cheque_bank,
            'cheque_date' => $reapplied->cheque_date,
            'cheque_due_date' => $reapplied->cheque_due_date,
            'cheque_beneficiary' => $reapplied->cheque_beneficiary,
            'cheque_status' => $reapplied->cheque_status,
            'notes' => $reapplied->notes,
            'allocation_snapshot' => $reapplied->allocation_snapshot,
            'status' => SupplierPayment::STATUS_VALIDATED,
            'payment_number' => $keepNumber,
        ]);
        $original->save();
        $reapplied->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function historyRow(SupplierPayment $payment): array
    {
        $invoices = $payment->allocations->map(fn ($a) => $a->invoice?->invoice_number)
            ->merge($payment->creditNoteAllocations->map(fn ($a) => $a->invoice?->invoice_number))
            ->filter()
            ->unique()
            ->values();
        if ($invoices->isEmpty() && is_array($payment->allocation_snapshot['invoices'] ?? null)) {
            $invoices = collect($payment->allocation_snapshot['invoices'])->pluck('invoice_number')->filter()->unique()->values();
        }

        return [
            'payment' => $payment,
            'invoices' => $invoices->implode(', ') ?: '—',
            'has_justificatif' => $this->justificatifsFor($payment) !== [],
            'status' => $payment->statusLabel(),
            'cancelled' => $payment->isCancelled(),
        ];
    }

    /**
     * @return list<\App\Models\ManagedDocument>
     */
    private function justificatifsFor(SupplierPayment $payment): array
    {
        $docs = $payment->managedDocuments->where('is_active', true)->values();
        if ($docs->isEmpty()) {
            foreach ($payment->invoicePayments as $line) {
                $docs = $docs->merge($line->managedDocuments->where('is_active', true));
            }
        }

        return $docs->unique('id')->values()->all();
    }

    private function audit(
        SupplierPayment $payment,
        string $action,
        ?string $field,
        mixed $old,
        mixed $new,
        ?string $reason
    ): void {
        SupplierPaymentAudit::query()->create([
            'supplier_payment_id' => $payment->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'field' => $field,
            'old_value' => $old === null ? null : (string) $old,
            'new_value' => $new === null ? null : (string) $new,
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createInvoicePaymentLine(
        SupplierInvoice $invoice,
        SupplierPayment $header,
        float $amount,
        array $data,
        bool $isCashMovement
    ): SupplierInvoicePayment {
        return $invoice->payments()->create([
            'supplier_id' => $header->supplier_id,
            'supplier_payment_id' => $header->id,
            'payment_date' => $header->payment_date,
            'amount' => $amount,
            'payment_method' => $header->payment_method,
            'payment_reference' => $header->payment_reference,
            'cheque_number' => $header->cheque_number,
            'cheque_bank' => $header->cheque_bank,
            'cheque_date' => $header->cheque_date,
            'cheque_due_date' => $header->cheque_due_date,
            'cheque_beneficiary' => $header->cheque_beneficiary,
            'cheque_status' => $header->cheque_status,
            'payment_file_path' => $header->payment_file_path,
            'notes' => $header->notes,
            'source' => $header->source,
            'tracking_number' => $header->tracking_number,
            'user_id' => $header->user_id,
            'payment_import_id' => $header->payment_import_id,
            'payment_import_row_id' => $header->payment_import_row_id,
            'dedupe_key' => $isCashMovement ? ($data['dedupe_key'] ?? null) : null,
            'allow_overpayment' => true,
            'is_cash_movement' => $isCashMovement,
        ]);
    }
}
