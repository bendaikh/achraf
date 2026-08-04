<?php

namespace App\Http\Controllers;

use App\Services\FinancialManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialManagementController extends Controller
{
    public function __construct(
        private FinancialManagementService $financial
    ) {}

    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $operationType = $request->input('operation_type', 'all');
        $paymentStatus = $request->input('payment_status');
        $search = $request->input('q');

        return view('financial.index', [
            'overview' => $this->financial->getOverview($dateFrom, $dateTo),
            'chart' => $this->financial->getMonthlyChart(6, $dateFrom, $dateTo),
            'recentTransactions' => $this->financial->getRecentTransactions(
                40,
                $dateFrom,
                $dateTo,
                $operationType,
                $search
            ),
            'history' => $this->financial->getPeriodHistory($dateFrom, $dateTo, 15),
            'outstandingClients' => $this->financial->getOutstandingClientInvoices(8, $paymentStatus),
            'outstandingSuppliers' => $this->financial->getOutstandingSupplierInvoices(8, $paymentStatus),
            'health' => $this->financial->getPeriodHealth($dateFrom, $dateTo),
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'operationType' => $operationType,
            'paymentStatus' => $paymentStatus,
            'search' => $search,
            'monthOptions' => $this->monthOptions(),
            'selectedMonth' => $dateFrom->format('Y-m'),
        ]);
    }

    public function tva(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $overview = $this->financial->getOverview($dateFrom, $dateTo);
        $vat = $overview['vat_details'];
        $ratesLabel = $vat['rates'] !== []
            ? 'Taux '.implode(', ', array_map(function ($r) {
                $formatted = rtrim(rtrim(number_format((float) $r, 2, '.', ''), '0'), '.');

                return $formatted.' %';
            }, $vat['rates']))
            : 'Aucun taux sur la période';

        return view('financial.tva', array_merge($this->sectionBase($request, $dateFrom, $dateTo, $overview), [
            'breadcrumb' => 'TVA',
            'sectionTitle' => 'TVA collectée et déductible',
            'sectionDescription' => 'Comparer automatiquement la TVA sur les ventes avec la TVA récupérable sur les achats.',
            'dataSource' => 'Factures clients et factures fournisseurs validées',
            'infoItems' => [
                ['label' => 'Base HT', 'value' => number_format($vat['base_ht'], 2).' DH', 'url' => null],
                ['label' => $ratesLabel, 'value' => count($vat['rates']).' taux', 'url' => null],
                ['label' => 'TVA collectée', 'value' => number_format($vat['collected'], 2).' DH', 'url' => route('invoices.index')],
                ['label' => 'TVA déductible', 'value' => number_format($vat['deductible'], 2).' DH', 'url' => route('supplier-invoices.index')],
                ['label' => 'TVA nette', 'value' => number_format($vat['net'], 2).' DH', 'url' => route('financial.declarations', request()->only(['date_from', 'date_to']))],
            ],
            'primaryAction' => ['label' => '+ Ajouter une pièce', 'url' => route('invoices.create')],
            'secondaryActions' => [
                ['label' => 'Contrôler', 'url' => route('financial.tva', request()->only(['date_from', 'date_to']))],
                ['label' => 'Préparer la déclaration', 'url' => route('financial.declarations', request()->only(['date_from', 'date_to']))],
                ['label' => 'Exporter Excel/PDF', 'url' => route('financial.export', request()->only(['date_from', 'date_to']))],
            ],
            'explanation' => 'La TVA collectée vient des factures clients et ventes POS. La TVA déductible vient des factures fournisseurs et dépenses avec TVA. La TVA nette = collectée − déductible.',
            'recentTransactions' => $this->financial->getRecentTransactions(15, $dateFrom, $dateTo),
            'filterRoute' => 'financial.tva',
        ]));
    }

    public function tresorerie(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $overview = $this->financial->getOverview($dateFrom, $dateTo);

        return view('financial.tresorerie', array_merge($this->sectionBase($request, $dateFrom, $dateTo, $overview), [
            'breadcrumb' => 'TRÉSORERIE',
            'sectionTitle' => 'Trésorerie détaillée',
            'sectionDescription' => 'Savoir où se trouve réellement l\'argent et distinguer le résultat comptable du cash disponible.',
            'dataSource' => 'Banque + caisse + POS + encaissements + décaissements',
            'infoItems' => [
                ['label' => 'Solde banque', 'value' => number_format($overview['treasury_banque'], 2).' DH', 'url' => route('sales.payments.index')],
                ['label' => 'Solde caisse', 'value' => number_format($overview['treasury_caisse'], 2).' DH', 'url' => route('pos.sales.index')],
                ['label' => 'Autres / non classés', 'value' => number_format($overview['treasury_other'], 2).' DH', 'url' => null],
                ['label' => 'Entrées', 'value' => number_format($overview['client_payments'], 2).' DH', 'url' => route('sales.payments.index')],
                ['label' => 'Sorties', 'value' => number_format($overview['decaissements'], 2).' DH', 'url' => route('purchases.payments.index')],
            ],
            'primaryAction' => ['label' => '+ Rapprocher', 'url' => route('sales.payments.index')],
            'secondaryActions' => [
                ['label' => 'Ajouter un mouvement', 'url' => route('expenses-without-invoice.create')],
                ['label' => 'Pointer', 'url' => route('pos.sales.index')],
                ['label' => 'Clôturer la journée', 'url' => route('financial.declarations', request()->only(['date_from', 'date_to']))],
            ],
            'explanation' => 'La trésorerie cumule les encaissements (POS, paiements clients) et décaissements (paiements fournisseurs, dépenses), ventilés entre caisse et banque.',
            'recentTransactions' => $this->financial->getRecentTransactions(15, $dateFrom, $dateTo),
            'filterRoute' => 'financial.tresorerie',
        ]));
    }

    public function achatsDepenses(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $overview = $this->financial->getOverview($dateFrom, $dateTo);
        $history = $this->financial->getPeriodHistory($dateFrom, $dateTo, 15);
        $purchasesAndExpenses = collect($history['purchases'])
            ->concat($history['expenses'])
            ->take(15)
            ->values()
            ->all();

        return view('financial.achats-depenses', array_merge($this->sectionBase($request, $dateFrom, $dateTo, $overview), [
            'breadcrumb' => 'ACHATS & DÉPENSES',
            'sectionTitle' => 'Achats et dépenses',
            'sectionDescription' => 'Centraliser toutes les sorties avec ou sans facture et identifier la TVA récupérable.',
            'dataSource' => 'Gestion des achats + dépenses manuelles + fournisseurs',
            'infoItems' => [
                ['label' => 'Fournisseurs / tiers', 'value' => number_format($overview['purchases'] + $overview['expenses'], 2).' DH', 'url' => route('suppliers.index')],
                ['label' => 'Achats (factures fourn.)', 'value' => number_format($overview['supplier_purchases'], 2).' DH', 'url' => route('supplier-invoices.index')],
                ['label' => 'HT / TVA / TTC', 'value' => 'TVA déd. '.number_format($overview['vat_details']['deductible'], 2).' DH', 'url' => route('financial.tva', request()->only(['date_from', 'date_to'])), 'highlight' => true],
                ['label' => 'Dépenses avec facture', 'value' => number_format($overview['expenses_with_invoice'], 2).' DH', 'url' => route('expenses-with-invoice.index')],
                ['label' => 'Dépenses sans facture', 'value' => number_format($overview['expenses_without_invoice'], 2).' DH', 'url' => route('expenses-without-invoice.index')],
            ],
            'primaryAction' => ['label' => '+ Nouvel achat', 'url' => route('supplier-invoices.create')],
            'secondaryActions' => [
                ['label' => 'Nouvelle dépense', 'url' => route('expenses-without-invoice.create')],
                ['label' => 'Joindre une facture', 'url' => route('expenses-with-invoice.create')],
                ['label' => 'Marquer payé', 'url' => route('purchases.payments.index')],
            ],
            'explanation' => 'Les achats viennent des factures fournisseurs. Les dépenses (avec ou sans facture) sont suivies séparément. La TVA récupérable apparaît dans l\'onglet TVA.',
            'historyRows' => $purchasesAndExpenses,
            'filterRoute' => 'financial.achats-depenses',
        ]));
    }

    public function creancesDettes(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $overview = $this->financial->getOverview($dateFrom, $dateTo);
        $clients = $this->financial->getOutstandingClientInvoices(20);
        $suppliers = $this->financial->getOutstandingSupplierInvoices(20);
        $combined = collect($clients['items'])
            ->map(fn (array $item) => [
                'date' => $item['date'],
                'reference' => $item['number'],
                'party' => $item['party'],
                'total' => $item['remaining'],
                'status' => $item['status'],
                'url' => $item['url'],
                'kind' => 'Créance',
            ])
            ->concat(collect($suppliers['items'])->map(fn (array $item) => [
                'date' => $item['date'],
                'reference' => $item['number'],
                'party' => $item['party'],
                'total' => $item['remaining'],
                'status' => $item['status'],
                'url' => $item['url'],
                'kind' => 'Dette',
            ]))
            ->take(20)
            ->values()
            ->all();

        return view('financial.creances-dettes', array_merge($this->sectionBase($request, $dateFrom, $dateTo, $overview), [
            'breadcrumb' => 'CRÉANCES & DETTES',
            'sectionTitle' => 'Créances clients et dettes fournisseurs',
            'sectionDescription' => 'Suivre exactement ce qui doit être encaissé et ce qui doit être payé.',
            'dataSource' => 'Factures non soldées et paiements partiels',
            'infoItems' => [
                ['label' => 'Tiers (créances)', 'value' => $clients['count'].' facture(s)', 'url' => route('sales.payments.index', ['payment_status' => 'unpaid'])],
                ['label' => 'Créances clients', 'value' => number_format($clients['total'], 2).' DH', 'url' => route('sales.payments.index', ['payment_status' => 'unpaid'])],
                ['label' => 'Montant initial (période CA)', 'value' => number_format($overview['revenue'], 2).' DH', 'url' => route('invoices.index')],
                ['label' => 'Encaissements période', 'value' => number_format($overview['client_payments'], 2).' DH', 'url' => route('sales.payments.index')],
                ['label' => 'Reste à encaisser', 'value' => number_format($overview['client_receivables'], 2).' DH', 'url' => route('sales.payments.index', ['payment_status' => 'unpaid'])],
                ['label' => 'Dettes fournisseurs', 'value' => number_format($suppliers['total'], 2).' DH', 'url' => route('purchases.payments.index', ['payment_status' => 'unpaid'])],
            ],
            'primaryAction' => ['label' => '+ Enregistrer paiement', 'url' => route('sales.payments.index')],
            'secondaryActions' => [
                ['label' => 'Relancer', 'url' => route('sales.payments.index', ['payment_status' => 'unpaid'])],
                ['label' => 'Voir factures', 'url' => route('invoices.index')],
                ['label' => 'Exporter l\'état', 'url' => route('financial.export', request()->only(['date_from', 'date_to']))],
            ],
            'explanation' => 'Les créances sont les factures clients non soldées. Les dettes sont les factures fournisseurs non soldées. Les montants affichés sont les restes à payer réels.',
            'historyRows' => $combined,
            'filterRoute' => 'financial.creances-dettes',
        ]));
    }

    public function declarations(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $overview = $this->financial->getOverview($dateFrom, $dateTo);
        $health = $this->financial->getPeriodHealth($dateFrom, $dateTo);
        $rows = $this->financial->getDeclarationRows($dateFrom, $dateTo);

        return view('financial.declarations', array_merge($this->sectionBase($request, $dateFrom, $dateTo, $overview), [
            'breadcrumb' => 'DÉCLARATIONS',
            'sectionTitle' => 'Déclarations et clôtures',
            'sectionDescription' => 'Donner au comptable une période contrôlée, justifiée et verrouillable.',
            'dataSource' => 'Toutes les opérations validées de la période',
            'infoItems' => [
                ['label' => 'Période', 'value' => $dateFrom->format('d/m/Y').' → '.$dateTo->format('d/m/Y'), 'url' => null],
                ['label' => 'TVA nette', 'value' => number_format($overview['vat_net'], 2).' DH', 'url' => route('financial.tva', request()->only(['date_from', 'date_to']))],
                ['label' => 'Pièces manquantes', 'value' => $health['anomalies_count'] > 0 ? $health['anomalies_count'].' signal(s)' : 'Aucune', 'url' => null],
                ['label' => 'Anomalies', 'value' => $health['anomalies'] !== [] ? implode(' · ', array_slice($health['anomalies'], 0, 2)) : 'Aucune', 'url' => null],
                ['label' => 'Statut de clôture', 'value' => $health['status_label'], 'url' => null],
            ],
            'primaryAction' => ['label' => '+ Lancer les contrôles', 'url' => route('financial.declarations', request()->only(['date_from', 'date_to']))],
            'secondaryActions' => [
                ['label' => 'Valider', 'url' => route('financial.index', request()->only(['date_from', 'date_to']))],
                ['label' => 'Clôturer', 'url' => route('financial.export', request()->only(['date_from', 'date_to']))],
            ],
            'linkAction' => ['label' => 'Rouvrir avec motif', 'url' => route('financial.index', request()->only(['date_from', 'date_to']))],
            'explanation' => 'La synthèse reprend CA, achats, dépenses, TVA et trésorerie de la période. Exportez le CSV pour votre comptable.',
            'declarationRows' => $rows,
            'recentTransactions' => $this->financial->getRecentTransactions(15, $dateFrom, $dateTo),
            'filterRoute' => 'financial.declarations',
        ]));
    }

    public function export(Request $request): StreamedResponse
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $rows = $this->financial->getDeclarationRows($dateFrom, $dateTo);
        $filename = sprintf(
            'finance_%s_%s.csv',
            $dateFrom->format('Ymd'),
            $dateTo->format('Ymd')
        );

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Indicateur', 'Montant', 'Détail'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['indicateur'],
                    is_numeric($row['montant']) ? number_format((float) $row['montant'], 2, '.', '') : $row['montant'],
                    $row['detail'],
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        if ($request->filled('month') && ! $request->filled('date_from')) {
            $month = Carbon::parse($request->input('month').'-01');
            return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()->endOfDay()];
        }

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            return [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [$dateFrom, $dateTo];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function monthOptions(): array
    {
        $options = [];
        $cursor = Carbon::now()->startOfMonth();

        for ($i = 0; $i < 18; $i++) {
            $options[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('F Y'),
            ];
            $cursor->subMonth();
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionBase(Request $request, Carbon $dateFrom, Carbon $dateTo, array $overview): array
    {
        return [
            'overview' => $overview,
            'health' => $this->financial->getPeriodHealth($dateFrom, $dateTo),
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'monthOptions' => $this->monthOptions(),
            'selectedMonth' => $dateFrom->format('Y-m'),
        ];
    }
}
