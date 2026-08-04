<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoicePayment;
use App\Support\DocumentTaxBreakdown;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinancialManagementService
{
    /**
     * @return array<string, mixed>
     */
    public function getOverview(?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $dateFrom ??= Carbon::now()->startOfMonth();
        $dateTo ??= Carbon::now()->endOfDay();

        $sales = $this->getSalesBreakdown($dateFrom, $dateTo);
        $purchases = $this->getPurchasesBreakdown($dateFrom, $dateTo);
        $vat = $this->getVatBreakdown($dateFrom, $dateTo);
        $cash = $this->getCashMovements($dateFrom, $dateTo);
        $treasury = $this->getTreasuryBalances();
        $paymentStatuses = $this->getPaymentStatusSummary();

        $expensesTotal = (float) Expense::query()
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $expensesWithInvoice = (float) Expense::query()
            ->where('expense_type', 'with_invoice')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $expensesWithoutInvoice = (float) Expense::query()
            ->where('expense_type', 'without_invoice')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $estimatedResult = $sales['revenue'] - $purchases['purchases'] - $expensesTotal;

        return [
            'revenue' => $sales['revenue'],
            'revenue_pos' => $sales['pos'],
            'revenue_invoices' => $sales['invoices'],
            'revenue_credit_notes' => $sales['credit_notes'],
            'purchases' => $purchases['purchases'],
            'supplier_purchases' => $purchases['supplier_invoices'],
            'supplier_credit_notes' => $purchases['credit_notes'],
            'expenses' => $expensesTotal,
            'expenses_with_invoice' => $expensesWithInvoice,
            'expenses_without_invoice' => $expensesWithoutInvoice,
            'estimated_result' => $estimatedResult,
            'vat_collected' => $vat['collected'],
            'vat_deductible' => $vat['deductible'],
            'vat_net' => $vat['net'],
            'vat_details' => $vat,
            'client_payments' => $cash['encaissements'],
            'supplier_payments' => $cash['payments_fournisseurs'],
            'decaissements' => $cash['decaissements'],
            'net_cash_flow' => $cash['net'],
            'cash_in_pos' => $cash['pos'],
            'cash_in_invoices' => $cash['payments_clients'],
            'treasury_total' => $treasury['total'],
            'treasury_caisse' => $treasury['caisse'],
            'treasury_banque' => $treasury['banque'],
            'treasury_other' => $treasury['other'],
            'client_receivables' => $paymentStatuses['clients']['remaining_total'],
            'supplier_payables' => $paymentStatuses['suppliers']['remaining_total'],
            'payment_statuses' => $paymentStatuses,
            'has_delivery_drivers' => false,
            'delivery_drivers_cash' => [],
        ];
    }

    /**
     * CA = factures période + ventes POS sans facture liée − avoirs clients.
     *
     * @return array{revenue: float, pos: float, invoices: float, credit_notes: float}
     */
    public function getSalesBreakdown(Carbon $dateFrom, Carbon $dateTo): array
    {
        $invoiceRevenue = (float) Invoice::query()
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->sum('total');

        $posStandalone = (float) PosSale::query()
            ->where('status', PosSale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->whereDoesntHave('invoice')
            ->sum('total');

        $creditNotes = (float) CreditNote::query()
            ->whereBetween('credit_note_date', [$dateFrom, $dateTo])
            ->sum('total');

        return [
            'invoices' => round($invoiceRevenue, 2),
            'pos' => round($posStandalone, 2),
            'credit_notes' => round($creditNotes, 2),
            'revenue' => round($invoiceRevenue + $posStandalone - $creditNotes, 2),
        ];
    }

    /**
     * @return array{purchases: float, supplier_invoices: float, credit_notes: float}
     */
    public function getPurchasesBreakdown(Carbon $dateFrom, Carbon $dateTo): array
    {
        $supplierInvoices = (float) SupplierInvoice::query()
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->sum('total');

        $creditNotes = (float) SupplierCreditNote::query()
            ->whereBetween('credit_note_date', [$dateFrom, $dateTo])
            ->sum('total');

        return [
            'supplier_invoices' => round($supplierInvoices, 2),
            'credit_notes' => round($creditNotes, 2),
            'purchases' => round($supplierInvoices - $creditNotes, 2),
        ];
    }

    /**
     * @return array{
     *     collected: float,
     *     deductible: float,
     *     net: float,
     *     base_ht: float,
     *     rates: list<float>,
     *     collected_invoices: float,
     *     collected_pos: float,
     *     collected_credit_notes: float,
     *     deductible_purchases: float,
     *     deductible_expenses: float,
     *     deductible_credit_notes: float
     * }
     */
    public function getVatBreakdown(Carbon $dateFrom, Carbon $dateTo): array
    {
        $invoices = Invoice::query()
            ->with('items')
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->get();

        $invoiceTotals = $this->sumDocumentTotals($invoices);
        $collectedInvoices = $invoiceTotals['tax_total'];

        $posSales = PosSale::query()
            ->where('status', PosSale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->whereDoesntHave('invoice')
            ->get(['tax_total', 'subtotal']);

        $collectedPos = (float) $posSales->sum('tax_total');
        $posHt = (float) $posSales->sum('subtotal');

        $creditNotes = CreditNote::query()
            ->with('items')
            ->whereBetween('credit_note_date', [$dateFrom, $dateTo])
            ->get();

        $creditTotals = $this->sumDocumentTotals($creditNotes);
        $collectedCreditNotes = $creditTotals['tax_total'];
        $collected = $collectedInvoices + $collectedPos - $collectedCreditNotes;

        $supplierInvoices = SupplierInvoice::query()
            ->with('items')
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->get();

        $purchaseTotals = $this->sumDocumentTotals($supplierInvoices);
        $deductiblePurchases = $purchaseTotals['tax_total'];

        $supplierCreditNotes = SupplierCreditNote::query()
            ->with('items')
            ->whereBetween('credit_note_date', [$dateFrom, $dateTo])
            ->get();

        $supplierCreditTotals = $this->sumDocumentTotals($supplierCreditNotes);
        $deductibleCreditNotes = $supplierCreditTotals['tax_total'];

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->get();

        $deductibleExpenses = (float) $expenses->sum(fn (Expense $expense) => $this->expenseDeductibleVat($expense));
        $deductible = $deductiblePurchases + $deductibleExpenses - $deductibleCreditNotes;

        $baseHt = $invoiceTotals['subtotal_ht'] + $posHt - $creditTotals['subtotal_ht']
            + $purchaseTotals['subtotal_ht'] - $supplierCreditTotals['subtotal_ht'];

        $rates = $this->collectTaxRates(
            $invoices->concat($creditNotes)->concat($supplierInvoices)->concat($supplierCreditNotes)
        );

        return [
            'collected' => round($collected, 2),
            'deductible' => round($deductible, 2),
            'net' => round($collected - $deductible, 2),
            'base_ht' => round($baseHt, 2),
            'rates' => $rates,
            'collected_invoices' => round($collectedInvoices, 2),
            'collected_pos' => round($collectedPos, 2),
            'collected_credit_notes' => round($collectedCreditNotes, 2),
            'deductible_purchases' => round($deductiblePurchases, 2),
            'deductible_expenses' => round($deductibleExpenses, 2),
            'deductible_credit_notes' => round($deductibleCreditNotes, 2),
        ];
    }

    /**
     * Santé de la période : état, anomalies détectées, dernière activité.
     *
     * @return array{
     *     status: string,
     *     status_label: string,
     *     anomalies_count: int,
     *     anomalies: list<string>,
     *     last_updated_at: ?Carbon,
     *     last_updated_label: string
     * }
     */
    public function getPeriodHealth(Carbon $dateFrom, Carbon $dateTo): array
    {
        $anomalies = [];

        $overdueClients = Invoice::query()
            ->withSum('payments as payments_sum', 'amount')
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today())
            ->get()
            ->filter(function (Invoice $invoice) {
                $paid = (float) ($invoice->payments_sum ?? 0);

                return $paid + 0.00001 < (float) $invoice->total;
            })
            ->count();

        if ($overdueClients > 0) {
            $anomalies[] = $overdueClients.' créance(s) client(s) en retard';
        }

        $overdueSuppliers = SupplierInvoice::query()
            ->withSum('payments as payments_sum', 'amount')
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today())
            ->get()
            ->filter(function (SupplierInvoice $invoice) {
                $paid = (float) ($invoice->payments_sum ?? 0);

                return $paid + 0.00001 < (float) $invoice->total;
            })
            ->count();

        if ($overdueSuppliers > 0) {
            $anomalies[] = $overdueSuppliers.' dette(s) fournisseur(s) en retard';
        }

        $partialClients = Invoice::query()
            ->withSum('payments as payments_sum', 'amount')
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->get()
            ->filter(function (Invoice $invoice) {
                $paid = (float) ($invoice->payments_sum ?? 0);
                $total = (float) $invoice->total;

                return $paid > 0 && $paid + 0.00001 < $total;
            })
            ->count();

        if ($partialClients > 0) {
            $anomalies[] = $partialClients.' facture(s) client(s) partiellement payée(s)';
        }

        $expensesWithoutRef = Expense::query()
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->where(function ($q) {
                $q->whereNull('reference')->orWhere('reference', '');
            })
            ->where(function ($q) {
                $q->whereNull('designation')->orWhere('designation', '');
            })
            ->count();

        if ($expensesWithoutRef > 0) {
            $anomalies[] = $expensesWithoutRef.' dépense(s) sans justificatif / libellé';
        }

        $lastDates = collect([
            Invoice::query()->whereBetween('invoice_date', [$dateFrom, $dateTo])->max('updated_at'),
            SupplierInvoice::query()->whereBetween('invoice_date', [$dateFrom, $dateTo])->max('updated_at'),
            Expense::query()->whereBetween('expense_date', [$dateFrom, $dateTo])->max('updated_at'),
            InvoicePayment::query()->whereBetween('payment_date', [$dateFrom, $dateTo])->max('updated_at'),
            SupplierInvoicePayment::query()->whereBetween('payment_date', [$dateFrom, $dateTo])->max('updated_at'),
            PosSale::query()->whereBetween('sold_at', [$dateFrom, $dateTo])->max('updated_at'),
        ])->filter()->map(fn ($d) => Carbon::parse($d));

        $lastUpdated = $lastDates->sortDesc()->first();
        $anomaliesCount = count($anomalies);
        $status = $anomaliesCount > 0 ? 'a_controler' : 'ok';

        return [
            'status' => $status,
            'status_label' => $status === 'ok' ? 'Contrôlée' : 'À contrôler',
            'anomalies_count' => $anomaliesCount,
            'anomalies' => $anomalies,
            'last_updated_at' => $lastUpdated,
            'last_updated_label' => $this->formatRelativeUpdate($lastUpdated),
        ];
    }

    /**
     * @return array{
     *     encaissements: float,
     *     decaissements: float,
     *     net: float,
     *     pos: float,
     *     payments_clients: float,
     *     payments_fournisseurs: float,
     *     expenses: float
     * }
     */
    public function getCashMovements(Carbon $dateFrom, Carbon $dateTo): array
    {
        $posStandalone = (float) PosSale::query()
            ->where('status', PosSale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->whereDoesntHave('invoice')
            ->sum('total');

        // Ventes POS déjà facturées mais sans ligne de paiement (ex. auto-payées) :
        // l'encaissement réel a eu lieu au ticket caisse.
        $posInvoicedWithoutPayments = (float) PosSale::query()
            ->where('status', PosSale::STATUS_COMPLETED)
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->whereHas('invoice', fn ($q) => $q->whereDoesntHave('payments'))
            ->sum('total');

        $clientPayments = (float) InvoicePayment::query()
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $supplierPayments = (float) SupplierInvoicePayment::query()
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $expenses = (float) Expense::query()
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->sum('amount');

        $encaissements = $posStandalone + $posInvoicedWithoutPayments + $clientPayments;
        $decaissements = $supplierPayments + $expenses;

        return [
            'pos' => round($posStandalone + $posInvoicedWithoutPayments, 2),
            'payments_clients' => round($clientPayments, 2),
            'encaissements' => round($encaissements, 2),
            'payments_fournisseurs' => round($supplierPayments, 2),
            'expenses' => round($expenses, 2),
            'decaissements' => round($decaissements, 2),
            'net' => round($encaissements - $decaissements, 2),
        ];
    }

    /**
     * Soldes disponibles (historique complet), ventilés caisse / banque.
     *
     * @return array{total: float, caisse: float, banque: float, other: float}
     */
    public function getTreasuryBalances(): array
    {
        $caisse = 0.0;
        $banque = 0.0;
        $other = 0.0;

        $add = function (string $bucket, float $amount) use (&$caisse, &$banque, &$other): void {
            if ($bucket === 'caisse') {
                $caisse += $amount;
            } elseif ($bucket === 'banque') {
                $banque += $amount;
            } else {
                $other += $amount;
            }
        };

        // Entrées POS : tickets sans facture, ou facturés sans ligne de paiement.
        PosSale::query()
            ->where('status', PosSale::STATUS_COMPLETED)
            ->where(function ($q) {
                $q->whereDoesntHave('invoice')
                    ->orWhereHas('invoice', fn ($iq) => $iq->whereDoesntHave('payments'));
            })
            ->get(['total', 'payment_method'])
            ->each(function (PosSale $sale) use ($add) {
                $add($this->classifyPosPaymentMethod($sale->payment_method), (float) $sale->total);
            });

        InvoicePayment::query()
            ->get(['amount', 'payment_method'])
            ->each(function (InvoicePayment $payment) use ($add) {
                $add($this->classifyDocumentPaymentMethod($payment->payment_method), (float) $payment->amount);
            });

        SupplierInvoicePayment::query()
            ->get(['amount', 'payment_method'])
            ->each(function (SupplierInvoicePayment $payment) use ($add) {
                $add($this->classifyDocumentPaymentMethod($payment->payment_method), -1 * (float) $payment->amount);
            });

        Expense::query()
            ->get(['amount', 'account', 'payment_method'])
            ->each(function (Expense $expense) use ($add) {
                $add(
                    $this->classifyExpenseAccount($expense->account, $expense->payment_method),
                    -1 * (float) $expense->amount
                );
            });

        $caisse = round($caisse, 2);
        $banque = round($banque, 2);
        $other = round($other, 2);

        return [
            'caisse' => $caisse,
            'banque' => $banque,
            'other' => $other,
            'total' => round($caisse + $banque + $other, 2),
        ];
    }

    /**
     * @return array{
     *     clients: array{paid: int, partial: int, unpaid: int, remaining_total: float},
     *     suppliers: array{paid: int, partial: int, unpaid: int, remaining_total: float}
     * }
     */
    public function getPaymentStatusSummary(): array
    {
        $clients = ['paid' => 0, 'partial' => 0, 'unpaid' => 0, 'remaining_total' => 0.0];
        $suppliers = ['paid' => 0, 'partial' => 0, 'unpaid' => 0, 'remaining_total' => 0.0];

        Invoice::query()
            ->withSum('payments as payments_sum', 'amount')
            ->get()
            ->each(function (Invoice $invoice) use (&$clients) {
                $paid = (float) ($invoice->payments_sum ?? 0);
                $total = (float) $invoice->total;
                $status = $this->statusFromAmounts($paid, $total);
                $clients[$status]++;
                $clients['remaining_total'] += max(0, $total - $paid);
            });

        SupplierInvoice::query()
            ->withSum('payments as payments_sum', 'amount')
            ->get()
            ->each(function (SupplierInvoice $invoice) use (&$suppliers) {
                $paid = (float) ($invoice->payments_sum ?? 0);
                $total = (float) $invoice->total;
                $status = $this->statusFromAmounts($paid, $total);
                $suppliers[$status]++;
                $suppliers['remaining_total'] += max(0, $total - $paid);
            });

        $clients['remaining_total'] = round($clients['remaining_total'], 2);
        $suppliers['remaining_total'] = round($suppliers['remaining_total'], 2);

        return compact('clients', 'suppliers');
    }

    /**
     * @return array{labels: list<string>, revenue: list<float>, expenses: list<float>, cash_in: list<float>, cash_out: list<float>, purchases: list<float>}
     */
    public function getMonthlyChart(int $months = 6, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $dateTo ??= Carbon::now();
        $dateFrom ??= $dateTo->copy()->subMonths($months - 1)->startOfMonth();

        $labels = [];
        $revenue = [];
        $expenses = [];
        $cashIn = [];
        $cashOut = [];
        $purchases = [];

        $cursor = $dateFrom->copy()->startOfMonth();
        $end = $dateTo->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            $start = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $labels[] = $cursor->translatedFormat('M Y');

            $sales = $this->getSalesBreakdown($start, $monthEnd);
            $purchase = $this->getPurchasesBreakdown($start, $monthEnd);
            $cash = $this->getCashMovements($start, $monthEnd);

            $revenue[] = $sales['revenue'];
            $purchases[] = $purchase['purchases'];
            $expenses[] = round((float) Expense::query()
                ->whereBetween('expense_date', [$start, $monthEnd])
                ->sum('amount'), 2);
            $cashIn[] = $cash['encaissements'];
            $cashOut[] = $cash['decaissements'];

            $cursor->addMonth();
        }

        return compact('labels', 'revenue', 'expenses', 'cashIn', 'cashOut', 'purchases');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRecentTransactions(
        int $limit = 50,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?string $type = null,
        ?string $search = null,
    ): array {
        $transactions = collect();

        $includeIn = $type === null || $type === '' || $type === 'all' || in_array($type, ['encaissement', 'in'], true);
        $includeOut = $type === null || $type === '' || $type === 'all' || in_array($type, ['decaissement', 'out'], true);
        $includePos = $type === null || $type === '' || $type === 'all' || $type === 'pos' || $type === 'encaissement';
        $includeExpense = $type === null || $type === '' || $type === 'all' || $type === 'expense' || $type === 'decaissement';
        $includeSupplier = $type === null || $type === '' || $type === 'all' || $type === 'purchase' || $type === 'decaissement';
        $includeClient = $type === null || $type === '' || $type === 'all' || $type === 'sale' || $type === 'encaissement';

        if ($includeClient || $includeIn) {
            $transactions = $transactions->concat(
                InvoicePayment::with('invoice.client')
                    ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('payment_date', [$dateFrom, $dateTo]))
                    ->latest('payment_date')
                    ->limit($limit * 2)
                    ->get()
                    ->map(fn (InvoicePayment $payment) => [
                        'date' => $payment->payment_date,
                        'label' => 'Encaissement client',
                        'type' => 'encaissement',
                        'status' => 'paid',
                        'reference' => $payment->invoice?->invoice_number ?? '—',
                        'party' => $payment->invoice?->client?->name ?? '—',
                        'method' => $payment->payment_method,
                        'amount' => (float) $payment->amount,
                        'direction' => 'in',
                        'url' => $payment->invoice ? route('invoices.payments.index', $payment->invoice) : null,
                    ])
            );
        }

        if ($includePos) {
            $transactions = $transactions->concat(
                PosSale::with('client')
                    ->where('status', PosSale::STATUS_COMPLETED)
                    ->where(function ($q) {
                        $q->whereDoesntHave('invoice')
                            ->orWhereHas('invoice', fn ($iq) => $iq->whereDoesntHave('payments'));
                    })
                    ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('sold_at', [$dateFrom, $dateTo]))
                    ->latest('sold_at')
                    ->limit($limit * 2)
                    ->get()
                    ->map(fn (PosSale $sale) => [
                        'date' => $sale->sold_at,
                        'label' => 'Vente POS',
                        'type' => 'pos',
                        'status' => 'paid',
                        'reference' => $sale->ticket_number,
                        'party' => $sale->client?->name ?? 'Comptoir',
                        'method' => $sale->paymentLabel(),
                        'amount' => (float) $sale->total,
                        'direction' => 'in',
                        'url' => route('pos.sales.show', $sale),
                    ])
            );
        }

        if ($includeSupplier || $includeOut) {
            $transactions = $transactions->concat(
                SupplierInvoicePayment::with('supplierInvoice.supplier')
                    ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('payment_date', [$dateFrom, $dateTo]))
                    ->latest('payment_date')
                    ->limit($limit * 2)
                    ->get()
                    ->map(fn (SupplierInvoicePayment $payment) => [
                        'date' => $payment->payment_date,
                        'label' => 'Paiement fournisseur',
                        'type' => 'decaissement',
                        'status' => 'paid',
                        'reference' => $payment->supplierInvoice?->invoice_number ?? '—',
                        'party' => $payment->supplierInvoice?->supplier?->name ?? '—',
                        'method' => $payment->payment_method,
                        'amount' => (float) $payment->amount,
                        'direction' => 'out',
                        'url' => $payment->supplierInvoice
                            ? route('supplier-invoices.payments.index', $payment->supplierInvoice)
                            : null,
                    ])
            );
        }

        if ($includeExpense || $includeOut) {
            $transactions = $transactions->concat(
                Expense::with(['supplier', 'client'])
                    ->when($dateFrom && $dateTo, fn ($q) => $q->whereBetween('expense_date', [$dateFrom, $dateTo]))
                    ->latest('expense_date')
                    ->limit($limit * 2)
                    ->get()
                    ->map(fn (Expense $expense) => [
                        'date' => $expense->expense_date,
                        'label' => $expense->expense_type === 'with_invoice' ? 'Dépense avec facture' : 'Dépense sans facture',
                        'type' => 'expense',
                        'status' => 'paid',
                        'reference' => $expense->reference ?? $expense->designation,
                        'party' => $expense->supplier?->name ?? $expense->client?->name ?? '—',
                        'method' => $expense->payment_method ?? $expense->account,
                        'amount' => (float) $expense->amount,
                        'direction' => 'out',
                        'url' => $expense->expense_type === 'with_invoice'
                            ? route('expenses-with-invoice.show', $expense)
                            : route('expenses-without-invoice.show', $expense),
                    ])
            );
        }

        if ($search) {
            $needle = mb_strtolower(trim($search));
            $transactions = $transactions->filter(function (array $item) use ($needle) {
                $haystack = mb_strtolower(implode(' ', [
                    $item['label'] ?? '',
                    $item['reference'] ?? '',
                    $item['party'] ?? '',
                    $item['method'] ?? '',
                    $item['type'] ?? '',
                ]));

                return str_contains($haystack, $needle);
            });
        }

        return $transactions
            ->sortByDesc(fn (array $item) => $item['date']?->timestamp ?? 0)
            ->take($limit)
            ->map(fn (array $item) => [
                ...$item,
                'date_formatted' => $item['date']?->format('d/m/Y') ?? '—',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{count: int, total: float, items: list<array<string, mixed>>}
     */
    public function getOutstandingClientInvoices(int $limit = 8, ?string $status = null): array
    {
        $invoices = Invoice::with(['client'])
            ->withSum('payments as payments_sum', 'amount')
            ->latest('invoice_date')
            ->get()
            ->map(function (Invoice $invoice) {
                $paid = (float) ($invoice->payments_sum ?? 0);
                $total = (float) $invoice->total;

                return [
                    'number' => $invoice->invoice_number,
                    'party' => $invoice->client?->name ?? '—',
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => max(0, $total - $paid),
                    'status' => $this->statusFromAmounts($paid, $total),
                    'date' => $invoice->invoice_date?->format('d/m/Y'),
                    'due_date' => $invoice->due_date?->format('d/m/Y'),
                    'url' => route('invoices.payments.index', $invoice),
                ];
            })
            ->filter(fn (array $item) => $item['remaining'] > 0);

        if ($status && in_array($status, ['paid', 'partial', 'unpaid'], true)) {
            $invoices = $invoices->filter(fn (array $item) => $item['status'] === $status);
        }

        return [
            'count' => $invoices->count(),
            'total' => round($invoices->sum('remaining'), 2),
            'items' => $invoices->take($limit)->values()->all(),
        ];
    }

    /**
     * @return array{count: int, total: float, items: list<array<string, mixed>>}
     */
    public function getOutstandingSupplierInvoices(int $limit = 8, ?string $status = null): array
    {
        $invoices = SupplierInvoice::with(['supplier'])
            ->withSum('payments as payments_sum', 'amount')
            ->latest('invoice_date')
            ->get()
            ->map(function (SupplierInvoice $invoice) {
                $paid = (float) ($invoice->payments_sum ?? 0);
                $total = (float) $invoice->total;

                return [
                    'number' => $invoice->invoice_number,
                    'party' => $invoice->supplier?->name ?? '—',
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => max(0, $total - $paid),
                    'status' => $this->statusFromAmounts($paid, $total),
                    'date' => $invoice->invoice_date?->format('d/m/Y'),
                    'due_date' => $invoice->due_date?->format('d/m/Y'),
                    'url' => route('supplier-invoices.payments.index', $invoice),
                ];
            })
            ->filter(fn (array $item) => $item['remaining'] > 0);

        if ($status && in_array($status, ['paid', 'partial', 'unpaid'], true)) {
            $invoices = $invoices->filter(fn (array $item) => $item['status'] === $status);
        }

        return [
            'count' => $invoices->count(),
            'total' => round($invoices->sum('remaining'), 2),
            'items' => $invoices->take($limit)->values()->all(),
        ];
    }

    /**
     * Historique consolidé ventes / achats / dépenses pour la période.
     *
     * @return array{sales: list<array<string, mixed>>, purchases: list<array<string, mixed>>, expenses: list<array<string, mixed>>}
     */
    public function getPeriodHistory(Carbon $dateFrom, Carbon $dateTo, int $limit = 20): array
    {
        $sales = Invoice::with('client')
            ->withSum('payments as payments_sum', 'amount')
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->latest('invoice_date')
            ->limit($limit)
            ->get()
            ->map(function (Invoice $invoice) {
                $paid = (float) ($invoice->payments_sum ?? 0);
                $total = (float) $invoice->total;

                return [
                    'date' => $invoice->invoice_date?->format('d/m/Y'),
                    'reference' => $invoice->invoice_number,
                    'party' => $invoice->client?->name ?? '—',
                    'total' => $total,
                    'status' => $this->statusFromAmounts($paid, $total),
                    'url' => route('invoices.show', $invoice),
                ];
            })
            ->all();

        $posSales = PosSale::with('client')
            ->where('status', PosSale::STATUS_COMPLETED)
            ->whereDoesntHave('invoice')
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->latest('sold_at')
            ->limit($limit)
            ->get()
            ->map(fn (PosSale $sale) => [
                'date' => $sale->sold_at?->format('d/m/Y'),
                'reference' => $sale->ticket_number,
                'party' => $sale->client?->name ?? 'Comptoir',
                'total' => (float) $sale->total,
                'status' => 'paid',
                'url' => route('pos.sales.show', $sale),
            ])
            ->all();

        $purchases = SupplierInvoice::with('supplier')
            ->withSum('payments as payments_sum', 'amount')
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->latest('invoice_date')
            ->limit($limit)
            ->get()
            ->map(function (SupplierInvoice $invoice) {
                $paid = (float) ($invoice->payments_sum ?? 0);
                $total = (float) $invoice->total;

                return [
                    'date' => $invoice->invoice_date?->format('d/m/Y'),
                    'reference' => $invoice->invoice_number,
                    'party' => $invoice->supplier?->name ?? '—',
                    'total' => $total,
                    'status' => $this->statusFromAmounts($paid, $total),
                    'url' => route('supplier-invoices.show', $invoice),
                ];
            })
            ->all();

        $expenses = Expense::with(['supplier', 'client'])
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->latest('expense_date')
            ->limit($limit)
            ->get()
            ->map(fn (Expense $expense) => [
                'date' => $expense->expense_date?->format('d/m/Y'),
                'reference' => $expense->reference ?? $expense->designation,
                'party' => $expense->supplier?->name ?? $expense->client?->name ?? '—',
                'total' => (float) $expense->amount,
                'status' => 'paid',
                'url' => $expense->expense_type === 'with_invoice'
                    ? route('expenses-with-invoice.show', $expense)
                    : route('expenses-without-invoice.show', $expense),
            ])
            ->all();

        return [
            'sales' => collect($sales)->concat($posSales)->take($limit)->values()->all(),
            'purchases' => $purchases,
            'expenses' => $expenses,
        ];
    }

    /**
     * Lignes plates pour export CSV / déclaration.
     *
     * @return list<array<string, scalar|null>>
     */
    public function getDeclarationRows(Carbon $dateFrom, Carbon $dateTo): array
    {
        $overview = $this->getOverview($dateFrom, $dateTo);

        return [
            ['indicateur' => 'Période début', 'montant' => $dateFrom->toDateString(), 'detail' => ''],
            ['indicateur' => 'Période fin', 'montant' => $dateTo->toDateString(), 'detail' => ''],
            ['indicateur' => 'Chiffre d\'affaires TTC', 'montant' => $overview['revenue'], 'detail' => 'Factures + POS sans facture − avoirs'],
            ['indicateur' => 'CA factures', 'montant' => $overview['revenue_invoices'], 'detail' => ''],
            ['indicateur' => 'CA POS (sans facture)', 'montant' => $overview['revenue_pos'], 'detail' => ''],
            ['indicateur' => 'Avoirs clients', 'montant' => $overview['revenue_credit_notes'], 'detail' => ''],
            ['indicateur' => 'Achats TTC', 'montant' => $overview['purchases'], 'detail' => 'Factures fournisseurs − avoirs'],
            ['indicateur' => 'Dépenses', 'montant' => $overview['expenses'], 'detail' => ''],
            ['indicateur' => 'Résultat estimé', 'montant' => $overview['estimated_result'], 'detail' => 'CA − achats − dépenses'],
            ['indicateur' => 'TVA collectée', 'montant' => $overview['vat_collected'], 'detail' => ''],
            ['indicateur' => 'TVA déductible', 'montant' => $overview['vat_deductible'], 'detail' => ''],
            ['indicateur' => 'TVA nette à payer', 'montant' => $overview['vat_net'], 'detail' => 'Collectée − déductible'],
            ['indicateur' => 'Encaissements', 'montant' => $overview['client_payments'], 'detail' => ''],
            ['indicateur' => 'Décaissements', 'montant' => $overview['decaissements'], 'detail' => ''],
            ['indicateur' => 'Flux de trésorerie net', 'montant' => $overview['net_cash_flow'], 'detail' => ''],
            ['indicateur' => 'Trésorerie totale', 'montant' => $overview['treasury_total'], 'detail' => 'Solde cumulé'],
            ['indicateur' => 'Solde caisse', 'montant' => $overview['treasury_caisse'], 'detail' => ''],
            ['indicateur' => 'Solde banque', 'montant' => $overview['treasury_banque'], 'detail' => ''],
            ['indicateur' => 'Créances clients', 'montant' => $overview['client_receivables'], 'detail' => ''],
            ['indicateur' => 'Dettes fournisseurs', 'montant' => $overview['supplier_payables'], 'detail' => ''],
        ];
    }

    /**
     * @param  Collection<int, object>|iterable<object>  $documents
     * @return array{tax_total: float, subtotal_ht: float}
     */
    private function sumDocumentTotals(iterable $documents): array
    {
        $taxTotal = 0.0;
        $subtotalHt = 0.0;

        foreach ($documents as $document) {
            $items = $document->relationLoaded('items')
                ? $document->items
                : $document->items()->get();

            if ($items->isEmpty()) {
                continue;
            }

            $breakdown = DocumentTaxBreakdown::fromDocument($document, $items);
            $taxTotal += $breakdown['tax_total'];
            $subtotalHt += $breakdown['subtotal_ht'];
        }

        return [
            'tax_total' => round($taxTotal, 2),
            'subtotal_ht' => round($subtotalHt, 2),
        ];
    }

    /**
     * @param  Collection<int, object>|iterable<object>  $documents
     * @return list<float>
     */
    private function collectTaxRates(iterable $documents): array
    {
        $rates = [];

        foreach ($documents as $document) {
            $items = $document->relationLoaded('items')
                ? $document->items
                : $document->items()->get();

            foreach ($items as $item) {
                $rate = round((float) ($item->tax_rate ?? 0), 2);
                if ($rate > 0) {
                    $rates[$rate] = $rate;
                }
            }
        }

        $list = array_values($rates);
        sort($list);

        return $list;
    }

    private function formatRelativeUpdate(?Carbon $date): string
    {
        if (! $date) {
            return 'Aucune activité';
        }

        if ($date->isToday()) {
            return 'Aujourd\'hui '.$date->format('H:i');
        }

        if ($date->isYesterday()) {
            return 'Hier '.$date->format('H:i');
        }

        return $date->translatedFormat('d M Y H:i');
    }

    /**
     * @param  Collection<int, object>|iterable<object>  $documents
     */
    private function sumDocumentTax(iterable $documents): float
    {
        return $this->sumDocumentTotals($documents)['tax_total'];
    }

    public function expenseDeductibleVat(Expense $expense): float
    {
        $taxType = (string) ($expense->tax_type ?? '');
        if ($taxType === '' || str_contains(mb_strtoupper($taxType), 'NO TAX')) {
            return 0.0;
        }

        if (! preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $taxType, $matches)) {
            return 0.0;
        }

        $rate = (float) str_replace(',', '.', $matches[1]);
        if ($rate <= 0) {
            return 0.0;
        }

        $amount = (float) $expense->amount;

        // Montant dépense traité comme TTC (cohérent avec le mode achat).
        return round($amount * $rate / (100 + $rate), 2);
    }

    private function statusFromAmounts(float $paid, float $total): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid + 0.00001 >= $total) {
            return 'paid';
        }

        return 'partial';
    }

    private function classifyPosPaymentMethod(?string $method): string
    {
        $method = strtolower(trim((string) $method));

        return match (true) {
            $method === '' => 'other',
            $method === PosSale::PAYMENT_CASH, str_contains($method, 'esp'), str_contains($method, 'cash'), str_contains($method, 'caisse') => 'caisse',
            $method === PosSale::PAYMENT_CARD,
            $method === PosSale::PAYMENT_TRANSFER,
            $method === PosSale::PAYMENT_CHEQUE,
            str_contains($method, 'carte'),
            str_contains($method, 'vir'),
            str_contains($method, 'chèque'),
            str_contains($method, 'cheque'),
            str_contains($method, 'banque'),
            str_contains($method, 'bank') => 'banque',
            default => 'other',
        };
    }

    private function classifyDocumentPaymentMethod(?string $method): string
    {
        $method = mb_strtolower(trim((string) $method));

        return match (true) {
            $method === '' => 'other',
            str_contains($method, 'esp'), str_contains($method, 'caisse'), str_contains($method, 'cash') => 'caisse',
            str_contains($method, 'carte'),
            str_contains($method, 'vir'),
            str_contains($method, 'chèque'),
            str_contains($method, 'cheque'),
            str_contains($method, 'banque'),
            str_contains($method, 'bank') => 'banque',
            default => 'other',
        };
    }

    private function classifyExpenseAccount(?string $account, ?string $paymentMethod): string
    {
        $account = mb_strtolower(trim((string) $account));

        if (str_contains($account, 'caisse') || str_contains($account, 'esp')) {
            return 'caisse';
        }

        if (str_contains($account, 'banque') || str_contains($account, 'bank') || str_contains($account, 'compte')) {
            return 'banque';
        }

        return $this->classifyDocumentPaymentMethod($paymentMethod);
    }
}
