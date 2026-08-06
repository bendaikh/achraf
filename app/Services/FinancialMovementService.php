<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FinancialMovement;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Models\SupplierInvoicePayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FinancialMovementService
{
    public function syncFromInvoicePayment(InvoicePayment $payment): FinancialMovement
    {
        $payment->loadMissing('invoice.client');

        $clientName = $payment->invoice?->client?->name;
        $invoiceNumber = $payment->invoice?->invoice_number ?? '—';

        return $this->upsertFromSource($payment, [
            'movement_date' => $payment->payment_date,
            'origin' => FinancialMovement::ORIGIN_CLIENT,
            'type' => FinancialMovement::TYPE_ENTREE,
            'label' => 'Encaissement facture '.$invoiceNumber.($clientName ? ' — '.$clientName : ''),
            'account' => $this->classifyPaymentMethod($payment->payment_method),
            'amount_in' => (float) $payment->amount,
            'amount_out' => 0,
            'justificatif_path' => $payment->payment_file_path,
            'notes' => $payment->notes,
        ]);
    }

    public function syncFromSupplierPayment(SupplierInvoicePayment $payment): FinancialMovement
    {
        $payment->loadMissing('supplierInvoice.supplier');

        $supplierName = $payment->supplierInvoice?->supplier?->name;
        $invoiceNumber = $payment->supplierInvoice?->invoice_number ?? '—';

        return $this->upsertFromSource($payment, [
            'movement_date' => $payment->payment_date,
            'origin' => FinancialMovement::ORIGIN_FOURNISSEUR,
            'type' => FinancialMovement::TYPE_SORTIE,
            'label' => 'Paiement fournisseur '.$invoiceNumber.($supplierName ? ' — '.$supplierName : ''),
            'account' => $this->classifyPaymentMethod($payment->payment_method),
            'amount_in' => 0,
            'amount_out' => (float) $payment->amount,
            'justificatif_path' => $payment->payment_file_path,
            'notes' => $payment->notes,
        ]);
    }

    public function syncFromExpense(Expense $expense): FinancialMovement
    {
        $expense->loadMissing(['supplier', 'client']);

        $origin = $this->originFromExpenseCategory($expense);
        $party = $expense->supplier?->name ?? $expense->client?->name;
        $kind = $expense->expense_type === 'with_invoice' ? 'Dépense avec facture' : 'Dépense sans facture';

        return $this->upsertFromSource($expense, [
            'movement_date' => $expense->expense_date,
            'origin' => $origin,
            'type' => FinancialMovement::TYPE_SORTIE,
            'label' => $kind.' — '.($expense->designation ?: ($expense->reference ?: 'Dépense')),
            'account' => $this->classifyExpenseAccount($expense->account, $expense->payment_method),
            'amount_in' => 0,
            'amount_out' => (float) $expense->amount,
            'justificatif_path' => $expense->invoice_file_path,
            'notes' => $party ? 'Tiers: '.$party : null,
        ]);
    }

    public function syncFromPosSale(PosSale $sale): ?FinancialMovement
    {
        if ($sale->status !== PosSale::STATUS_COMPLETED) {
            $this->deleteForSource($sale);

            return null;
        }

        $sale->loadMissing(['client', 'invoice.payments']);

        // Avoid double-counting when the POS ticket already has invoice payments.
        if ($sale->invoice && $sale->invoice->payments->isNotEmpty()) {
            $this->deleteForSource($sale);

            return null;
        }

        $origin = $sale->source === 'shopify'
            ? FinancialMovement::ORIGIN_SHOPIFY
            : FinancialMovement::ORIGIN_POS;

        $party = $sale->client?->name ?? 'Comptoir';

        return $this->upsertFromSource($sale, [
            'movement_date' => $sale->sold_at?->toDateString() ?? now()->toDateString(),
            'origin' => $origin,
            'type' => FinancialMovement::TYPE_ENTREE,
            'label' => 'Encaissement POS '.$sale->ticket_number.' — '.$party,
            'account' => $this->classifyPosPaymentMethod($sale->payment_method),
            'amount_in' => (float) $sale->total,
            'amount_out' => 0,
            'user_id' => $sale->user_id,
            'notes' => $sale->notes,
        ]);
    }

    public function deleteForSource(Model $source): void
    {
        FinancialMovement::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('is_manual', false)
            ->whereNull('day_closed_at')
            ->where('status', '!=', FinancialMovement::STATUS_CLOTURE)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createManual(array $attributes, ?int $userId = null): FinancialMovement
    {
        $type = $attributes['type'] ?? FinancialMovement::TYPE_SORTIE;
        $amount = (float) ($attributes['amount'] ?? 0);

        return FinancialMovement::create([
            'reference' => $this->nextReference(),
            'movement_date' => $attributes['movement_date'],
            'origin' => $attributes['origin'] ?? FinancialMovement::ORIGIN_MANUEL,
            'type' => $type,
            'label' => $attributes['label'],
            'account' => $attributes['account'] ?? FinancialMovement::ACCOUNT_OTHER,
            'amount_in' => $type === FinancialMovement::TYPE_ENTREE ? $amount : (float) ($attributes['amount_in'] ?? 0),
            'amount_out' => in_array($type, [FinancialMovement::TYPE_SORTIE, FinancialMovement::TYPE_VIREMENT], true)
                ? ($type === FinancialMovement::TYPE_VIREMENT
                    ? (float) ($attributes['amount_out'] ?? $amount)
                    : $amount)
                : (float) ($attributes['amount_out'] ?? 0),
            'status' => FinancialMovement::STATUS_VALIDE,
            'is_manual' => true,
            'user_id' => $userId,
            'justificatif_path' => $attributes['justificatif_path'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);
    }

    public function updateManual(FinancialMovement $movement, array $attributes): FinancialMovement
    {
        if (! $movement->isEditable()) {
            throw new \RuntimeException('Ce mouvement ne peut pas être modifié.');
        }

        $type = $attributes['type'] ?? $movement->type;
        $amount = (float) ($attributes['amount'] ?? max((float) $movement->amount_in, (float) $movement->amount_out));

        $movement->update([
            'movement_date' => $attributes['movement_date'] ?? $movement->movement_date,
            'origin' => $attributes['origin'] ?? $movement->origin,
            'type' => $type,
            'label' => $attributes['label'] ?? $movement->label,
            'account' => $attributes['account'] ?? $movement->account,
            'amount_in' => $type === FinancialMovement::TYPE_ENTREE ? $amount : 0,
            'amount_out' => $type === FinancialMovement::TYPE_ENTREE ? 0 : $amount,
            'justificatif_path' => $attributes['justificatif_path'] ?? $movement->justificatif_path,
            'notes' => $attributes['notes'] ?? $movement->notes,
        ]);

        return $movement->fresh();
    }

    public function point(FinancialMovement $movement, int $userId): FinancialMovement
    {
        if ($movement->isLocked()) {
            throw new \RuntimeException('Ce mouvement est clôturé.');
        }

        $movement->update([
            'status' => FinancialMovement::STATUS_POINTE,
            'pointed_at' => now(),
            'pointed_by' => $userId,
        ]);

        return $movement->fresh();
    }

    public function closeDay(Carbon $day, int $userId): int
    {
        $count = 0;

        FinancialMovement::query()
            ->whereDate('movement_date', $day->toDateString())
            ->whereNull('day_closed_at')
            ->where('status', '!=', FinancialMovement::STATUS_CLOTURE)
            ->orderBy('id')
            ->chunkById(100, function ($movements) use ($userId, &$count) {
                foreach ($movements as $movement) {
                    $movement->update([
                        'status' => FinancialMovement::STATUS_CLOTURE,
                        'day_closed_at' => now(),
                        'pointed_at' => $movement->pointed_at ?? now(),
                        'pointed_by' => $movement->pointed_by ?? $userId,
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Recreate journal entries from operational source tables.
     *
     * @return array{created: int, skipped: int}
     */
    public function backfill(): array
    {
        $created = 0;
        $skipped = 0;

        InvoicePayment::query()->with('invoice.client')->orderBy('id')->chunkById(100, function ($payments) use (&$created) {
            foreach ($payments as $payment) {
                $this->syncFromInvoicePayment($payment);
                $created++;
            }
        });

        SupplierInvoicePayment::query()->with('supplierInvoice.supplier')->orderBy('id')->chunkById(100, function ($payments) use (&$created) {
            foreach ($payments as $payment) {
                $this->syncFromSupplierPayment($payment);
                $created++;
            }
        });

        Expense::query()->with(['supplier', 'client'])->orderBy('id')->chunkById(100, function ($expenses) use (&$created) {
            foreach ($expenses as $expense) {
                $this->syncFromExpense($expense);
                $created++;
            }
        });

        PosSale::query()
            ->with(['client', 'invoice.payments'])
            ->where('status', PosSale::STATUS_COMPLETED)
            ->orderBy('id')
            ->chunkById(100, function ($sales) use (&$created, &$skipped) {
                foreach ($sales as $sale) {
                    $movement = $this->syncFromPosSale($sale);
                    if ($movement) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return compact('created', 'skipped');
    }

    /**
     * Running balances for a filtered set of movements (oldest first).
     *
     * @param  \Illuminate\Support\Collection<int, FinancialMovement>  $movements
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function withRunningBalances($movements, float $openingBalance = 0.0)
    {
        $balance = $openingBalance;

        return $movements->map(function (FinancialMovement $movement) use (&$balance) {
            $balance = round($balance + (float) $movement->amount_in - (float) $movement->amount_out, 2);

            return [
                'movement' => $movement,
                'solde' => $balance,
            ];
        });
    }

    /**
     * @return array{total: float, caisse: float, banque: float, other: float, entrees: float, sorties: float}
     */
    public function treasuryFromMovements(?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $base = FinancialMovement::query()
            ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('movement_date', [$dateFrom->toDateString(), $dateTo->toDateString()]));

        $entrees = (float) (clone $base)->sum('amount_in');
        $sorties = (float) (clone $base)->sum('amount_out');

        // Soldes cumulés jusqu'à date_to (ou aujourd'hui)
        $asOf = $dateTo?->toDateString() ?? now()->toDateString();

        $caisse = $this->accountBalance(FinancialMovement::ACCOUNT_CAISSE, $asOf);
        $banque = $this->accountBalance(FinancialMovement::ACCOUNT_BANQUE, $asOf);
        $other = $this->accountBalance(FinancialMovement::ACCOUNT_OTHER, $asOf);

        return [
            'caisse' => $caisse,
            'banque' => $banque,
            'other' => $other,
            'total' => round($caisse + $banque + $other, 2),
            'entrees' => round($entrees, 2),
            'sorties' => round($sorties, 2),
        ];
    }

    public function accountBalance(string $account, string $asOfDate): float
    {
        $in = (float) FinancialMovement::query()
            ->where('account', $account)
            ->whereDate('movement_date', '<=', $asOfDate)
            ->sum('amount_in');

        $out = (float) FinancialMovement::query()
            ->where('account', $account)
            ->whereDate('movement_date', '<=', $asOfDate)
            ->sum('amount_out');

        return round($in - $out, 2);
    }

    public function classifyPaymentMethod(?string $method): string
    {
        $method = mb_strtolower(trim((string) $method));

        return match (true) {
            $method === '' => FinancialMovement::ACCOUNT_OTHER,
            str_contains($method, 'esp'), str_contains($method, 'caisse'), str_contains($method, 'cash') => FinancialMovement::ACCOUNT_CAISSE,
            str_contains($method, 'carte'),
            str_contains($method, 'vir'),
            str_contains($method, 'chèque'),
            str_contains($method, 'cheque'),
            str_contains($method, 'banque'),
            str_contains($method, 'bank'),
            str_contains($method, 'card'),
            str_contains($method, 'transfer') => FinancialMovement::ACCOUNT_BANQUE,
            default => FinancialMovement::ACCOUNT_OTHER,
        };
    }

    public function classifyPosPaymentMethod(?string $method): string
    {
        $method = strtolower(trim((string) $method));

        return match (true) {
            $method === '' => FinancialMovement::ACCOUNT_OTHER,
            $method === PosSale::PAYMENT_CASH, str_contains($method, 'esp'), str_contains($method, 'cash'), str_contains($method, 'caisse') => FinancialMovement::ACCOUNT_CAISSE,
            $method === PosSale::PAYMENT_CARD,
            $method === PosSale::PAYMENT_TRANSFER,
            $method === PosSale::PAYMENT_CHEQUE,
            str_contains($method, 'carte'),
            str_contains($method, 'vir'),
            str_contains($method, 'chèque'),
            str_contains($method, 'cheque'),
            str_contains($method, 'banque'),
            str_contains($method, 'bank') => FinancialMovement::ACCOUNT_BANQUE,
            default => FinancialMovement::ACCOUNT_OTHER,
        };
    }

    public function classifyExpenseAccount(?string $account, ?string $paymentMethod): string
    {
        $account = mb_strtolower(trim((string) $account));

        if (str_contains($account, 'caisse') || str_contains($account, 'esp')) {
            return FinancialMovement::ACCOUNT_CAISSE;
        }

        if (str_contains($account, 'banque') || str_contains($account, 'bank') || str_contains($account, 'compte')) {
            return FinancialMovement::ACCOUNT_BANQUE;
        }

        return $this->classifyPaymentMethod($paymentMethod);
    }

    private function originFromExpenseCategory(Expense $expense): string
    {
        $category = mb_strtolower(trim((string) ($expense->expense_category ?? '')));
        $designation = mb_strtolower(trim((string) ($expense->designation ?? '')));
        $haystack = $category.' '.$designation;

        return match (true) {
            str_contains($haystack, 'salaire') => FinancialMovement::ORIGIN_SALAIRE,
            str_contains($haystack, 'loyer') => FinancialMovement::ORIGIN_LOYER,
            str_contains($haystack, 'eau'),
            str_contains($haystack, 'électri'),
            str_contains($haystack, 'electri'),
            str_contains($haystack, 'internet'),
            str_contains($haystack, 'télécom'),
            str_contains($haystack, 'telecom') => FinancialMovement::ORIGIN_UTILITIES,
            str_contains($haystack, 'frais banc'),
            str_contains($haystack, 'banque') => FinancialMovement::ORIGIN_BANQUE,
            default => FinancialMovement::ORIGIN_DEPENSE,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertFromSource(Model $source, array $attributes): FinancialMovement
    {
        $existing = FinancialMovement::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->first();

        if ($existing && $existing->isLocked()) {
            return $existing;
        }

        $payload = array_merge($attributes, [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'is_manual' => false,
            'status' => $existing?->status ?? FinancialMovement::STATUS_VALIDE,
            'user_id' => $attributes['user_id'] ?? $existing?->user_id ?? auth()->id(),
        ]);

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        $payload['reference'] = $this->nextReference();

        return FinancialMovement::create($payload);
    }

    public function nextReference(): string
    {
        $last = FinancialMovement::query()
            ->where('reference', 'like', 'MVT-%')
            ->orderByDesc('id')
            ->value('reference');

        $next = 1;
        if ($last && preg_match('/MVT-(\d+)/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return 'MVT-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
