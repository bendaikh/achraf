<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\FinancialMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\FinancialMovementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gestion financière clients — vues de suivi en lecture seule.
 *
 * Les factures restent dans Gestion ventes ; les règlements sont toujours saisis via
 * « Gestion des paiements clients » (SalesPaymentController / InvoicePaymentController).
 * Ce contrôleur ne crée ni ne modifie aucun paiement : il lit invoice_payments,
 * invoices et financial_movements (pointage = action existante du journal des mouvements).
 */
class SalesFinanceController extends Controller
{
    use FiltersIndexTables;

    public const PAYMENT_METHODS = ['Espèces', 'Chèque', 'Virement bancaire', 'Carte bancaire', 'Autre'];

    public function __construct(
        protected FinancialMovementService $movements,
    ) {}

    /**
     * Encaissements : liste des règlements clients reçus, filtres et totaux.
     */
    public function receipts(Request $request)
    {
        $query = $this->paymentsQuery($request);

        $totalsRow = (clone $query)
            ->reorder()
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(invoice_payments.amount),0) as gross, COALESCE(SUM(invoice_payments.delivery_fees),0) as fees, COALESCE(SUM(COALESCE(invoice_payments.net_received, invoice_payments.amount)),0) as net')
            ->first();

        $byMethod = (clone $query)
            ->reorder()
            ->selectRaw("COALESCE(NULLIF(invoice_payments.payment_method, ''), 'Non renseigné') as method, COUNT(*) as cnt, COALESCE(SUM(invoice_payments.amount),0) as total")
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $payments = $this->paginateTable(
            $query->with(['invoice.client', 'invoice.posSale', 'user'])
                ->orderByDesc('invoice_payments.payment_date')
                ->orderByDesc('invoice_payments.id'),
            $request,
            25
        );

        return view('sales.finance.receipts', [
            'payments' => $payments,
            'totals' => [
                'count' => (int) ($totalsRow->cnt ?? 0),
                'gross' => (float) ($totalsRow->gross ?? 0),
                'fees' => (float) ($totalsRow->fees ?? 0),
                'net' => (float) ($totalsRow->net ?? 0),
            ],
            'byMethod' => $byMethod,
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    /**
     * Impayés / reste à payer : factures clients dont le solde est > 0.
     * Même règle de solde que Gestion des paiements clients (total TTC calculé − paiements).
     */
    public function unpaid(Request $request)
    {
        $query = Invoice::query()
            ->with(['client', 'items', 'adjustments', 'posSale'])
            ->withSum('payments as payments_sum', 'amount')
            ->withMax('payments as last_payment_date', 'payment_date')
            // Pré-filtre SQL identique au filtre « Non soldé » de Gestion Paiement.
            ->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_payments.invoice_id = invoices.id) < invoices.total');

        $this->applyTableSearch($query, $request, ['invoice_number', 'client.name', 'posSale.ticket_number']);
        $this->applyTableDateRange($query, $request, 'invoice_date');

        $today = Carbon::today();

        /** @var Collection<int, Invoice> $rows */
        $rows = $query->orderBy('due_date')->orderBy('invoice_date')->get()
            ->map(function (Invoice $invoice) use ($today) {
                $paid = (float) ($invoice->payments_sum ?? 0);
                $invoice->setAttribute('fin_total', round($invoice->computed_total, 2));
                $invoice->setAttribute('fin_paid', round($paid, 2));
                // Total TTC − paiements réels − avoirs (même règle que Invoice::remaining_balance).
                $invoice->setAttribute('fin_remaining', round($invoice->remaining_balance, 2));
                $due = $invoice->due_date ?? $invoice->invoice_date;
                $daysLate = $due && $due->lt($today) ? (int) $due->diffInDays($today) : 0;
                $invoice->setAttribute('fin_days_late', $daysLate);
                $invoice->setAttribute('fin_bucket', $this->agingBucket($daysLate));

                return $invoice;
            })
            ->filter(fn (Invoice $invoice) => $invoice->fin_remaining > 0.009)
            ->values();

        if ($request->input('overdue') === '1') {
            $rows = $rows->filter(fn (Invoice $invoice) => $invoice->fin_days_late > 0)->values();
        }
        if ($request->filled('bucket')) {
            $rows = $rows->filter(fn (Invoice $invoice) => $invoice->fin_bucket === $request->input('bucket'))->values();
        }

        $buckets = collect(self::agingBuckets())->map(fn (string $label, string $key) => [
            'key' => $key,
            'label' => $label,
            'count' => $rows->where('fin_bucket', $key)->count(),
            'amount' => round($rows->where('fin_bucket', $key)->sum('fin_remaining'), 2),
        ])->values();

        $totals = [
            'count' => $rows->count(),
            'total' => round($rows->sum('fin_total'), 2),
            'paid' => round($rows->sum('fin_paid'), 2),
            'remaining' => round($rows->sum('fin_remaining'), 2),
            'clients' => $rows->pluck('client_id')->filter()->unique()->count(),
        ];

        $topClients = $rows->groupBy('client_id')
            ->map(fn (Collection $group) => [
                'name' => $group->first()->client?->name ?? 'Client inconnu',
                'count' => $group->count(),
                'remaining' => round($group->sum('fin_remaining'), 2),
            ])
            ->sortByDesc('remaining')
            ->take(5)
            ->values();

        $perPage = $this->resolvePerPage($request, 25);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $invoices = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('sales.finance.unpaid', compact('invoices', 'totals', 'buckets', 'topClients'));
    }

    /**
     * Rapprochements / trésorerie : encaissements clients par mode et par jour,
     * avec l'état du mouvement de trésorerie lié (validé / pointé / clôturé).
     */
    public function reconciliation(Request $request)
    {
        // Période par défaut : mois en cours.
        if (! $request->filled('date_from') && ! $request->filled('date_to')) {
            $request->merge([
                'date_from' => Carbon::today()->startOfMonth()->toDateString(),
                'date_to' => Carbon::today()->toDateString(),
            ]);
        }

        $query = $this->paymentsQuery($request);
        $payments = $query->with(['invoice.client'])
            ->orderByDesc('invoice_payments.payment_date')
            ->orderByDesc('invoice_payments.id')
            ->get();

        $movements = FinancialMovement::query()
            ->where('source_type', (new InvoicePayment)->getMorphClass())
            ->whereIn('source_id', $payments->pluck('id'))
            ->get()
            ->keyBy('source_id');

        $rows = $payments->map(function (InvoicePayment $payment) use ($movements) {
            $movement = $movements->get($payment->id);
            $state = match (true) {
                ! $payment->isRealized() => 'programme',
                ! $movement => 'non_synchronise',
                in_array($movement->status, [FinancialMovement::STATUS_POINTE, FinancialMovement::STATUS_CLOTURE], true) => 'pointe',
                default => 'a_pointer',
            };

            return [
                'payment' => $payment,
                'movement' => $movement,
                'account' => $this->movements->classifyPaymentMethod($payment->payment_method),
                'state' => $state,
                'gross' => (float) $payment->amount,
                'net' => (float) ($payment->net_received ?? $payment->amount),
            ];
        });

        if ($request->filled('state')) {
            $rows = $rows->where('state', $request->input('state'))->values();
        }

        $byMethod = $rows->groupBy(fn ($row) => $row['payment']->payment_method ?: 'Non renseigné')
            ->map(fn (Collection $group, string $method) => [
                'method' => $method,
                'account' => $group->first()['account'],
                'count' => $group->count(),
                'gross' => round($group->sum('gross'), 2),
                'net' => round($group->sum('net'), 2),
                'pointed' => round($group->where('state', 'pointe')->sum('net'), 2),
                'to_point' => round($group->whereIn('state', ['a_pointer', 'non_synchronise'])->sum('net'), 2),
            ])
            ->sortByDesc('gross')
            ->values();

        $byDay = $rows->groupBy(fn ($row) => $row['payment']->payment_date?->toDateString() ?? '—')
            ->map(fn (Collection $group, string $day) => [
                'day' => $day,
                'count' => $group->count(),
                'caisse' => round($group->where('account', FinancialMovement::ACCOUNT_CAISSE)->sum('net'), 2),
                'banque' => round($group->where('account', FinancialMovement::ACCOUNT_BANQUE)->sum('net'), 2),
                'other' => round($group->where('account', FinancialMovement::ACCOUNT_OTHER)->sum('net'), 2),
                'net' => round($group->sum('net'), 2),
                'pointed' => round($group->where('state', 'pointe')->sum('net'), 2),
            ])
            ->sortKeysDesc()
            ->values();

        $totals = [
            'count' => $rows->count(),
            'gross' => round($rows->sum('gross'), 2),
            'net' => round($rows->sum('net'), 2),
            'caisse' => round($rows->where('account', FinancialMovement::ACCOUNT_CAISSE)->sum('net'), 2),
            'banque' => round($rows->where('account', FinancialMovement::ACCOUNT_BANQUE)->sum('net'), 2),
            'pointed' => round($rows->where('state', 'pointe')->sum('net'), 2),
            'to_point' => round($rows->whereIn('state', ['a_pointer', 'non_synchronise'])->sum('net'), 2),
            'scheduled' => round($rows->where('state', 'programme')->sum('gross'), 2),
        ];

        $perPage = $this->resolvePerPage($request, 25);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $detail = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('sales.finance.reconciliation', [
            'byMethod' => $byMethod,
            'byDay' => $byDay,
            'totals' => $totals,
            'detail' => $detail,
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function agingBuckets(): array
    {
        return [
            'not_due' => 'Non échu',
            'd30' => '1 – 30 j',
            'd60' => '31 – 60 j',
            'd90' => '61 – 90 j',
            'd90plus' => '+ 90 j',
        ];
    }

    protected function agingBucket(int $daysLate): string
    {
        return match (true) {
            $daysLate <= 0 => 'not_due',
            $daysLate <= 30 => 'd30',
            $daysLate <= 60 => 'd60',
            $daysLate <= 90 => 'd90',
            default => 'd90plus',
        };
    }

    protected function paymentsQuery(Request $request): Builder
    {
        $query = InvoicePayment::query();

        $this->applyTableSearch($query, $request, [
            'invoice.invoice_number',
            'invoice.client.name',
            'payment_reference',
            'tracking_number',
        ]);
        $this->applyTableDateRange($query, $request, 'invoice_payments.payment_date');

        if ($request->filled('payment_method')) {
            $query->where('invoice_payments.payment_method', $request->input('payment_method'));
        }
        if ($request->filled('payment_source')) {
            $query->where('invoice_payments.source', $request->input('payment_source'));
        }
        if ($request->input('realization') === 'realized') {
            $query->whereDate('invoice_payments.payment_date', '<=', Carbon::today()->toDateString());
        } elseif ($request->input('realization') === 'scheduled') {
            $query->whereDate('invoice_payments.payment_date', '>', Carbon::today()->toDateString());
        }

        return $query;
    }
}
