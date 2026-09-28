<?php

namespace App\Services\Access;

use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    /**
     * Evaluate / create commission for an invoice based on active rules.
     * One rule only: specific commercial → default → none. Never stack rules.
     */
    public function syncForInvoice(Invoice $invoice): ?Commission
    {
        if (! $invoice->collaborator_id) {
            return null;
        }

        $existing = Commission::query()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->whereNull('parent_id')
            ->first();

        // Acquired+ commissions keep their snapshot — rule edits must not rewrite them.
        if ($existing && in_array($existing->status, [
            Commission::STATUS_ACQUISE,
            Commission::STATUS_VALIDEE,
            Commission::STATUS_PAYEE,
            Commission::STATUS_ANNULEE,
            Commission::STATUS_REGULARISEE,
        ], true)) {
            return $existing;
        }

        $rule = $this->resolveRuleForCollaborator((int) $invoice->collaborator_id);
        if (! $rule) {
            return $existing;
        }

        $base = $this->baseAmount($invoice, $rule);
        $amount = $this->computeAmount($base, $rule);
        $status = $this->statusForInvoice($invoice, $rule);

        $payload = [
            'collaborator_id' => $invoice->collaborator_id,
            'commission_rule_id' => $rule->id,
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'document_ref' => $invoice->invoice_number,
            'base_amount' => $base,
            'rate' => $rule->rate,
            'amount' => $amount,
            'status' => $status,
            'earned_at' => $status !== Commission::STATUS_A_VENIR ? now()->toDateString() : null,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        $commission = Commission::query()->create($payload);

        ActivityLogger::log(
            'creation',
            'Commission créée '.$commission->document_ref.' = '.$commission->amount,
            $commission,
        );

        return $commission;
    }

    public function applyCreditNote(CreditNote $creditNote): void
    {
        $invoice = $creditNote->invoice;
        if (! $invoice) {
            return;
        }

        $parent = Commission::query()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->whereNull('parent_id')
            ->first();

        if (! $parent) {
            return;
        }

        $rule = $parent->rule
            ?? $this->resolveRuleForCollaborator((int) $parent->collaborator_id);
        if (! $rule) {
            return;
        }

        $creditBase = (float) $creditNote->total;
        $delta = -1 * $this->computeAmount($creditBase, $rule);

        if (abs($delta) < 0.01) {
            return;
        }

        $this->createRegularisation(
            $parent,
            $delta,
            'Régularisation avoir '.($creditNote->credit_note_number ?? $creditNote->id),
        );
    }

    public function createRegularisation(Commission $parent, float $deltaAmount, string $note): Commission
    {
        $row = Commission::query()->create([
            'collaborator_id' => $parent->collaborator_id,
            'commission_rule_id' => $parent->commission_rule_id,
            'source_type' => $parent->source_type,
            'source_id' => $parent->source_id,
            'document_ref' => $parent->document_ref,
            'base_amount' => 0,
            'rate' => $parent->rate,
            'amount' => round($deltaAmount, 2),
            'status' => Commission::STATUS_REGULARISEE,
            'earned_at' => now()->toDateString(),
            'notes' => $note,
            'parent_id' => $parent->id,
        ]);

        ActivityLogger::log(
            'modification_commission',
            $note.' ('.$deltaAmount.')',
            $row,
            ['parent_amount' => $parent->amount],
            ['delta' => $deltaAmount],
            $parent->document_ref,
        );

        return $row;
    }

    /**
     * @param  list<int>  $commissionIds
     * @param  array{amount?: mixed, date?: mixed, payment_method?: mixed, payment_reference?: mixed, notes?: mixed}  $payment
     */
    public function markPaid(array $commissionIds, array $payment): int
    {
        return DB::transaction(function () use ($commissionIds, $payment) {
            $count = 0;
            $rows = Commission::query()
                ->whereIn('id', $commissionIds)
                ->whereIn('status', [Commission::STATUS_ACQUISE, Commission::STATUS_VALIDEE, Commission::STATUS_REGULARISEE])
                ->get();

            foreach ($rows as $row) {
                $row->update([
                    'status' => Commission::STATUS_PAYEE,
                    'paid_at' => $payment['date'] ?? now()->toDateString(),
                    'payment_method' => $payment['payment_method'] ?? null,
                    'payment_reference' => $payment['payment_reference'] ?? null,
                    'notes' => trim(($row->notes ? $row->notes."\n" : '').($payment['notes'] ?? '')),
                    'amount' => isset($payment['amount']) && count($commissionIds) === 1
                        ? (float) $payment['amount']
                        : $row->amount,
                ]);

                ActivityLogger::log(
                    'paiement',
                    'Commission payée '.$row->document_ref,
                    $row,
                    null,
                    $payment,
                    $row->document_ref,
                );
                $count++;
            }

            return $count;
        });
    }

    public function validate(array $commissionIds): int
    {
        $count = 0;
        Commission::query()
            ->whereIn('id', $commissionIds)
            ->where('status', Commission::STATUS_ACQUISE)
            ->each(function (Commission $row) use (&$count) {
                $row->update([
                    'status' => Commission::STATUS_VALIDEE,
                    'validated_at' => now()->toDateString(),
                ]);
                ActivityLogger::log('validation', 'Commission validée '.$row->document_ref, $row);
                $count++;
            });

        return $count;
    }

    public function ensureDefaultRule(): CommissionRule
    {
        $existingDefault = CommissionRule::query()
            ->where('is_default', true)
            ->whereNull('archived_at')
            ->orderBy('id')
            ->first();

        if ($existingDefault) {
            return $existingDefault;
        }

        $named = CommissionRule::query()
            ->where('name', 'Commission standard 3% CA HT')
            ->whereNull('archived_at')
            ->first();

        if ($named) {
            $named->update([
                'is_default' => true,
                'is_active' => true,
                'collaborator_id' => null,
            ]);

            return $named->fresh();
        }

        return CommissionRule::query()->create([
            'name' => 'Commission standard 3% CA HT',
            'collaborator_id' => null,
            'type' => 'percent_ca',
            'base' => 'ca_ht',
            'rate' => 3,
            'fixed_amount' => null,
            'trigger' => 'delivered_paid',
            'is_active' => true,
            'is_default' => true,
            'notes' => 'Règle par défaut — modifiable par l\'Admin',
        ]);
    }

    /**
     * Specific commercial rule → default rule → none. Never stack.
     */
    public function resolveRuleForCollaborator(int $collaboratorId): ?CommissionRule
    {
        $specific = CommissionRule::query()
            ->active()
            ->where('collaborator_id', $collaboratorId)
            ->orderByDesc('id')
            ->first();

        if ($specific) {
            return $specific;
        }

        return CommissionRule::query()
            ->active()
            ->where('is_default', true)
            ->whereNull('collaborator_id')
            ->orderBy('id')
            ->first();
    }

    private function baseAmount(Invoice $invoice, CommissionRule $rule): float
    {
        return match ($rule->base) {
            'ca_ttc' => (float) $invoice->total,
            'collected' => (float) $invoice->payments()->sum('amount'),
            'margin' => (float) $invoice->total, // margin engine later — fallback CA
            'fixed' => 0,
            default => (float) ($invoice->subtotal ?? $invoice->total),
        };
    }

    private function computeAmount(float $base, CommissionRule $rule): float
    {
        return match ($rule->type) {
            'fixed' => (float) ($rule->fixed_amount ?? 0),
            'percent_margin', 'percent_ca' => round($base * ((float) $rule->rate) / 100, 2),
            default => round($base * ((float) $rule->rate) / 100, 2),
        };
    }

    private function statusForInvoice(Invoice $invoice, CommissionRule $rule): string
    {
        $paid = in_array($invoice->payment_status, [
            Invoice::PAYMENT_PAID ?? 'paid',
            'paid',
            'payé',
            'Payé',
        ], true) || (float) $invoice->payments()->sum('amount') >= (float) $invoice->total - 0.01;

        $delivered = true; // invoice implies delivery in this chain; refine with BL later

        return match ($rule->trigger) {
            'invoice_validated' => Commission::STATUS_ACQUISE,
            'delivered' => $delivered ? Commission::STATUS_ACQUISE : Commission::STATUS_A_VENIR,
            'paid' => $paid ? Commission::STATUS_ACQUISE : Commission::STATUS_A_VENIR,
            default => ($delivered && $paid) ? Commission::STATUS_ACQUISE : Commission::STATUS_A_VENIR,
        };
    }
}
