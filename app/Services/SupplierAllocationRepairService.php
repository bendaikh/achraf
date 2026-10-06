<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierInvoicePayment;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Support\PaymentRealization;
use Illuminate\Support\Facades\DB;

class SupplierAllocationRepairService
{
    public function __construct(
        protected SupplierAccountService $accounts
    ) {}

    /**
     * @return array{
     *   supplier_id: int,
     *   supplier: string,
     *   total_invoices: float,
     *   total_credits: float,
     *   total_payments: float,
     *   total_advances: float,
     *   unallocated_amount: float,
     *   available_credits: float,
     *   open_invoices: float,
     *   balance: float,
     *   open_vs_balance_gap: float,
     *   orphan_unallocated: float,
     *   missing_invoice_lines: int,
     *   needs_repair: bool
     * }
     */
    public function auditSupplier(Supplier $supplier): array
    {
        $statement = $this->accounts->statement($supplier);
        $orphan = $this->orphanUnallocatedTotal($supplier);
        $missingLines = $this->missingInvoicePaymentLinesCount($supplier);

        $coveringPools = $this->accounts->money(
            $statement['available_credits'] + $statement['available_advances'] + $orphan
        );
        $gap = $this->accounts->money(
            $statement['open_invoices'] - max(0, $statement['balance']) - $coveringPools
        );
        $openVsBalance = $this->accounts->money(
            $statement['open_invoices'] - max(0, $statement['balance'])
        );
        $availablePools = $this->accounts->money(
            $statement['available_credits'] + $statement['available_advances']
        );

        // Réparer si : avances orphelines, lignes facture manquantes, écart non couvert,
        // ou pools disponibles non encore imputés alors que des factures restent ouvertes.
        $needsRepair = $orphan > 0.009
            || $missingLines > 0
            || (
                $statement['open_invoices'] > 0.009
                && (abs($gap) > 0.009 || ($availablePools > 0.009 && abs($openVsBalance) > 0.009))
            );

        return [
            'supplier_id' => $supplier->id,
            'supplier' => $supplier->name,
            'total_invoices' => $statement['total_invoices'],
            'total_credits' => $statement['total_credits'],
            'total_payments' => $this->accounts->money($statement['total_payments'] + $statement['total_advances']),
            'total_advances' => $statement['total_advances'],
            'unallocated_amount' => $statement['available_advances'],
            'available_credits' => $statement['available_credits'],
            'open_invoices' => $statement['open_invoices'],
            'balance' => $statement['balance'],
            'open_vs_balance_gap' => $this->accounts->money(
                $statement['open_invoices'] - max(0, $statement['balance'])
            ),
            'orphan_unallocated' => $orphan,
            'missing_invoice_lines' => $missingLines,
            'needs_repair' => $needsRepair,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function auditAll(?int $supplierId = null): array
    {
        $query = Supplier::query()->orderBy('name');
        if ($supplierId) {
            $query->where('id', $supplierId);
        }

        return $query->get()
            ->map(fn (Supplier $supplier) => $this->auditSupplier($supplier))
            ->filter(fn (array $row) => $row['needs_repair']
                || abs($row['balance']) > 0.009
                || $row['open_invoices'] > 0.009
                || $row['total_payments'] > 0.009)
            ->values()
            ->all();
    }

    /**
     * Idempotent : restaure les avances orphelines, recrée les lignes facture manquantes,
     * puis impute avoirs/avances non affectés sur les factures ouvertes (plus anciennes d'abord).
     *
     * @return array{
     *   supplier_id: int,
     *   supplier: string,
     *   dry_run: bool,
     *   orphan_restored: float,
     *   invoice_lines_created: int,
     *   credits_applied: float,
     *   advances_applied: float,
     *   before: array<string, mixed>,
     *   after: array<string, mixed>
     * }
     */
    public function repairSupplier(Supplier $supplier, bool $apply = false): array
    {
        $before = $this->auditSupplier($supplier);

        if (! $apply) {
            $projectedOrphan = $before['orphan_unallocated'];
            $projectedMissing = $before['missing_invoice_lines'];
            $pool = $this->accounts->money(
                $before['available_credits'] + $before['unallocated_amount'] + $projectedOrphan
            );
            $applyable = min($pool, $before['open_invoices']);

            return [
                'supplier_id' => $supplier->id,
                'supplier' => $supplier->name,
                'dry_run' => true,
                'orphan_restored' => $projectedOrphan,
                'invoice_lines_created' => $projectedMissing,
                'credits_applied' => min($before['available_credits'], $before['open_invoices']),
                'advances_applied' => max(0, $this->accounts->money($applyable - min($before['available_credits'], $before['open_invoices']))),
                'before' => $before,
                'after' => $before,
            ];
        }

        return DB::transaction(function () use ($supplier, $before) {
            $orphanRestored = $this->restoreOrphanUnallocated($supplier);
            $linesCreated = $this->syncMissingInvoicePaymentLines($supplier);

            $openIds = collect($this->accounts->openInvoicesPayload($supplier))->pluck('id')->all();
            $creditsApplied = 0.0;
            $advancesApplied = 0.0;

            if ($openIds !== []) {
                $creditsBefore = $this->accounts->availableCreditsTotal($supplier);
                $advancesBefore = $this->accounts->availableAdvancesTotal($supplier);

                if ($creditsBefore > 0.009 || $advancesBefore > 0.009) {
                    $this->accounts->recordSettlement($supplier, [
                        'payment_date' => now()->toDateString(),
                        'amount' => 0,
                        'payment_method' => 'Réaffectation',
                        'notes' => 'Réaffectation automatique des avoirs/avances non affectés (repair)',
                        'invoice_ids' => $openIds,
                        'use_credits' => $creditsBefore > 0.009,
                        'use_advances' => $advancesBefore > 0.009,
                        'allow_advance' => false,
                        'source' => 'repair',
                    ]);

                    $creditsApplied = $this->accounts->money(
                        $creditsBefore - $this->accounts->availableCreditsTotal($supplier)
                    );
                    $advancesApplied = $this->accounts->money(
                        $advancesBefore - $this->accounts->availableAdvancesTotal($supplier)
                    );
                }
            }

            $after = $this->auditSupplier($supplier->fresh());

            return [
                'supplier_id' => $supplier->id,
                'supplier' => $supplier->name,
                'dry_run' => false,
                'orphan_restored' => $orphanRestored,
                'invoice_lines_created' => $linesCreated,
                'credits_applied' => $creditsApplied,
                'advances_applied' => $advancesApplied,
                'before' => $before,
                'after' => $after,
            ];
        });
    }

    /**
     * Montant des règlements « affectés » en en-tête mais sans allocation cash réelle.
     */
    public function orphanUnallocatedTotal(Supplier $supplier): float
    {
        $total = 0.0;

        foreach ($this->activePayments($supplier) as $payment) {
            $total = $this->accounts->money($total + $this->orphanForPayment($payment));
        }

        return $total;
    }

    public function missingInvoicePaymentLinesCount(Supplier $supplier): int
    {
        $count = 0;

        foreach ($this->activePayments($supplier) as $payment) {
            $payment->loadMissing(['allocations', 'invoicePayments']);
            foreach ($payment->allocations as $allocation) {
                if (! $this->hasMatchingInvoicePaymentLine($payment, $allocation)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function restoreOrphanUnallocated(Supplier $supplier): float
    {
        $restored = 0.0;

        foreach ($this->activePayments($supplier) as $payment) {
            $orphan = $this->orphanForPayment($payment);
            if ($orphan <= 0.009) {
                continue;
            }

            $payment->unallocated_amount = $this->accounts->money(
                (float) $payment->unallocated_amount + $orphan
            );
            $payment->save();
            $restored = $this->accounts->money($restored + $orphan);
        }

        return $restored;
    }

    private function syncMissingInvoicePaymentLines(Supplier $supplier): int
    {
        $created = 0;

        foreach ($this->activePayments($supplier) as $payment) {
            $payment->loadMissing(['allocations', 'invoicePayments']);

            foreach ($payment->allocations as $allocation) {
                if ($this->hasMatchingInvoicePaymentLine($payment, $allocation)) {
                    continue;
                }

                if (! $allocation->supplier_invoice_id) {
                    continue;
                }

                SupplierInvoicePayment::query()->create([
                    'supplier_id' => $payment->supplier_id,
                    'supplier_payment_id' => $payment->id,
                    'supplier_invoice_id' => $allocation->supplier_invoice_id,
                    'payment_date' => $payment->payment_date,
                    'amount' => $allocation->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_reference' => $payment->payment_reference,
                    'cheque_number' => $payment->cheque_number,
                    'cheque_bank' => $payment->cheque_bank,
                    'cheque_date' => $payment->cheque_date,
                    'cheque_due_date' => $payment->cheque_due_date,
                    'cheque_beneficiary' => $payment->cheque_beneficiary,
                    'cheque_status' => $payment->cheque_status,
                    'notes' => 'Ligne reconstituée (repair affectations)',
                    'source' => $payment->source ?: 'repair',
                    'user_id' => $payment->user_id,
                    'allow_overpayment' => true,
                    'is_cash_movement' => (bool) $allocation->is_cash,
                ]);
                $created++;
            }
        }

        return $created;
    }

    private function orphanForPayment(SupplierPayment $payment): float
    {
        $payment->loadMissing('allocations');
        $cashAllocated = $this->accounts->money(
            (float) $payment->allocations->where('is_cash', true)->sum('amount')
        );
        // Avances déjà imputées depuis ce règlement (via d'autres en-têtes).
        $appliedAsAdvance = $this->accounts->money(
            (float) SupplierPaymentAllocation::query()
                ->where('source_payment_id', $payment->id)
                ->sum('amount')
        );
        $expectedUnallocated = max(0, $this->accounts->money(
            (float) $payment->amount - $cashAllocated - $appliedAsAdvance
        ));
        $current = $this->accounts->money((float) $payment->unallocated_amount);

        return max(0, $this->accounts->money($expectedUnallocated - $current));
    }

    private function hasMatchingInvoicePaymentLine(
        SupplierPayment $payment,
        SupplierPaymentAllocation $allocation
    ): bool {
        return $payment->invoicePayments
            ->where('supplier_invoice_id', $allocation->supplier_invoice_id)
            ->contains(function (SupplierInvoicePayment $line) use ($allocation) {
                return abs((float) $line->amount - (float) $allocation->amount) < 0.009
                    && (bool) $line->is_cash_movement === (bool) $allocation->is_cash;
            });
    }

    /**
     * Règlements effectifs uniquement (hors planifiés) pour audit/réparation du solde réel.
     *
     * @return \Illuminate\Support\Collection<int, SupplierPayment>
     */
    private function activePayments(Supplier $supplier)
    {
        return $supplier->accountPayments()
            ->where('status', '!=', SupplierPayment::STATUS_CANCELLED)
            ->whereDate('payment_date', '<=', now()->toDateString())
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (SupplierPayment $payment) => PaymentRealization::isRealized($payment->payment_date))
            ->values();
    }
}
