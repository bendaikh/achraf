<?php

namespace App\Services\Access;

use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    /**
     * Evaluate / create commission for an invoice based on active rules.
     */
    public function syncForInvoice(Invoice $invoice): ?Commission
    {
        if (! $invoice->collaborator_id) {
            return null;
        }

        $rule = $this->resolveRule();
        if (! $rule || ! $rule->is_active) {
            return null;
        }

        $base = $this->baseAmount($invoice, $rule);
        $amount = $this->computeAmount($base, $rule);
        $status = $this->statusForInvoice($invoice, $rule);

        $existing = Commission::query()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->whereNull('parent_id')
            ->first();

        $payload = [
            'collaborator_id' => $invoice->collaborator_id,
            'commission_rule_id' => $rule->id,
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'document_ref' => $invoice->invoice_number,
            'base_amount' => $base,
            'rate' => $rule->rate,
            'amount' => $amount,
            'status' => $existing && in_array($existing->status, [
                Commission::STATUS_VALIDEE,
                Commission::STATUS_PAYEE,
            ], true) ? $existing->status : $status,
            'earned_at' => $status !== Commission::STATUS_A_VENIR ? now()->toDateString() : null,
        ];

        if ($existing) {
            // Don't overwrite paid/validated amounts blindly — create regularisation if needed
            if (in_array($existing->status, [Commission::STATUS_VALIDEE, Commission::STATUS_PAYEE], true)
                && abs((float) $existing->amount - (float) $amount) > 0.009
            ) {
                $this->createRegularisation($existing, (float) $amount - (float) $existing->amount, 'Recalcul suite modification facture');

                return $existing->fresh();
            }

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

        $rule = $parent->rule ?? $this->resolveRule();
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
        return CommissionRule::query()->firstOrCreate(
            ['name' => 'Commission standard 3% CA HT'],
            [
                'type' => 'percent_ca',
                'base' => 'ca_ht',
                'rate' => 3,
                'fixed_amount' => null,
                'trigger' => 'delivered_paid',
                'is_active' => true,
                'notes' => 'Règle par défaut — modifiable par l\'Admin',
            ]
        );
    }

    private function resolveRule(): ?CommissionRule
    {
        return CommissionRule::query()->where('is_active', true)->orderBy('id')->first()
            ?? $this->ensureDefaultRule();
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
