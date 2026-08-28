<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\OrderFulfillment;
use App\Models\OrderTracking;
use App\Models\PaymentImportLine;
use App\Models\PosSale;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PaymentMatchingService
{
    public function __construct(
        protected PaymentMatchMemoryService $memories
    ) {}

    public const CONFIDENCE_HIGH = 'high';

    public const CONFIDENCE_AMBIGUOUS = 'ambiguous';

    public const CONFIDENCE_NONE = 'none';

    public const CRITERION_MEMORY = 'memory';

    public const CRITERION_TRACKING = 'tracking_exact';

    public const CRITERION_ORDER = 'order_exact';

    public const CRITERION_INVOICE = 'invoice_exact';

    public const CRITERION_EXTERNAL = 'external_id';

    public const CRITERION_PHONE = 'phone';

    public const CRITERION_PHONE_AMOUNT = 'phone_amount';

    public const CRITERION_NAME_AMOUNT = 'name_amount';

    public const CRITERION_NAME_DATE = 'name_date';

    public const CRITERION_AMOUNT_CITY_PERIOD = 'amount_city_period';

    /** @var array<string, array{weight: int, label: string}> */
    public const CRITERIA = [
        self::CRITERION_MEMORY => ['weight' => 95, 'label' => 'Rapprochement mémorisé'],
        self::CRITERION_TRACKING => ['weight' => 100, 'label' => 'Tracking exact'],
        self::CRITERION_ORDER => ['weight' => 90, 'label' => 'N° commande exact'],
        self::CRITERION_INVOICE => ['weight' => 90, 'label' => 'N° facture exact'],
        self::CRITERION_EXTERNAL => ['weight' => 85, 'label' => 'Référence externe / marketplace'],
        self::CRITERION_PHONE_AMOUNT => ['weight' => 95, 'label' => 'Téléphone + montant'],
        self::CRITERION_PHONE => ['weight' => 70, 'label' => 'Téléphone client'],
        self::CRITERION_NAME_AMOUNT => ['weight' => 60, 'label' => 'Nom client + montant'],
        self::CRITERION_NAME_DATE => ['weight' => 55, 'label' => 'Nom client + date'],
        self::CRITERION_AMOUNT_CITY_PERIOD => ['weight' => 50, 'label' => 'Montant + ville + période'],
    ];

    private const AUTO_MATCH_MIN_SCORE = 90;

    private const AUTO_MATCH_COMBINED_MIN_SCORE = 120;

    private const AMBIGUOUS_SCORE_GAP = 15;

    private const MIN_SCORE = 50;

    /**
     * @param  array{
     *   tracking?: ?string,
     *   order_ref?: ?string,
     *   invoice_ref?: ?string,
     *   reference?: ?string,
     *   external_ref?: ?string,
     *   client_name?: ?string,
     *   client_phone?: ?string,
     *   city?: ?string,
     *   delivery_date?: ?string,
     *   pickup_date?: ?string,
     *   gross_amount?: ?float,
     *   delivery_fees?: ?float,
     *   net_amount?: ?float,
     *   raw?: array,
     * }  $context
     * @return array{
     *   status: string,
     *   confidence: string,
     *   invoice_id: ?int,
     *   pos_sale_id: ?int,
     *   resolved_tracking: ?string,
     *   candidates: list<array<string, mixed>>,
     *   criteria: list<string>,
     *   score: int,
     *   confidence_percent: int,
     *   notes: string,
     * }
     */
    public function matchSalesLine(array $context): array
    {
        $scores = [];

        $this->memories->applyMemories($context, $scores);
        $this->matchByTracking($context['tracking'] ?? null, $scores);
        $this->matchByOrderRef($context['order_ref'] ?? null, $context['reference'] ?? null, $context['tracking'] ?? null, $scores);
        $this->matchByInvoiceRef($context['invoice_ref'] ?? null, $scores);
        $this->matchByExternalRef($context['external_ref'] ?? null, $context['reference'] ?? null, $context['order_ref'] ?? null, $scores);
        $this->matchByPhoneAndAmount($context['client_phone'] ?? null, $context['gross_amount'] ?? null, $scores);
        $this->matchByPhone($context['client_phone'] ?? null, $context['gross_amount'] ?? null, $scores);
        $this->matchByNameAndAmount($context['client_name'] ?? null, $context['gross_amount'] ?? null, $scores);
        $this->matchByNameAndDate($context['client_name'] ?? null, $context['delivery_date'] ?? null, $context['pickup_date'] ?? null, $scores);
        $this->matchByAmountCityPeriod(
            $context['gross_amount'] ?? null,
            $context['city'] ?? null,
            $context['delivery_date'] ?? null,
            $context['pickup_date'] ?? null,
            $scores
        );

        $candidates = $this->buildCandidates($scores);

        if ($candidates === []) {
            return [
                'status' => PaymentImportLine::MATCH_UNMATCHED,
                'confidence' => self::CONFIDENCE_NONE,
                'invoice_id' => null,
                'pos_sale_id' => null,
                'resolved_tracking' => $context['tracking'] ?? null,
                'candidates' => [],
                'criteria' => [],
                'score' => 0,
                'confidence_percent' => 0,
                'notes' => 'Aucune commande/facture reconnue après recherche multi-critères',
            ];
        }

        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $top = $candidates[0];
        $second = $candidates[1] ?? null;

        $isReliable = $this->isReliableMatch($top, $second);

        if ($isReliable) {
            $invoice = Invoice::query()->with('posSale.fulfillments')->find($top['invoice_id']);

            return [
                'status' => PaymentImportLine::MATCH_MATCHED,
                'confidence' => self::CONFIDENCE_HIGH,
                'invoice_id' => $top['invoice_id'],
                'pos_sale_id' => $invoice?->pos_sale_id,
                'resolved_tracking' => $context['tracking'] ?? $invoice?->posSale?->primaryTrackingNumber(),
                'candidates' => $candidates,
                'criteria' => $top['criteria'],
                'score' => $top['score'],
                'confidence_percent' => $this->confidencePercent($top),
                'notes' => $this->buildMatchNotes($top),
            ];
        }

        if ($top['score'] >= self::MIN_SCORE) {
            return [
                'status' => PaymentImportLine::MATCH_AMBIGUOUS,
                'confidence' => self::CONFIDENCE_AMBIGUOUS,
                'invoice_id' => null,
                'pos_sale_id' => null,
                'resolved_tracking' => $context['tracking'] ?? null,
                'candidates' => $candidates,
                'criteria' => $top['criteria'],
                'score' => $top['score'],
                'confidence_percent' => $this->confidencePercent($top),
                'notes' => count($candidates).' correspondances possibles — choisissez la facture',
            ];
        }

        return [
            'status' => PaymentImportLine::MATCH_UNMATCHED,
            'confidence' => self::CONFIDENCE_NONE,
            'invoice_id' => null,
            'pos_sale_id' => null,
            'resolved_tracking' => $context['tracking'] ?? null,
            'candidates' => $candidates,
            'criteria' => [],
            'score' => 0,
            'confidence_percent' => 0,
            'notes' => 'Correspondances insuffisamment fiables après recherche multi-critères',
        ];
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function addScore(array &$scores, int $invoiceId, string $criterion): void
    {
        if (! isset(self::CRITERIA[$criterion])) {
            return;
        }

        if (! isset($scores[$invoiceId])) {
            $scores[$invoiceId] = ['criteria' => [], 'score' => 0];
        }

        if (in_array($criterion, $scores[$invoiceId]['criteria'], true)) {
            return;
        }

        $scores[$invoiceId]['criteria'][] = $criterion;
        $scores[$invoiceId]['score'] += self::CRITERIA[$criterion]['weight'];
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function matchByTracking(?string $tracking, array &$scores): void
    {
        if (! $tracking) {
            return;
        }

        $saleIds = OrderFulfillment::query()
            ->where('tracking_number', $tracking)
            ->pluck('pos_sale_id');

        $saleIds = $saleIds->merge(
            OrderTracking::query()
                ->where('tracking_number', $tracking)
                ->pluck('pos_sale_id')
        )->unique();

        if ($saleIds->isEmpty()) {
            return;
        }

        Invoice::query()
            ->whereIn('pos_sale_id', $saleIds)
            ->pluck('id')
            ->each(fn (int $id) => $this->addScore($scores, $id, self::CRITERION_TRACKING));
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function matchByOrderRef(?string $orderRef, ?string $reference, ?string $tracking, array &$scores): void
    {
        foreach (array_filter([$orderRef, $reference, $tracking]) as $token) {
            foreach ($this->orderNumberVariants($token) as $variant) {
                $normalized = mb_strtoupper(trim($variant));

                PosSale::query()
                    ->where(function ($q) use ($normalized) {
                        $q->where('ticket_number', $normalized)
                            ->orWhere('ticket_number', 'like', $normalized.'%')
                            ->orWhere('ticket_number', 'like', '%'.$normalized);
                    })
                    ->with('invoice')
                    ->get()
                    ->each(function (PosSale $sale) use (&$scores, $normalized) {
                        if (! $sale->invoice) {
                            return;
                        }

                        $ticket = mb_strtoupper(trim((string) $sale->ticket_number));
                        if ($ticket === $normalized || str_ends_with($ticket, $normalized)) {
                            $this->addScore($scores, $sale->invoice->id, self::CRITERION_ORDER);
                        }
                    });
            }
        }
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function matchByInvoiceRef(?string $invoiceRef, array &$scores): void
    {
        if (! $invoiceRef) {
            return;
        }

        $normalized = mb_strtoupper(trim($invoiceRef));

        Invoice::query()
            ->where(function ($q) use ($normalized, $invoiceRef) {
                $q->where('invoice_number', $invoiceRef)
                    ->orWhere('invoice_number', $normalized)
                    ->orWhere('invoice_number', 'like', '%'.$invoiceRef.'%');
            })
            ->get()
            ->each(function (Invoice $invoice) use (&$scores, $normalized, $invoiceRef) {
                $number = mb_strtoupper(trim((string) $invoice->invoice_number));
                if ($number === $normalized || $number === mb_strtoupper(trim($invoiceRef))) {
                    $this->addScore($scores, $invoice->id, self::CRITERION_INVOICE);
                }
            });
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function matchByExternalRef(?string $externalRef, ?string $reference, ?string $orderRef, array &$scores): void
    {
        foreach (array_filter([$externalRef, $reference, $orderRef]) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            PosSale::query()
                ->where(function ($q) use ($token) {
                    $q->where('external_id', $token)
                        ->orWhere('shopify_order_id', $token)
                        ->orWhere('shopify_order_number', $token)
                        ->orWhere('external_id', 'like', '%'.$token.'%');
                })
                ->with('invoice')
                ->get()
                ->each(function (PosSale $sale) use (&$scores, $token) {
                    if (! $sale->invoice) {
                        return;
                    }

                    if (in_array($token, array_filter([
                        $sale->external_id,
                        $sale->shopify_order_id,
                        $sale->shopify_order_number,
                    ]), true)) {
                        $this->addScore($scores, $sale->invoice->id, self::CRITERION_EXTERNAL);
                    }
                });
        }
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function matchByPhoneAndAmount(?string $phone, ?float $grossAmount, array &$scores): void
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === null || strlen($normalized) < 9 || $grossAmount === null) {
            return;
        }

        $clientIds = $this->clientIdsForPhone($normalized);
        if ($clientIds->isEmpty()) {
            return;
        }

        Invoice::query()
            ->with(['items', 'payments'])
            ->whereIn('client_id', $clientIds)
            ->get()
            ->each(function (Invoice $invoice) use (&$scores, $grossAmount) {
                if ($this->amountMatchesInvoice($grossAmount, $invoice)) {
                    $this->addScore($scores, $invoice->id, self::CRITERION_PHONE_AMOUNT);
                }
            });
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function matchByPhone(?string $phone, ?float $grossAmount, array &$scores): void
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === null || strlen($normalized) < 9) {
            return;
        }

        $clientIds = $this->clientIdsForPhone($normalized);
        if ($clientIds->isEmpty()) {
            return;
        }

        $query = Invoice::query()
            ->with(['items', 'payments'])
            ->whereIn('client_id', $clientIds);

        if ($grossAmount !== null) {
            $query->get()->each(function (Invoice $invoice) use (&$scores, $grossAmount) {
                if ($this->amountMatchesInvoice($grossAmount, $invoice)) {
                    $this->addScore($scores, $invoice->id, self::CRITERION_PHONE);
                }
            });

            return;
        }

        $query->pluck('id')->each(fn (int $id) => $this->addScore($scores, $id, self::CRITERION_PHONE));
    }

    protected function clientIdsForPhone(string $normalized): \Illuminate\Support\Collection
    {
        return Client::query()
            ->where(function ($q) use ($normalized) {
                $q->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', ''), '.', '') LIKE ?", ['%'.$normalized.'%'])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(whatsapp, ' ', ''), '+', ''), '-', ''), '.', '') LIKE ?", ['%'.$normalized.'%']);
            })
            ->pluck('id');
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function matchByNameAndAmount(?string $clientName, ?float $grossAmount, array &$scores): void
    {
        $normalizedName = $this->normalizeName($clientName);
        if ($normalizedName === null || $grossAmount === null) {
            return;
        }

        $clientIds = Client::query()
            ->where('name', 'like', '%'.trim((string) $clientName).'%')
            ->pluck('id');

        if ($clientIds->isEmpty()) {
            return;
        }

        Invoice::query()
            ->with(['client', 'items', 'payments'])
            ->whereIn('client_id', $clientIds)
            ->get()
            ->each(function (Invoice $invoice) use (&$scores, $normalizedName, $grossAmount) {
                $invoiceName = $this->normalizeName($invoice->client?->name);
                if ($invoiceName === null || ! $this->namesMatch($normalizedName, $invoiceName)) {
                    return;
                }

                if ($this->amountMatchesInvoice($grossAmount, $invoice)) {
                    $this->addScore($scores, $invoice->id, self::CRITERION_NAME_AMOUNT);
                }
            });
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function matchByNameAndDate(?string $clientName, ?string $deliveryDate, ?string $pickupDate, array &$scores): void
    {
        $normalizedName = $this->normalizeName($clientName);
        $referenceDate = $this->parseDate($deliveryDate) ?? $this->parseDate($pickupDate);
        if ($normalizedName === null || $referenceDate === null) {
            return;
        }

        $clientIds = Client::query()
            ->where('name', 'like', '%'.trim((string) $clientName).'%')
            ->pluck('id');

        if ($clientIds->isEmpty()) {
            return;
        }

        Invoice::query()
            ->with(['client', 'posSale'])
            ->whereIn('client_id', $clientIds)
            ->get()
            ->each(function (Invoice $invoice) use (&$scores, $normalizedName, $referenceDate) {
                $invoiceName = $this->normalizeName($invoice->client?->name);
                if ($invoiceName === null || ! $this->namesMatch($normalizedName, $invoiceName)) {
                    return;
                }

                $invoiceDate = $invoice->invoice_date ?? $invoice->posSale?->sold_at;
                if ($invoiceDate && abs($referenceDate->diffInDays(Carbon::parse($invoiceDate))) <= 21) {
                    $this->addScore($scores, $invoice->id, self::CRITERION_NAME_DATE);
                }
            });
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     */
    protected function matchByAmountCityPeriod(?float $grossAmount, ?string $city, ?string $deliveryDate, ?string $pickupDate, array &$scores): void
    {
        if ($grossAmount === null) {
            return;
        }

        $normalizedCity = $this->normalizeCity($city);
        $referenceDate = $this->parseDate($deliveryDate) ?? $this->parseDate($pickupDate);

        $query = Invoice::query()
            ->with(['client', 'posSale', 'items', 'payments']);

        if ($referenceDate !== null) {
            $from = $referenceDate->copy()->subDays(30)->toDateString();
            $to = $referenceDate->copy()->addDays(30)->toDateString();
            $query->whereBetween('invoice_date', [$from, $to]);
        }

        $query->get()->each(function (Invoice $invoice) use (&$scores, $grossAmount, $normalizedCity, $referenceDate) {
                if (! $this->amountMatchesInvoice($grossAmount, $invoice)) {
                    return;
                }

                $invoiceCity = $this->normalizeCity($invoice->posSale?->shipping_city ?? $invoice->client?->city);
                if ($normalizedCity !== null && $invoiceCity !== null && ! $this->citiesMatch($normalizedCity, $invoiceCity)) {
                    return;
                }

                if ($referenceDate !== null) {
                    $invoiceDate = $invoice->invoice_date ?? $invoice->posSale?->sold_at;
                    if (! $invoiceDate || abs($referenceDate->diffInDays(Carbon::parse($invoiceDate))) > 30) {
                        return;
                    }
                } elseif ($normalizedCity === null) {
                    return;
                }

                $this->addScore($scores, $invoice->id, self::CRITERION_AMOUNT_CITY_PERIOD);
            });
    }

    /**
     * @param  array<int, array{criteria: list<string>, score: int}>  $scores
     * @return list<array<string, mixed>>
     */
    protected function buildCandidates(array $scores): array
    {
        if ($scores === []) {
            return [];
        }

        $invoices = Invoice::query()
            ->with(['client', 'posSale', 'items', 'payments'])
            ->whereIn('id', array_keys($scores))
            ->get()
            ->keyBy('id');

        $candidates = [];

        foreach ($scores as $invoiceId => $data) {
            $invoice = $invoices->get($invoiceId);
            if (! $invoice) {
                continue;
            }

            $candidate = [
                'invoice_id' => $invoiceId,
                'score' => $data['score'],
                'criteria' => $data['criteria'],
                'criteria_labels' => array_map(
                    fn (string $c) => self::CRITERIA[$c]['label'] ?? $c,
                    $data['criteria']
                ),
                'invoice_number' => $invoice->invoice_number,
                'order_number' => $invoice->posSale?->ticket_number,
                'client_name' => $invoice->client?->name,
                'amount' => round($invoice->computed_total, 2),
                'remaining' => round($invoice->remaining_balance, 2),
            ];
            $candidate['confidence_percent'] = $this->confidencePercent($candidate);
            $candidates[] = $candidate;
        }

        return $candidates;
    }

    protected function isReliableMatch(array $top, ?array $second): bool
    {
        if ($second !== null && ($top['score'] - $second['score']) < self::AMBIGUOUS_SCORE_GAP) {
            return false;
        }

        $criteria = $top['criteria'] ?? [];

        if (in_array(self::CRITERION_MEMORY, $criteria, true)) {
            return true;
        }

        if (in_array(self::CRITERION_PHONE_AMOUNT, $criteria, true)
            && in_array(self::CRITERION_NAME_AMOUNT, $criteria, true)) {
            return true;
        }

        if (count($criteria) >= 2 && $top['score'] >= self::AUTO_MATCH_COMBINED_MIN_SCORE) {
            return true;
        }

        if ($top['score'] >= self::AUTO_MATCH_MIN_SCORE) {
            return true;
        }

        if (count($criteria) >= 3 && $top['score'] >= self::MIN_SCORE) {
            return true;
        }

        return false;
    }

    /**
     * @param  array{score: int, criteria: list<string>}  $candidate
     */
    public function confidencePercent(array $candidate): int
    {
        $criteria = $candidate['criteria'] ?? [];
        $score = (int) ($candidate['score'] ?? 0);

        if (in_array(self::CRITERION_TRACKING, $criteria, true)
            || in_array(self::CRITERION_ORDER, $criteria, true)
            || in_array(self::CRITERION_INVOICE, $criteria, true)
            || in_array(self::CRITERION_MEMORY, $criteria, true)) {
            return 100;
        }

        if (in_array(self::CRITERION_PHONE_AMOUNT, $criteria, true)
            && (in_array(self::CRITERION_NAME_AMOUNT, $criteria, true) || count($criteria) >= 2)) {
            return min(100, max(90, (int) round($score / 2)));
        }

        return min(99, max(40, (int) round($score / 2)));
    }

    protected function buildMatchNotes(array $candidate): string
    {
        $labels = $candidate['criteria_labels'] ?? [];

        return 'Correspondance automatique ('.implode(', ', $labels).')';
    }

    protected function amountMatchesInvoice(float $amount, Invoice $invoice): bool
    {
        $total = round($invoice->computed_total, 2);
        $remaining = round($invoice->remaining_balance, 2);

        return abs($amount - $total) < 0.02 || abs($amount - $remaining) < 0.02;
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

        return $digits !== '' ? $digits : null;
    }

    protected function normalizeName(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return Str::of(Str::ascii($name))
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    protected function namesMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }

        similar_text($a, $b, $percent);

        return $percent >= 80;
    }

    protected function normalizeCity(?string $city): ?string
    {
        if ($city === null || trim($city) === '') {
            return null;
        }

        return Str::of(Str::ascii($city))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->trim()
            ->toString();
    }

    protected function citiesMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        return str_contains($a, $b) || str_contains($b, $a);
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', trim($value))) {
                return Carbon::createFromFormat('d/m/Y', trim($value))->startOfDay();
            }

            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Compare payment amounts accounting for carrier delivery fees.
     *
     * @return array{0:string,1:float,2:float}
     */
    public function compareAmountsWithFees(?float $grossAmount, ?float $deliveryFees, ?float $netAmount, float $expectedInvoiceAmount): array
    {
        $gross = $grossAmount;
        if ($gross === null && $netAmount !== null && $deliveryFees !== null) {
            $gross = round($netAmount + $deliveryFees, 2);
        }

        if ($gross === null) {
            $gross = $netAmount;
        }

        if ($gross === null) {
            return [PaymentImportLine::AMOUNT_OK, 0.0, $expectedInvoiceAmount];
        }

        $gross = round($gross, 2);
        $diff = round($gross - $expectedInvoiceAmount, 2);

        if (abs($diff) < 0.01) {
            return [PaymentImportLine::AMOUNT_OK, 0.0, $expectedInvoiceAmount];
        }

        if ($deliveryFees !== null && $netAmount !== null) {
            $expectedNet = round($expectedInvoiceAmount - $deliveryFees, 2);
            $netDiff = round($netAmount - $expectedNet, 2);
            if (abs($netDiff) < 0.01) {
                return [PaymentImportLine::AMOUNT_OK, 0.0, $expectedInvoiceAmount];
            }
        }

        if ($diff > 0) {
            return [PaymentImportLine::AMOUNT_OVERPAYMENT, $diff, $expectedInvoiceAmount];
        }

        return [PaymentImportLine::AMOUNT_DISCREPANCY, $diff, $expectedInvoiceAmount];
    }

    /**
     * @return list<string>
     */
    public function orderNumberVariants(string $value): array
    {
        $value = trim($value);
        $variants = [$value, ltrim($value, '#')];

        if (preg_match('/([A-Z]{2,}\d{3,})/i', $value, $m)) {
            $variants[] = strtoupper($m[1]);
        }

        if (preg_match('/(?:EGR|COL|EXP|CHR)?([A-Z]*\d+)/i', $value, $m)) {
            $variants[] = strtoupper($m[1]);
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
