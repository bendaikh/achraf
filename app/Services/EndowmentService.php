<?php

namespace App\Services;

use App\Models\BankCard;
use App\Models\Endowment;
use App\Models\EndowmentConsumption;
use App\Models\Expense;
use App\Models\FinancialMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EndowmentService
{
    public function __construct(
        protected FinancialMovementService $movements
    ) {}

    /**
     * Consume endowment only on real payment (not on mere expense registration).
     */
    public function consumeFromExpensePayment(Expense $expense): ?EndowmentConsumption
    {
        if ($expense->payment_status !== Expense::PAYMENT_PAID) {
            return null;
        }

        $endowmentId = $expense->endowment_id;
        $cardId = $expense->bank_card_id;

        if (! $endowmentId && $cardId) {
            $card = BankCard::query()->with('endowments')->find($cardId);
            $endowmentId = $card?->endowments->firstWhere('is_active', true)?->id
                ?? $card?->endowments->first()?->id;
        }

        if (! $endowmentId) {
            return null;
        }

        $amountMad = (float) ($expense->amount ?? 0);
        if ($amountMad <= 0) {
            return null;
        }

        return DB::transaction(function () use ($expense, $endowmentId, $cardId, $amountMad) {
            $existing = EndowmentConsumption::query()
                ->where('source_type', Expense::class)
                ->where('source_id', $expense->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $endowment = Endowment::query()->lockForUpdate()->findOrFail($endowmentId);
            if (! $endowment->is_active) {
                throw new RuntimeException('La dotation sélectionnée est inactive.');
            }

            $currency = $expense->currency ?: 'MAD';
            $amountCurrency = (float) ($expense->amount_currency ?? $amountMad);
            $rate = (float) ($expense->exchange_rate ?? 1);
            if ($rate <= 0) {
                $rate = 1;
            }

            $consumption = EndowmentConsumption::create([
                'endowment_id' => $endowment->id,
                'bank_card_id' => $cardId,
                'consumed_on' => ($expense->paid_at ?? $expense->expense_date)?->toDateString() ?? now()->toDateString(),
                'source_type' => Expense::class,
                'source_id' => $expense->id,
                'reference' => $expense->reference,
                'supplier_name' => $expense->supplier?->name,
                'currency' => $currency,
                'amount_currency' => $amountCurrency,
                'exchange_rate' => $rate,
                'amount_mad' => $amountMad,
                'notes' => $expense->designation,
                'user_id' => Auth::id(),
            ]);

            $endowment->consumed_amount = round((float) $endowment->consumed_amount + $amountMad, 2);
            $endowment->save();

            return $consumption;
        });
    }

    public function recalcConsumed(Endowment $endowment): void
    {
        $sum = (float) $endowment->consumptions()->sum('amount_mad');
        $endowment->update(['consumed_amount' => round($sum, 2)]);
    }
}
