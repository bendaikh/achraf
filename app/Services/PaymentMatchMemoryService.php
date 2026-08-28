<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentImportLine;
use App\Models\PaymentMatchMemory;
use Illuminate\Support\Facades\Auth;

class PaymentMatchMemoryService
{
    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    public function applyMemories(array $context, array &$scores): void
    {
        $keys = $this->buildLookupKeys($context);

        if ($keys === []) {
            return;
        }

        $memories = PaymentMatchMemory::query()
            ->where(function ($query) use ($keys) {
                foreach ($keys as $type => $value) {
                    $query->orWhere(function ($q) use ($type, $value) {
                        $q->where('lookup_type', $type)->where('lookup_value', $value);
                    });
                }
            })
            ->get();

        foreach ($memories as $memory) {
            $this->addMemoryScore($scores, $memory->invoice_id);
            $memory->increment('hit_count');
            $memory->update(['last_used_at' => now()]);
        }
    }

    public function rememberFromLine(PaymentImportLine $line, Invoice $invoice): void
    {
        $raw = $line->file_raw ?? [];
        $context = [
            'tracking' => $line->file_tracking ?? $line->resolved_tracking,
            'order_ref' => $line->file_order_ref,
            'client_name' => $raw['client'] ?? $raw['nom'] ?? $raw['nom_client'] ?? $raw['destinataire'] ?? null,
            'client_phone' => $raw['telephone'] ?? $raw['tel'] ?? $raw['phone'] ?? null,
            'external_ref' => $raw['external_id'] ?? $raw['order_id'] ?? $raw['marketplace_id'] ?? null,
            'gross_amount' => $line->file_amount !== null ? (float) $line->file_amount : null,
        ];

        foreach ($this->buildLookupKeys($context) as $type => $value) {
            PaymentMatchMemory::query()->updateOrCreate(
                ['lookup_type' => $type, 'lookup_value' => $value],
                [
                    'invoice_id' => $invoice->id,
                    'created_by' => Auth::id(),
                    'last_used_at' => now(),
                ]
            );
        }
    }

    /**
     * @param  array{
     *   tracking?: ?string,
     *   order_ref?: ?string,
     *   client_name?: ?string,
     *   client_phone?: ?string,
     *   external_ref?: ?string,
     *   gross_amount?: ?float,
     * }  $context
     * @return array<string, string>
     */
    public function buildLookupKeys(array $context): array
    {
        $keys = [];

        $tracking = trim((string) ($context['tracking'] ?? ''));
        if ($tracking !== '') {
            $keys[PaymentMatchMemory::TYPE_TRACKING] = mb_strtoupper($tracking);
        }

        $orderRef = trim((string) ($context['order_ref'] ?? ''));
        if ($orderRef !== '') {
            $keys[PaymentMatchMemory::TYPE_ORDER] = mb_strtoupper($orderRef);
        }

        $external = trim((string) ($context['external_ref'] ?? ''));
        if ($external !== '') {
            $keys[PaymentMatchMemory::TYPE_EXTERNAL] = mb_strtolower($external);
        }

        $phone = $this->normalizePhone($context['client_phone'] ?? null);
        if ($phone !== null) {
            $keys[PaymentMatchMemory::TYPE_PHONE] = $phone;

            $amount = $context['gross_amount'] ?? null;
            if ($amount !== null) {
                $keys[PaymentMatchMemory::TYPE_PHONE_AMOUNT] = $phone.'|'.number_format((float) $amount, 2, '.', '');
            }
        }

        $name = $this->normalizeName($context['client_name'] ?? null);
        $amount = $context['gross_amount'] ?? null;
        if ($name !== null && $amount !== null) {
            $keys[PaymentMatchMemory::TYPE_CLIENT_AMOUNT] = $name.'|'.number_format((float) $amount, 2, '.', '');
        }

        return $keys;
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function addMemoryScore(array &$scores, int $invoiceId): void
    {
        if (! isset($scores[$invoiceId])) {
            $scores[$invoiceId] = ['criteria' => [], 'score' => 0];
        }

        $criterion = PaymentMatchingService::CRITERION_MEMORY;
        if (in_array($criterion, $scores[$invoiceId]['criteria'], true)) {
            return;
        }

        $scores[$invoiceId]['criteria'][] = $criterion;
        $scores[$invoiceId]['score'] += PaymentMatchingService::CRITERIA[$criterion]['weight'];
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $digits = ltrim($digits, '0');

        if (str_starts_with($digits, '212')) {
            $digits = substr($digits, 3);
        }

        return strlen($digits) >= 9 ? $digits : null;
    }

    protected function normalizeName(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return mb_strtolower(trim($name));
    }
}
