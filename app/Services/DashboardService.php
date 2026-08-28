<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\FinancialMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentImport;
use App\Models\PaymentImportLine;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Agrégats du tableau de bord.
 *
 * Règles de calcul (voir README du module) :
 *  - CA = ventes POS terminées + factures indépendantes (pos_sale_id null).
 *    Les factures issues d'un ticket POS sont exclues pour éviter le double comptage.
 *  - Encaissements réels = paiements de factures (InvoicePayment) + ventes POS non facturées.
 *  - Décaissements = paiements fournisseurs + dépenses.
 *  - Trésorerie = journal FinancialMovement hors brouillon.
 *  - Créances / dettes = total document − paiements (les partiels sont donc inclus).
 */
class DashboardService
{
    public const LIST_LIMIT = 5;

    /**
     * Modes de paiement canoniques (POS + paiements de factures confondus).
     */
    private const PAYMENT_BUCKETS = [
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'cheque' => 'Chèque',
        'transfer' => 'Virement bancaire',
        'other' => 'Autre',
    ];

    /**
     * Chiffre d'affaires de la période, sans double comptage.
     *
     * @return array{total: float, pos: float, invoices: float}
     */
    public function getRevenue(Carbon $dateFrom, Carbon $dateTo): array
    {
        $pos = (float) $this->posSalesInPeriod($dateFrom, $dateTo)->sum('total');

        $invoices = (float) $this->independentInvoices()
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'invoice_date', $dateFrom, $dateTo))
            ->sum('total');

        return [
            'pos' => round($pos, 2),
            'invoices' => round($invoices, 2),
            'total' => round($pos + $invoices, 2),
        ];
    }

    /**
     * Encaissements réellement perçus sur la période.
     */
    public function getCashIn(Carbon $dateFrom, Carbon $dateTo): float
    {
        $invoicePayments = (float) InvoicePayment::query()
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'payment_date', $dateFrom, $dateTo))
            ->sum('amount');

        $posWithoutInvoice = (float) $this->posSalesInPeriod($dateFrom, $dateTo)
            ->whereDoesntHave('invoice')
            ->sum('total');

        return round($invoicePayments + $posWithoutInvoice, 2);
    }

    /**
     * Décaissements de la période : paiements fournisseurs + dépenses.
     */
    public function getCashOut(Carbon $dateFrom, Carbon $dateTo): float
    {
        return round(
            $this->getSupplierPayments($dateFrom, $dateTo) + $this->getExpenses($dateFrom, $dateTo),
            2
        );
    }

    public function getSupplierPayments(Carbon $dateFrom, Carbon $dateTo): float
    {
        return round(
            (float) SupplierInvoicePayment::query()
                ->where(function ($q) {
                    $q->where('is_cash_movement', true)->orWhereNull('is_cash_movement');
                })
                ->tap(fn (Builder $q) => $this->betweenDates($q, 'payment_date', $dateFrom, $dateTo))
                ->sum('amount')
            + (float) \App\Models\SupplierPayment::query()
                ->tap(fn (Builder $q) => $this->betweenDates($q, 'payment_date', $dateFrom, $dateTo))
                ->sum('unallocated_amount'),
            2
        );
    }

    public function getExpenses(Carbon $dateFrom, Carbon $dateTo): float
    {
        return round((float) Expense::query()
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'expense_date', $dateFrom, $dateTo))
            ->sum('amount'), 2);
    }

    public function getPurchases(Carbon $dateFrom, Carbon $dateTo): float
    {
        return round((float) SupplierInvoice::query()
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'invoice_date', $dateFrom, $dateTo))
            ->sum('total'), 2);
    }

    /**
     * Résultat estimé = CA − achats − dépenses.
     */
    public function getResult(Carbon $dateFrom, Carbon $dateTo): float
    {
        return round(
            $this->getRevenue($dateFrom, $dateTo)['total']
            - $this->getPurchases($dateFrom, $dateTo)
            - $this->getExpenses($dateFrom, $dateTo),
            2
        );
    }

    /**
     * Les 8 indicateurs financiers cliquables, avec variation vs période précédente.
     *
     * @return list<array<string, mixed>>
     */
    public function getKpis(Carbon $dateFrom, Carbon $dateTo): array
    {
        [$prevFrom, $prevTo] = $this->previousPeriod($dateFrom, $dateTo);

        $range = $this->rangeParams($dateFrom, $dateTo);

        $current = $this->kpiValues($dateFrom, $dateTo);
        $previous = $this->kpiValues($prevFrom, $prevTo);

        $receivables = $this->getReceivables(0);
        $payables = $this->getPayables(0);
        $treasury = $this->getTreasury($dateFrom, $dateTo);
        $openOrders = $this->openOrders();

        $definitions = [
            [
                'key' => 'revenue',
                'label' => 'Chiffre d\'affaires',
                'hint' => 'Ventes POS + factures directes',
                'format' => 'money',
                'tone' => 'sky',
                'url' => route('invoices.index', $range),
            ],
            [
                'key' => 'cash_in',
                'label' => 'Encaissements',
                'hint' => 'Paiements reçus + POS non facturé',
                'format' => 'money',
                'tone' => 'emerald',
                'url' => route('sales.payments.index', $range),
            ],
            [
                'key' => 'expenses',
                'label' => 'Dépenses',
                'hint' => 'Avec et sans facture',
                'format' => 'money',
                'tone' => 'amber',
                'url' => route('expenses.index', $range),
            ],
            [
                'key' => 'result',
                'label' => 'Résultat / Marge',
                'hint' => 'CA − achats − dépenses',
                'format' => 'money',
                'tone' => 'violet',
                'url' => route('financial.index', $range),
            ],
            [
                'key' => 'treasury',
                'label' => 'Trésorerie disponible',
                'hint' => 'Caisse + banque + autres comptes',
                'format' => 'money',
                'tone' => 'emerald',
                'url' => $treasury['urls']['overview'],
            ],
            [
                'key' => 'receivables',
                'label' => 'Créances clients',
                'hint' => $receivables['count'].' facture(s) non soldée(s)',
                'format' => 'money',
                'tone' => 'orange',
                'url' => route('sales.payments.index', ['payment_status' => 'open']),
            ],
            [
                'key' => 'payables',
                'label' => 'Dettes fournisseurs',
                'hint' => $payables['count'].' facture(s) non soldée(s)',
                'format' => 'money',
                'tone' => 'slate',
                'url' => route('purchases.payments.index', ['payment_status' => 'open']),
            ],
            [
                'key' => 'open_orders',
                'label' => 'Commandes en cours',
                'hint' => 'Montant total des commandes ouvertes',
                'format' => 'count',
                'secondary' => $openOrders['total'],
                'tone' => 'indigo',
                'url' => route('orders.index'),
            ],
        ];

        $current['receivables'] = $receivables['total'];
        $current['payables'] = $payables['total'];
        $current['treasury'] = $treasury['total'];
        $current['open_orders'] = $openOrders['count'];
        // Les soldes ouverts sont un état instantané : pas de comparatif de période.
        $previous['receivables'] = null;
        $previous['payables'] = null;
        $previous['treasury'] = null;
        $previous['open_orders'] = null;

        return array_map(function (array $definition) use ($current, $previous) {
            $value = (float) ($current[$definition['key']] ?? 0);
            $before = $previous[$definition['key']] ?? null;

            return $definition + [
                'value' => $value,
                'previous' => $before,
                'variation' => $before === null ? null : $this->variation($value, (float) $before),
            ];
        }, $definitions);
    }

    /**
     * @return array<string, float>
     */
    private function kpiValues(Carbon $dateFrom, Carbon $dateTo): array
    {
        $revenue = $this->getRevenue($dateFrom, $dateTo)['total'];
        $purchases = $this->getPurchases($dateFrom, $dateTo);
        $expenses = $this->getExpenses($dateFrom, $dateTo);

        return [
            'revenue' => $revenue,
            'cash_in' => $this->getCashIn($dateFrom, $dateTo),
            'expenses' => $expenses,
            'result' => round($revenue - $purchases - $expenses, 2),
        ];
    }

    /**
     * Commandes qui ne sont ni terminées ni annulées.
     *
     * @return array{count: int, total: float}
     */
    private function openOrders(): array
    {
        $query = PosSale::query()
            ->where(function (Builder $q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', [PosSale::STATUS_COMPLETED, PosSale::STATUS_CANCELLED]);
            });

        return [
            'count' => (clone $query)->count(),
            'total' => round((float) $query->sum('total'), 2),
        ];
    }

    /**
     * Files d'attente opérationnelles du jour.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function getTodo(): array
    {
        $today = Carbon::today();

        $overdueClients = $this->openBalances(
            Invoice::query()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today->toDateString())
        );

        $duePayables = $this->openBalances(SupplierInvoice::query());

        $unfulfilled = PosSale::query()->where('fulfillment_status', 'unfulfilled')->count();
        $partiallyFulfilled = PosSale::query()->where('fulfillment_status', 'partial')->count();

        $lowStock = Product::lowStock()->count();
        $outOfStock = Product::outOfStock()->count();

        $unreconciled = $this->unreconciledImportLines();

        $items = [
            [
                'key' => 'overdue_invoices',
                'label' => 'Factures clients échues',
                'count' => $overdueClients['count'],
                'amount' => $overdueClients['total'],
                'tone' => 'rose',
                'url' => route('sales.payments.index', ['payment_status' => 'open']),
            ],
            [
                'key' => 'supplier_due',
                'label' => 'Fournisseurs à payer',
                'count' => $duePayables['count'],
                'amount' => $duePayables['total'],
                'tone' => 'orange',
                'url' => route('purchases.payments.index', ['payment_status' => 'open']),
            ],
            [
                'key' => 'unfulfilled_orders',
                'label' => 'Commandes non traitées',
                'count' => $unfulfilled,
                'amount' => null,
                'tone' => 'sky',
                'url' => route('orders.index', ['fulfillment_status' => 'unfulfilled']),
            ],
            [
                'key' => 'partial_fulfillment',
                'label' => 'Livraisons en attente (partielles)',
                'count' => $partiallyFulfilled,
                'amount' => null,
                'tone' => 'indigo',
                'url' => route('orders.index', ['fulfillment_status' => 'partial']),
            ],
            [
                'key' => 'low_stock',
                'label' => 'Produits en stock faible',
                'count' => $lowStock,
                'amount' => null,
                'tone' => 'amber',
                'url' => route('products.index', ['stock_status' => Product::STOCK_STATUS_LOW]),
            ],
            [
                'key' => 'out_of_stock',
                'label' => 'Produits en rupture',
                'count' => $outOfStock,
                'amount' => null,
                'tone' => 'rose',
                'url' => route('products.index', ['stock_status' => Product::STOCK_STATUS_OUT]),
            ],
            [
                'key' => 'unreconciled_payments',
                'label' => 'Lignes d\'import non rapprochées',
                'count' => $unreconciled['count'],
                'amount' => null,
                'tone' => 'violet',
                'url' => $unreconciled['url'],
            ],
            [
                'key' => 'expenses_to_validate',
                'label' => 'Dépenses à valider',
                'count' => 0,
                'amount' => null,
                'tone' => 'slate',
                'note' => 'Non suivi : les dépenses n\'ont pas de statut de validation.',
                'url' => route('expenses.index'),
            ],
        ];

        return [
            'items' => $items,
            'total' => array_sum(array_column($items, 'count')),
        ];
    }

    /**
     * Série mensuelle CA / encaissements / dépenses / résultat.
     *
     * @param  '6'|'12'|'year'|string  $period
     * @return array{labels: list<string>, revenue: list<float>, cash_in: list<float>, expenses: list<float>, result: list<float>, period: string}
     */
    public function getChart(string $period = '6', ?Carbon $reference = null): array
    {
        $reference ??= Carbon::now();
        $period = in_array($period, ['6', '12', 'year'], true) ? $period : '6';

        if ($period === 'year') {
            $cursor = $reference->copy()->startOfYear();
            $end = $reference->copy()->endOfYear();
        } else {
            $months = (int) $period;
            $cursor = $reference->copy()->startOfMonth()->subMonths($months - 1);
            $end = $reference->copy()->endOfMonth();
        }

        $labels = [];
        $revenue = [];
        $cashIn = [];
        $expenses = [];
        $result = [];

        while ($cursor->lte($end)) {
            $start = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $labels[] = $cursor->translatedFormat('M Y');

            $monthRevenue = $this->getRevenue($start, $monthEnd)['total'];
            $monthExpenses = $this->getExpenses($start, $monthEnd);
            $monthPurchases = $this->getPurchases($start, $monthEnd);

            $revenue[] = $monthRevenue;
            $cashIn[] = $this->getCashIn($start, $monthEnd);
            $expenses[] = $monthExpenses;
            $result[] = round($monthRevenue - $monthPurchases - $monthExpenses, 2);

            $cursor->addMonth();
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'cash_in' => $cashIn,
            'expenses' => $expenses,
            'result' => $result,
            'period' => $period,
        ];
    }

    /**
     * Répartition du CA par canal (montant + part).
     *
     * @return array{total: float, items: list<array<string, mixed>>}
     */
    public function getChannels(Carbon $dateFrom, Carbon $dateTo): array
    {
        $range = $this->rangeParams($dateFrom, $dateTo);

        $shopify = (float) $this->posSalesInPeriod($dateFrom, $dateTo)->where('source', 'shopify')->sum('total');
        $jumia = (float) $this->posSalesInPeriod($dateFrom, $dateTo)->where('source', 'jumia')->sum('total');
        $pos = (float) $this->posSalesInPeriod($dateFrom, $dateTo)
            ->where(function (Builder $q) {
                $q->whereNull('source')->orWhereNotIn('source', ['shopify', 'jumia']);
            })
            ->sum('total');
        $direct = (float) $this->independentInvoices()
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'invoice_date', $dateFrom, $dateTo))
            ->sum('total');

        $rows = [
            ['key' => 'shopify', 'label' => 'Shopify', 'amount' => round($shopify, 2), 'url' => route('orders.index', $range + ['source' => 'shopify'])],
            ['key' => 'pos', 'label' => 'POS / Manuel', 'amount' => round($pos, 2), 'url' => route('orders.index', $range + ['source' => 'pos'])],
            ['key' => 'jumia', 'label' => 'Jumia', 'amount' => round($jumia, 2), 'url' => route('orders.index', $range + ['source' => 'jumia'])],
            ['key' => 'direct', 'label' => 'Facturation directe', 'amount' => round($direct, 2), 'url' => route('invoices.index', $range)],
        ];

        return $this->withShares($rows);
    }

    /**
     * Modes de paiement globaux : InvoicePayment + ventes POS non facturées.
     *
     * @return array{total: float, items: list<array<string, mixed>>}
     */
    public function getPaymentMethods(Carbon $dateFrom, Carbon $dateTo): array
    {
        $buckets = array_fill_keys(array_keys(self::PAYMENT_BUCKETS), 0.0);
        $counts = array_fill_keys(array_keys(self::PAYMENT_BUCKETS), 0);

        InvoicePayment::query()
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'payment_date', $dateFrom, $dateTo))
            ->get(['amount', 'payment_method'])
            ->each(function (InvoicePayment $payment) use (&$buckets, &$counts) {
                $bucket = $this->paymentBucket($payment->payment_method);
                $buckets[$bucket] += (float) $payment->amount;
                $counts[$bucket]++;
            });

        $this->posSalesInPeriod($dateFrom, $dateTo)
            ->whereDoesntHave('invoice')
            ->get(['total', 'payment_method'])
            ->each(function (PosSale $sale) use (&$buckets, &$counts) {
                $bucket = $this->paymentBucket($sale->payment_method);
                $buckets[$bucket] += (float) $sale->total;
                $counts[$bucket]++;
            });

        $rows = [];
        foreach (self::PAYMENT_BUCKETS as $key => $label) {
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'amount' => round($buckets[$key], 2),
                'count' => $counts[$key],
                'url' => route('sales.payments.index', $this->rangeParams($dateFrom, $dateTo)),
            ];
        }

        return $this->withShares($rows);
    }

    /**
     * Activité commerciale de la période.
     *
     * @return array{items: list<array<string, mixed>>, average_basket: float, orders_url: string}
     */
    public function getCommercialActivity(Carbon $dateFrom, Carbon $dateTo): array
    {
        $range = $this->rangeParams($dateFrom, $dateTo);

        $ordersToday = PosSale::query()->whereDate('sold_at', Carbon::today()->toDateString())->count();
        $orders = $this->posSalesInPeriod($dateFrom, $dateTo, false)->count();
        $completedOrders = $this->posSalesInPeriod($dateFrom, $dateTo, false)
            ->where('status', PosSale::STATUS_COMPLETED)
            ->count();
        $cancelledOrders = $this->posSalesInPeriod($dateFrom, $dateTo, false)
            ->where('status', PosSale::STATUS_CANCELLED)
            ->count();
        $fulfilledOrders = $this->posSalesInPeriod($dateFrom, $dateTo, false)
            ->where('fulfillment_status', 'fulfilled')
            ->count();
        $invoiceCount = Invoice::query()
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'invoice_date', $dateFrom, $dateTo))
            ->count();

        $creditNotes = CreditNote::query()
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'credit_note_date', $dateFrom, $dateTo))
            ->count();

        $revenue = $this->getRevenue($dateFrom, $dateTo);
        $transactions = $completedOrders + Invoice::query()
            ->whereNull('pos_sale_id')
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'invoice_date', $dateFrom, $dateTo))
            ->count();
        $averageBasket = $transactions > 0 ? round($revenue['total'] / $transactions, 2) : 0.0;
        $deliveryRate = $orders > 0 ? round(($fulfilledOrders / $orders) * 100, 1) : 0.0;
        $returnRate = $invoiceCount > 0 ? round(($creditNotes / $invoiceCount) * 100, 1) : 0.0;

        $items = [
            ['key' => 'orders_today', 'label' => 'Commandes aujourd\'hui', 'value' => $ordersToday, 'format' => 'count', 'url' => route('orders.index')],
            ['key' => 'orders_period', 'label' => 'Commandes période', 'value' => $orders, 'format' => 'count', 'url' => route('orders.index', $range)],
            ['key' => 'orders_completed', 'label' => 'Terminées', 'value' => $completedOrders, 'format' => 'count', 'url' => route('orders.index', $range + ['status' => PosSale::STATUS_COMPLETED])],
            ['key' => 'orders_cancelled', 'label' => 'Annulées', 'value' => $cancelledOrders, 'format' => 'count', 'url' => route('orders.index', $range + ['status' => PosSale::STATUS_CANCELLED])],
            ['key' => 'revenue', 'label' => 'Chiffre d\'affaires', 'value' => $revenue['total'], 'format' => 'money', 'url' => route('invoices.index', $range)],
            ['key' => 'average_basket', 'label' => 'Panier moyen', 'value' => $averageBasket, 'format' => 'money', 'url' => route('orders.index', $range)],
            ['key' => 'delivery_rate', 'label' => 'Taux de livraison', 'value' => $deliveryRate, 'format' => 'percent', 'url' => route('orders.index', $range + ['fulfillment_status' => 'fulfilled'])],
            ['key' => 'return_rate', 'label' => 'Taux de retour', 'value' => $returnRate, 'format' => 'percent', 'url' => route('credit-notes.index', $range)],
        ];

        return [
            'items' => $items,
            'average_basket' => $averageBasket,
            'orders_url' => route('orders.index', $range),
        ];
    }

    /**
     * Stock & produits + top réapprovisionnement.
     *
     * @return array{
     *     total: int,
     *     in_stock: int,
     *     low_stock: int,
     *     out_of_stock: int,
     *     stock_value: float,
     *     urls: array<string, string>,
     *     restock: list<array<string, mixed>>
     * }
     */
    public function getStockOverview(int $limit = self::LIST_LIMIT): array
    {
        $available = Product::availableStockSql();
        $threshold = Product::alertThresholdSql();

        $stockValue = (float) Product::query()
            ->tracksStock()
            ->selectRaw('COALESCE(SUM(COALESCE(stock_quantity, 0) * COALESCE(cost_price_ht, 0)), 0) as value')
            ->value('value');

        $restock = Product::query()
            ->with('primarySupplier')
            ->tracksStock()
            ->whereRaw("{$available} <= {$threshold}")
            ->orderByRaw("CASE WHEN {$available} <= 0 THEN 0 ELSE 1 END")
            ->orderByRaw("({$threshold} - {$available}) desc")
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'ref' => $product->ref,
                'available' => $product->availableStock(),
                'threshold' => $product->alertThreshold(),
                'status' => $product->stockStatus(),
                'status_label' => $product->stock_status_label,
                'primary_supplier' => $product->primarySupplier?->name,
                'url' => route('products.show', $product),
            ])
            ->all();

        return [
            'total' => Product::query()->count(),
            'stocked' => Product::query()->stocked()->count(),
            'non_stocked' => Product::query()->nonStocked()->count(),
            'services' => Product::query()->services()->count(),
            'in_stock' => Product::inStock()->count(),
            'low_stock' => Product::lowStock()->count(),
            'out_of_stock' => Product::outOfStock()->count(),
            'stock_value' => round($stockValue, 2),
            'urls' => [
                'all' => route('products.index'),
                'stocked' => route('products.index', ['item_kind' => Product::KIND_STOCKED]),
                'non_stocked' => route('products.index', ['item_kind' => Product::KIND_NON_STOCKED]),
                'services' => route('products.index', ['item_kind' => Product::KIND_SERVICE]),
                'in_stock' => route('products.index', ['stock_status' => Product::STOCK_STATUS_IN_STOCK]),
                'low_stock' => route('products.index', ['stock_status' => Product::STOCK_STATUS_LOW]),
                'out_of_stock' => route('products.index', ['stock_status' => Product::STOCK_STATUS_OUT]),
                'stock' => route('stock.index'),
            ],
            'restock' => $restock,
        ];
    }

    /**
     * Trésorerie depuis le journal des mouvements (hors brouillon).
     *
     * @return array<string, mixed>
     */
    public function getTreasury(Carbon $dateFrom, Carbon $dateTo): array
    {
        $balances = ['caisse' => 0.0, 'banque' => 0.0, 'other' => 0.0];
        $in = 0.0;
        $out = 0.0;
        $available = Schema::hasTable('financial_movements');

        if ($available) {
            $asOf = $dateTo->toDateString();

            foreach (array_keys($balances) as $account) {
                $accountQuery = fn () => FinancialMovement::query()
                    ->where('status', '!=', FinancialMovement::STATUS_BROUILLON)
                    ->where('account', $account)
                    ->whereDate('movement_date', '<=', $asOf);

                $balances[$account] = round(
                    (float) $accountQuery()->sum('amount_in') - (float) $accountQuery()->sum('amount_out'),
                    2
                );
            }

            $periodQuery = fn () => FinancialMovement::query()
                ->where('status', '!=', FinancialMovement::STATUS_BROUILLON)
                ->whereDate('movement_date', '>=', $dateFrom->toDateString())
                ->whereDate('movement_date', '<=', $asOf);

            $in = round((float) $periodQuery()->sum('amount_in'), 2);
            $out = round((float) $periodQuery()->sum('amount_out'), 2);
        }

        $range = $this->rangeParams($dateFrom, $dateTo);

        return [
            'available' => $available,
            'caisse' => $balances['caisse'],
            'banque' => $balances['banque'],
            'other' => $balances['other'],
            'total' => round(array_sum($balances), 2),
            'in' => $in,
            'out' => $out,
            'net' => round($in - $out, 2),
            'urls' => [
                'overview' => route('financial.tresorerie', $range),
                'caisse' => route('financial.mouvements.index', $range + ['account' => FinancialMovement::ACCOUNT_CAISSE]),
                'banque' => route('financial.mouvements.index', $range + ['account' => FinancialMovement::ACCOUNT_BANQUE]),
                'in' => route('financial.mouvements.index', $range + ['type' => FinancialMovement::TYPE_ENTREE]),
                'out' => route('financial.mouvements.index', $range + ['type' => FinancialMovement::TYPE_SORTIE]),
            ],
        ];
    }

    /**
     * Créances clients : total − paiements (partiels inclus).
     *
     * @return array{count: int, total: float, items: list<array<string, mixed>>, url: string}
     */
    public function getReceivables(int $limit = self::LIST_LIMIT): array
    {
        $rows = Invoice::query()
            ->with('client')
            ->withSum('payments as payments_sum', 'amount')
            ->orderByDesc('invoice_date')
            ->get()
            ->map(function (Invoice $invoice) {
                $total = (float) $invoice->total;
                $paid = (float) ($invoice->payments_sum ?? 0);

                return [
                    'number' => $invoice->invoice_number,
                    'party' => $invoice->client?->name ?? '—',
                    'total' => round($total, 2),
                    'paid' => round($paid, 2),
                    'remaining' => round(max(0, $total - $paid), 2),
                    'status' => $this->balanceStatus($paid, $total),
                    'date' => $invoice->invoice_date?->format('d/m/Y'),
                    'due_date' => $invoice->due_date?->format('d/m/Y'),
                    'overdue' => $invoice->due_date !== null && $invoice->due_date->lt(Carbon::today()),
                    'url' => route('invoices.payments.index', $invoice),
                ];
            })
            ->filter(fn (array $row) => $row['remaining'] > 0.009)
            ->sortBy([
                ['overdue', 'desc'],
                ['remaining', 'desc'],
                ['due_date', 'asc'],
            ])
            ->values();

        return [
            'count' => $rows->count(),
            'total' => round($rows->sum('remaining'), 2),
            'items' => $limit > 0 ? $rows->take($limit)->all() : [],
            'url' => route('sales.payments.index', ['payment_status' => 'open']),
        ];
    }

    /**
     * Dettes fournisseurs : total − paiements (partiels inclus).
     *
     * @return array{count: int, total: float, items: list<array<string, mixed>>, url: string}
     */
    public function getPayables(int $limit = self::LIST_LIMIT): array
    {
        $rows = SupplierInvoice::query()
            ->with('supplier')
            ->withSum('payments as payments_sum', 'amount')
            ->withSum('creditNoteAllocations as credit_note_allocations_sum_amount', 'amount')
            ->orderByDesc('invoice_date')
            ->get()
            ->map(function (SupplierInvoice $invoice) {
                $total = (float) $invoice->total;
                $paid = (float) ($invoice->payments_sum ?? 0);
                $credits = (float) ($invoice->credit_note_allocations_sum_amount ?? 0);
                $remaining = round(max(0, $total - $paid - $credits), 2);

                return [
                    'number' => $invoice->invoice_number,
                    'party' => $invoice->supplier?->name ?? '—',
                    'total' => round($total, 2),
                    'paid' => round($paid + $credits, 2),
                    'remaining' => $remaining,
                    'status' => $this->balanceStatus($paid, $total),
                    'date' => $invoice->invoice_date?->format('d/m/Y'),
                    'due_date' => $invoice->due_date?->format('d/m/Y'),
                    'overdue' => $invoice->due_date !== null && $invoice->due_date->lt(Carbon::today()),
                    'url' => route('supplier-invoices.payments.index', $invoice),
                ];
            })
            ->filter(fn (array $row) => $row['remaining'] > 0.009)
            ->sortBy([
                ['overdue', 'desc'],
                ['remaining', 'desc'],
                ['due_date', 'asc'],
            ])
            ->values();

        return [
            'count' => $rows->count(),
            'total' => round($rows->sum('remaining'), 2),
            'items' => $limit > 0 ? $rows->take($limit)->all() : [],
            'url' => route('purchases.payments.index', ['payment_status' => 'open']),
        ];
    }

    /**
     * Quatre listes « dernières opérations », limitées à 5 lignes.
     *
     * @return array<string, array{items: list<array<string, mixed>>, url: string}>
     */
    public function getRecentOperations(Carbon $dateFrom, Carbon $dateTo, int $limit = self::LIST_LIMIT): array
    {
        $range = $this->rangeParams($dateFrom, $dateTo);

        $orders = $this->posSalesInPeriod($dateFrom, $dateTo, false)
            ->with('client')
            ->orderByDesc('sold_at')
            ->limit($limit)
            ->get()
            ->map(fn (PosSale $sale) => [
                'reference' => $sale->ticket_number,
                'party' => $sale->client?->name ?? 'Comptoir',
                'amount' => (float) $sale->total,
                'date' => $sale->sold_at?->format('d/m/Y H:i'),
                'meta' => $sale->source ?: 'pos',
                'url' => route('orders.show', $sale),
            ])
            ->all();

        $invoices = Invoice::query()
            ->with('client')
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'invoice_date', $dateFrom, $dateTo))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'reference' => $invoice->invoice_number,
                'party' => $invoice->client?->name ?? '—',
                'amount' => (float) $invoice->total,
                'date' => $invoice->invoice_date?->format('d/m/Y'),
                'meta' => $invoice->payment_status,
                'url' => route('invoices.show', $invoice),
            ])
            ->all();

        $payments = InvoicePayment::query()
            ->with('invoice.client')
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'payment_date', $dateFrom, $dateTo))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (InvoicePayment $payment) => [
                'reference' => $payment->invoice?->invoice_number ?? '—',
                'party' => $payment->invoice?->client?->name ?? '—',
                'amount' => (float) $payment->amount,
                'date' => $payment->payment_date?->format('d/m/Y'),
                'meta' => $payment->payment_method,
                'url' => $payment->invoice
                    ? route('invoices.payments.index', $payment->invoice)
                    : route('sales.payments.index'),
            ])
            ->all();

        $movements = FinancialMovement::query()
            ->where('status', '!=', FinancialMovement::STATUS_BROUILLON)
            ->tap(fn (Builder $q) => $this->betweenDates($q, 'movement_date', $dateFrom, $dateTo))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (FinancialMovement $movement) => [
                'reference' => $movement->reference ?: $movement->label,
                'party' => FinancialMovement::accountLabels()[$movement->account] ?? $movement->account,
                'amount' => $movement->netAmount(),
                'date' => $movement->movement_date?->format('d/m/Y'),
                'meta' => FinancialMovement::typeLabels()[$movement->type] ?? $movement->type,
                'url' => route('financial.mouvements.index', ['movement' => $movement->id]),
            ])
            ->all();

        return [
            'orders' => ['items' => $orders, 'url' => route('orders.index', $range)],
            'invoices' => ['items' => $invoices, 'url' => route('invoices.index', $range)],
            'payments' => ['items' => $payments, 'url' => route('sales.payments.index', $range)],
            'movements' => ['items' => $movements, 'url' => route('financial.mouvements.index', $range)],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function previousPeriod(Carbon $dateFrom, Carbon $dateTo): array
    {
        $days = max(1, $dateFrom->copy()->startOfDay()->diffInDays($dateTo->copy()->startOfDay()) + 1);

        return [
            $dateFrom->copy()->subDays($days)->startOfDay(),
            $dateFrom->copy()->subDay()->endOfDay(),
        ];
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    public function rangeParams(Carbon $dateFrom, Carbon $dateTo): array
    {
        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
        ];
    }

    /**
     * Ventes POS de la période (terminées par défaut).
     */
    private function posSalesInPeriod(Carbon $dateFrom, Carbon $dateTo, bool $completedOnly = true): Builder
    {
        $query = PosSale::query()->whereBetween('sold_at', [$dateFrom, $dateTo]);

        return $completedOnly
            ? $query->where('status', PosSale::STATUS_COMPLETED)
            : $query;
    }

    /**
     * Factures non issues d'un ticket POS (évite le double comptage du CA).
     */
    private function independentInvoices(): Builder
    {
        return Invoice::query()->whereNull('pos_sale_id');
    }

    /**
     * Comparaison de colonnes DATE portable SQLite / MySQL.
     */
    private function betweenDates(Builder $query, string $column, Carbon $dateFrom, Carbon $dateTo): Builder
    {
        return $query
            ->whereDate($column, '>=', $dateFrom->toDateString())
            ->whereDate($column, '<=', $dateTo->toDateString());
    }

    /**
     * Solde restant dû (total − paiements) agrégé sur une requête de factures.
     *
     * @return array{count: int, total: float}
     */
    private function openBalances(Builder $query): array
    {
        $query->withSum('payments as payments_sum', 'amount');

        if ($query->getModel() instanceof SupplierInvoice) {
            $query->withSum('creditNoteAllocations as credit_note_allocations_sum_amount', 'amount');
        }

        $rows = $query
            ->get()
            ->map(fn ($invoice) => max(
                0,
                (float) $invoice->total
                - (float) ($invoice->payments_sum ?? 0)
                - (float) ($invoice->credit_note_allocations_sum_amount ?? 0)
            ))
            ->filter(fn (float $remaining) => $remaining > 0.009);

        return [
            'count' => $rows->count(),
            'total' => round($rows->sum(), 2),
        ];
    }

    /**
     * @return array{count: int, url: string}
     */
    private function unreconciledImportLines(): array
    {
        if (! Schema::hasTable('payment_import_rows')) {
            return ['count' => 0, 'url' => route('sales.payments.import')];
        }

        $query = PaymentImportLine::query()
            ->where('exclude', false)
            ->whereIn('match_status', [
                PaymentImportLine::MATCH_UNMATCHED,
                PaymentImportLine::MATCH_AMBIGUOUS,
            ]);

        $count = (clone $query)->count();

        $import = PaymentImport::query()
            ->where('status', PaymentImport::STATUS_DRAFT)
            ->whereIn('id', (clone $query)->select('payment_import_id'))
            ->latest('id')
            ->first();

        $url = match (true) {
            $import === null => route('sales.payments.import'),
            $import->scope === PaymentImport::SCOPE_PURCHASES => route('purchases.payments.import.show', $import),
            default => route('sales.payments.import.show', $import),
        };

        return ['count' => $count, 'url' => $url];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{total: float, items: list<array<string, mixed>>}
     */
    private function withShares(array $rows): array
    {
        $total = round(array_sum(array_column($rows, 'amount')), 2);

        $items = array_values(array_map(function (array $row) use ($total) {
            $row['share'] = $total > 0 ? round(((float) $row['amount'] / $total) * 100, 1) : 0.0;

            return $row;
        }, $rows));

        return ['total' => $total, 'items' => $items];
    }

    private function paymentBucket(?string $method): string
    {
        $method = mb_strtolower(trim((string) $method));

        return match (true) {
            $method === '' => 'other',
            $method === PosSale::PAYMENT_CASH,
            str_contains($method, 'esp'),
            str_contains($method, 'cash'),
            str_contains($method, 'caisse') => 'cash',
            $method === PosSale::PAYMENT_CARD,
            str_contains($method, 'carte'),
            str_contains($method, 'card') => 'card',
            $method === PosSale::PAYMENT_CHEQUE,
            str_contains($method, 'chèque'),
            str_contains($method, 'cheque'),
            str_contains($method, 'check') => 'cheque',
            $method === PosSale::PAYMENT_TRANSFER,
            str_contains($method, 'vir'),
            str_contains($method, 'transfer') => 'transfer',
            default => 'other',
        };
    }

    private function balanceStatus(float $paid, float $total): string
    {
        if ($paid <= 0.009) {
            return 'unpaid';
        }

        return $paid + 0.009 >= $total ? 'paid' : 'partial';
    }

    private function variation(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.009) {
            return null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}
