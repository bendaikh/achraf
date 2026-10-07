<?php

namespace App\Services;

use App\Models\FinancialMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rattache les encaissements carte déjà enregistrés (commande / mouvement)
 * qui n’ont pas d’InvoicePayment affecté à la facture client.
 */
class ClientCardPaymentAllocationRepairService
{
    public function __construct(
        protected PaymentRecordingService $recorder,
        protected FinancialMovementService $movements,
        protected OrderToInvoiceConverter $orderToInvoice,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function audit(?int $invoiceId = null): array
    {
        return $this->candidates($invoiceId)
            ->map(fn (array $row) => array_merge($row, ['action' => 'allocate']))
            ->values()
            ->all();
    }

    /**
     * @return array{dry_run: bool, matched: int, allocated: int, skipped: int, rows: list<array<string, mixed>>}
     */
    public function repair(bool $apply, ?int $invoiceId = null): array
    {
        $rows = [];
        $allocated = 0;
        $skipped = 0;

        foreach ($this->candidates($invoiceId) as $candidate) {
            if (! $apply) {
                $rows[] = array_merge($candidate, [
                    'status' => 'would_allocate',
                    'payment_id' => null,
                ]);
                $allocated++;

                continue;
            }

            try {
                $payment = DB::transaction(function () use ($candidate) {
                    return $this->allocateCandidate($candidate);
                });

                if ($payment) {
                    $allocated++;
                    $rows[] = array_merge($candidate, [
                        'status' => 'allocated',
                        'payment_id' => $payment->id,
                        'invoice_status' => $payment->invoice?->fresh()->payment_status,
                        'remaining_after' => $payment->invoice?->fresh()->remaining_balance,
                    ]);
                } else {
                    $skipped++;
                    $rows[] = array_merge($candidate, [
                        'status' => 'skipped',
                        'payment_id' => null,
                    ]);
                }
            } catch (ValidationException $e) {
                $skipped++;
                $rows[] = array_merge($candidate, [
                    'status' => 'skipped_duplicate',
                    'payment_id' => null,
                    'error' => collect($e->errors())->flatten()->first(),
                ]);
            } catch (\Throwable $e) {
                $skipped++;
                $rows[] = array_merge($candidate, [
                    'status' => 'error',
                    'payment_id' => null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'dry_run' => ! $apply,
            'matched' => count($rows),
            'allocated' => $allocated,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function candidates(?int $invoiceId = null): Collection
    {
        $fromOrders = $this->candidatesFromPaidOrders($invoiceId);
        $fromMovements = $this->candidatesFromOrphanCardMovements($invoiceId);

        return $fromOrders
            ->concat($fromMovements)
            ->unique(fn (array $row) => $row['invoice_id'].'|'.number_format((float) $row['amount'], 2, '.', '').'|'.($row['reference'] ?? ''))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function candidatesFromPaidOrders(?int $invoiceId = null): Collection
    {
        $query = Invoice::query()
            ->with(['client', 'items', 'adjustments', 'payments', 'posSale'])
            ->whereNotNull('pos_sale_id')
            ->whereHas('posSale', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('payment_method', PosSale::PAYMENT_CARD)
                        ->orWhere('payment_method', 'like', '%carte%')
                        ->orWhere('payment_method', 'like', '%card%');
                })->where(function ($inner) {
                    $inner->where('payment_status', 'paid')
                        ->orWhere('payment_status', 'partially_paid')
                        ->orWhere('amount_received', '>', 0);
                });
            });

        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        return $query->orderBy('id')->get()
            ->filter(function (Invoice $invoice) {
                return $invoice->remaining_balance > 0.009;
            })
            ->map(function (Invoice $invoice) {
                $order = $invoice->posSale;
                $remaining = round($invoice->collectibleRemainingBalance(), 2);
                $received = round((float) ($order->amount_received ?? 0), 2);
                $amount = $received > 0.009
                    ? min($remaining, $received)
                    : ($order->payment_status === 'paid' ? $remaining : 0.0);

                if ($amount <= 0.009) {
                    return null;
                }

                if ($this->alreadyAllocated($invoice, $amount, $order->ticket_number, $order->id)) {
                    return null;
                }

                return [
                    'source' => 'order',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'client' => $invoice->client?->name,
                    'pos_sale_id' => $order->id,
                    'movement_id' => null,
                    'amount' => $amount,
                    'payment_date' => ($order->sold_at ?? $invoice->invoice_date)?->toDateString(),
                    'reference' => $order->ticket_number,
                    'method' => $order->paymentLabel() ?: 'Carte bancaire',
                    'remaining_before' => $invoice->remaining_balance,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Mouvements carte (POS / manuels) sans InvoicePayment correspondant.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function candidatesFromOrphanCardMovements(?int $invoiceId = null): Collection
    {
        $movements = FinancialMovement::query()
            ->with('source')
            ->where('type', FinancialMovement::TYPE_ENTREE)
            ->where('account', FinancialMovement::ACCOUNT_BANQUE)
            ->whereIn('origin', [
                FinancialMovement::ORIGIN_POS,
                FinancialMovement::ORIGIN_CLIENT,
                FinancialMovement::ORIGIN_MANUEL,
                FinancialMovement::ORIGIN_SHOPIFY,
            ])
            ->where('amount_in', '>', 0)
            ->orderBy('id')
            ->get()
            ->filter(fn (FinancialMovement $m) => $this->isCardRelatedMovement($m));

        $rows = collect();

        foreach ($movements as $movement) {
            $invoice = $this->resolveInvoiceForMovement($movement, $invoiceId);
            if (! $invoice || $invoice->remaining_balance <= 0.009) {
                continue;
            }

            // ORIGIN_CLIENT déjà lié à un InvoicePayment → déjà affecté.
            if ($movement->source_type === InvoicePayment::class) {
                continue;
            }

            $amount = min(round((float) $movement->amount_in, 2), round($invoice->collectibleRemainingBalance(), 2));
            if ($amount <= 0.009) {
                continue;
            }

            $reference = $this->movementReference($movement);
            if ($this->alreadyAllocated($invoice, $amount, $reference, $invoice->pos_sale_id)) {
                continue;
            }

            $rows->push([
                'source' => 'movement',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'client' => $invoice->client?->name,
                'pos_sale_id' => $invoice->pos_sale_id,
                'movement_id' => $movement->id,
                'amount' => $amount,
                'payment_date' => $movement->movement_date?->toDateString() ?? now()->toDateString(),
                'reference' => $reference,
                'method' => 'Carte bancaire',
                'remaining_before' => $invoice->remaining_balance,
            ]);
        }

        return $rows->values();
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    protected function allocateCandidate(array $candidate): ?InvoicePayment
    {
        $invoice = Invoice::query()->with(['items', 'payments', 'posSale', 'client'])->find($candidate['invoice_id']);
        if (! $invoice || $invoice->collectibleRemainingBalance() <= 0.009) {
            return null;
        }

        if ($candidate['source'] === 'order' && $invoice->posSale) {
            $payment = $this->orderToInvoice->attachOrderPayment($invoice, $invoice->posSale, $invoice->posSale->payment_status === 'paid');
            if ($payment && $candidate['movement_id']) {
                $this->neutralizeOrphanMovement((int) $candidate['movement_id'], $payment);
            } elseif ($payment && $invoice->posSale) {
                // Remplace le mouvement POS par le mouvement client (évite double comptage).
                $this->movements->syncFromPosSale($invoice->posSale->fresh(['client', 'invoice.payments']));
            }

            return $payment;
        }

        $dedupeKey = $this->recorder->buildDedupeKey([
            'scope' => 'sales',
            'invoice_id' => $invoice->id,
            'reference' => $candidate['reference'] ?? null,
            'amount' => round((float) $candidate['amount'], 2),
            'date' => $candidate['payment_date'] ?? null,
            'method' => $candidate['method'] ?? 'Carte bancaire',
            'source' => InvoicePayment::SOURCE_REPAIR,
            'movement_id' => $candidate['movement_id'] ?? null,
        ]);

        if ($dedupeKey && InvoicePayment::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        $payment = $this->recorder->recordInvoicePayment($invoice, [
            'payment_date' => $candidate['payment_date'],
            'amount' => $candidate['amount'],
            'gross_amount' => $candidate['amount'],
            'payment_method' => $candidate['method'] ?? 'Carte bancaire',
            'payment_reference' => $candidate['reference'] ?? null,
            'notes' => 'Rattrapage affectation carte (mouvement #'.($candidate['movement_id'] ?? '—').')',
            'source' => InvoicePayment::SOURCE_REPAIR,
            'dedupe_key' => $dedupeKey,
        ]);

        if (! empty($candidate['movement_id'])) {
            $this->neutralizeOrphanMovement((int) $candidate['movement_id'], $payment);
        }

        return $payment;
    }

    protected function neutralizeOrphanMovement(int $movementId, InvoicePayment $payment): void
    {
        $movement = FinancialMovement::query()->find($movementId);
        if (! $movement) {
            return;
        }

        // Si le mouvement pointe encore vers PosSale / manuel, on le retire :
        // le sync InvoicePayment a créé (ou va créer) le mouvement client équivalent.
        if ($movement->source_type === InvoicePayment::class) {
            return;
        }

        $movement->delete();
    }

    protected function alreadyAllocated(Invoice $invoice, float $amount, ?string $reference, ?int $posSaleId): bool
    {
        return InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->where(function ($q) use ($amount, $reference, $posSaleId) {
                if ($posSaleId) {
                    $q->where('pos_sale_id', $posSaleId);
                }
                if ($reference) {
                    $q->orWhere('payment_reference', $reference);
                }
                $q->orWhere(function ($inner) use ($amount) {
                    $inner->where('amount', round($amount, 2))
                        ->where(function ($method) {
                            $method->where('payment_method', 'like', '%carte%')
                                ->orWhere('payment_method', 'like', '%card%');
                        });
                });
            })
            ->exists();
    }

    protected function isCardRelatedMovement(FinancialMovement $movement): bool
    {
        $haystack = mb_strtolower(trim(
            ($movement->label ?? '').' '.($movement->notes ?? '').' '.($movement->origin ?? '')
        ));

        if (str_contains($haystack, 'carte') || str_contains($haystack, 'card') || str_contains($haystack, 'cb')) {
            return true;
        }

        $source = $movement->source;
        if ($source instanceof PosSale) {
            return $this->isCardMethod($source->payment_method);
        }

        if ($source instanceof InvoicePayment) {
            return $this->isCardMethod($source->payment_method);
        }

        // Mouvement banque POS sans libellé carte : le mode carte POS va en banque.
        return $movement->origin === FinancialMovement::ORIGIN_POS
            && $movement->account === FinancialMovement::ACCOUNT_BANQUE;
    }

    protected function isCardMethod(?string $method): bool
    {
        $m = mb_strtolower((string) $method);

        return $method === PosSale::PAYMENT_CARD
            || str_contains($m, 'carte')
            || str_contains($m, 'card');
    }

    protected function resolveInvoiceForMovement(FinancialMovement $movement, ?int $invoiceId = null): ?Invoice
    {
        $source = $movement->source;

        if ($source instanceof PosSale && $source->invoice) {
            $invoice = $source->invoice()->with(['client', 'items', 'adjustments', 'payments', 'posSale'])->first();
            if ($invoice && (! $invoiceId || $invoice->id === $invoiceId)) {
                return $invoice;
            }
        }

        if ($source instanceof InvoicePayment) {
            return null;
        }

        // Matching soft : même client (via nom dans label), montant et date proches.
        $amount = round((float) $movement->amount_in, 2);
        $date = $movement->movement_date?->toDateString();

        $query = Invoice::query()
            ->with(['client', 'items', 'adjustments', 'payments', 'posSale'])
            ->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_payments.invoice_id = invoices.id) < invoices.total');

        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        $candidates = $query->orderByDesc('invoice_date')->limit(200)->get()
            ->filter(fn (Invoice $inv) => $inv->remaining_balance > 0.009);

        $matched = $candidates->filter(function (Invoice $invoice) use ($amount, $date, $movement) {
            $due = round($invoice->collectibleRemainingBalance(), 2);
            $total = round($invoice->collectibleTotal(), 2);
            $amountOk = abs($amount - $due) < 0.02 || abs($amount - $total) < 0.02;
            if (! $amountOk) {
                return false;
            }

            $clientName = mb_strtolower((string) $invoice->client?->name);
            $label = mb_strtolower((string) $movement->label);
            $clientOk = $clientName !== '' && str_contains($label, $clientName);

            $dateOk = true;
            if ($date && $invoice->invoice_date) {
                $diff = abs($invoice->invoice_date->diffInDays($movement->movement_date));
                $dateOk = $diff <= 45;
            }

            return $clientOk && $dateOk;
        });

        if ($matched->count() === 1) {
            return $matched->first();
        }

        return null;
    }

    protected function movementReference(FinancialMovement $movement): ?string
    {
        $source = $movement->source;
        if ($source instanceof PosSale) {
            return $source->ticket_number;
        }

        if (preg_match('/\b(FAST\d+|POS-[A-Z0-9\/-]+|#?\d{4,})\b/i', (string) $movement->label, $m)) {
            return $m[1];
        }

        return $movement->reference ?: ('FM-'.$movement->id);
    }
}
