<?php

namespace App\Http\Controllers;

use App\Models\FinancialDeclaration;
use App\Models\FinancialPiece;
use App\Models\Invoice;
use App\Services\FinancialDeclarationService;
use App\Services\FinancialManagementService;
use App\Services\FinancialMovementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialManagementController extends Controller
{
    public function __construct(
        private FinancialManagementService $financial,
        private FinancialDeclarationService $declarations,
        private FinancialMovementService $movements,
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
        $controls = $this->financial->getVatControls($dateFrom, $dateTo);
        $ratesLabel = $vat['rates'] !== []
            ? 'Taux '.implode(', ', array_map(function ($r) {
                $formatted = rtrim(rtrim(number_format((float) $r, 2, '.', ''), '0'), '.');

                return $formatted.' %';
            }, $vat['rates']))
            : 'Aucun taux sur la période';

        return view('financial.tva', array_merge($this->sectionBase($request, $dateFrom, $dateTo, $overview), [
            'breadcrumb' => 'TVA',
            'sectionTitle' => 'TVA collectée et déductible',
            'sectionDescription' => 'Alimentée automatiquement par les factures et avoirs clients/fournisseurs validés.',
            'dataSource' => 'Factures clients, factures fournisseurs, avoirs, dépenses avec TVA',
            'kpiCards' => [
                ['label' => 'TVA collectée', 'value' => number_format($vat['collected'], 2).' DH', 'tone' => 'emerald', 'hint' => 'Ventes & POS'],
                ['label' => 'TVA déductible', 'value' => number_format($vat['deductible'], 2).' DH', 'tone' => 'sky', 'hint' => 'Achats & dépenses'],
                ['label' => 'TVA nette', 'value' => number_format($vat['net'], 2).' DH', 'tone' => 'blue', 'hint' => 'Collectée − déductible'],
                ['label' => 'Base HT', 'value' => number_format($vat['base_ht'], 2).' DH', 'tone' => 'amber', 'hint' => $ratesLabel],
            ],
            'infoItems' => [
                ['label' => 'Base HT', 'value' => number_format($vat['base_ht'], 2).' DH', 'url' => null],
                ['label' => $ratesLabel, 'value' => count($vat['rates']).' taux', 'url' => null],
                ['label' => 'TVA collectée', 'value' => number_format($vat['collected'], 2).' DH', 'url' => route('invoices.index')],
                ['label' => 'TVA déductible', 'value' => number_format($vat['deductible'], 2).' DH', 'url' => route('supplier-invoices.index')],
                ['label' => 'TVA nette', 'value' => number_format($vat['net'], 2).' DH', 'url' => route('financial.declarations', request()->only(['date_from', 'date_to']))],
            ],
            'primaryAction' => ['label' => '+ Ajouter une pièce', 'url' => '#', 'modal' => 'piece-modal'],
            'secondaryActions' => [
                ['label' => 'Contrôler', 'url' => route('financial.tva.control'), 'method' => 'POST'],
                ['label' => 'Préparer la déclaration', 'url' => route('financial.tva.prepare', request()->only(['date_from', 'date_to']))],
                ['label' => 'Exporter Excel', 'url' => route('financial.export', array_merge(request()->only(['date_from', 'date_to']), ['format' => 'excel']))],
                ['label' => 'Exporter PDF', 'url' => route('financial.export', array_merge(request()->only(['date_from', 'date_to']), ['format' => 'pdf']))],
            ],
            'explanation' => 'La TVA collectée vient des factures clients, ventes POS et avoirs clients. La TVA déductible vient des factures fournisseurs, dépenses avec TVA et avoirs fournisseurs. TVA nette = collectée − déductible. Aucune saisie manuelle.',
            'recentTransactions' => $this->financial->getRecentTransactions(15, $dateFrom, $dateTo),
            'filterRoute' => 'financial.tva',
            'vatControls' => $controls,
            'pieces' => FinancialPiece::query()->where('category', 'tva')->latest()->limit(10)->get(),
            'showPieceModal' => true,
        ]));
    }

    public function tvaControl(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $result = $this->declarations->runControls($dateFrom, $dateTo);
        $count = count($result['controls']);

        return redirect()
            ->route('financial.tva', $request->only(['date_from', 'date_to', 'month']))
            ->with(
                $count > 0 ? 'warning' : 'success',
                $count > 0
                    ? "Contrôle TVA terminé : {$count} anomalie(s) détectée(s)."
                    : 'Contrôle TVA terminé : aucune anomalie.'
            );
    }

    public function tvaPrepare(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $payload = $this->declarations->prepareVatDeclaration($dateFrom, $dateTo);
        $this->declarations->runControls($dateFrom, $dateTo);

        return view('financial.tva-prepare', [
            'payload' => $payload,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
        ]);
    }

    public function tresorerie(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $overview = $this->financial->getOverview($dateFrom, $dateTo);
        $ledger = $this->movements->treasuryFromMovements($dateFrom, $dateTo);

        return view('financial.tresorerie', array_merge($this->sectionBase($request, $dateFrom, $dateTo, $overview), [
            'breadcrumb' => 'TRÉSORERIE',
            'sectionTitle' => 'Trésorerie détaillée',
            'sectionDescription' => 'Soldes calculés automatiquement depuis le journal des mouvements — jamais saisis manuellement.',
            'dataSource' => 'Journal des mouvements (paiements, POS, dépenses, virements)',
            'kpiCards' => [
                ['label' => 'Solde banque', 'value' => number_format($overview['treasury_banque'], 2).' DH', 'tone' => 'blue', 'hint' => 'Cumul'],
                ['label' => 'Solde caisse', 'value' => number_format($overview['treasury_caisse'], 2).' DH', 'tone' => 'violet', 'hint' => 'Espèces'],
                ['label' => 'Entrées', 'value' => number_format($ledger['entrees'] ?? $overview['client_payments'], 2).' DH', 'tone' => 'emerald', 'hint' => 'Période'],
                ['label' => 'Sorties', 'value' => number_format($ledger['sorties'] ?? $overview['decaissements'], 2).' DH', 'tone' => 'rose', 'hint' => 'Période'],
                ['label' => 'Disponible', 'value' => number_format($overview['treasury_total'], 2).' DH', 'tone' => 'amber', 'hint' => 'Banque + caisse'],
            ],
            'infoItems' => [
                ['label' => 'Solde banque', 'value' => number_format($overview['treasury_banque'], 2).' DH', 'url' => route('financial.mouvements.index', ['account' => 'banque'])],
                ['label' => 'Solde caisse', 'value' => number_format($overview['treasury_caisse'], 2).' DH', 'url' => route('financial.mouvements.index', ['account' => 'caisse'])],
                ['label' => 'Entrées période', 'value' => number_format($ledger['entrees'] ?? $overview['client_payments'], 2).' DH', 'url' => route('financial.mouvements.index', ['type' => 'entree'])],
                ['label' => 'Sorties période', 'value' => number_format($ledger['sorties'] ?? $overview['decaissements'], 2).' DH', 'url' => route('financial.mouvements.index', ['type' => 'sortie'])],
                ['label' => 'Disponible', 'value' => number_format($overview['treasury_total'], 2).' DH', 'url' => route('financial.mouvements.index')],
            ],
            'primaryAction' => ['label' => 'Rapprocher', 'url' => route('financial.mouvements.reconcile', request()->only(['date_from', 'date_to']))],
            'secondaryActions' => [
                ['label' => 'Ajouter un mouvement', 'url' => route('financial.mouvements.create')],
                ['label' => 'Pointer', 'url' => route('financial.mouvements.reconcile', request()->only(['date_from', 'date_to']))],
                ['label' => 'Clôturer la journée', 'url' => '#', 'modal' => 'close-day-modal'],
            ],
            'explanation' => 'Chaque paiement client, encaissement POS, paiement fournisseur ou dépense crée automatiquement un mouvement. La trésorerie n\'est jamais remplie à la main.',
            'recentTransactions' => $this->financial->getRecentTransactions(15, $dateFrom, $dateTo),
            'filterRoute' => 'financial.tresorerie',
            'showCloseDayModal' => true,
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
            'sectionDescription' => 'Chaque achat/dépense alimente automatiquement trésorerie, TVA, dettes et journal.',
            'dataSource' => 'Gestion des achats + dépenses + paiements fournisseurs',
            'kpiCards' => [
                ['label' => 'Achats (factures)', 'value' => number_format($overview['supplier_purchases'], 2).' DH', 'tone' => 'blue'],
                ['label' => 'Dépenses avec facture', 'value' => number_format($overview['expenses_with_invoice'], 2).' DH', 'tone' => 'sky'],
                ['label' => 'Dépenses sans facture', 'value' => number_format($overview['expenses_without_invoice'], 2).' DH', 'tone' => 'violet'],
                ['label' => 'TVA déductible', 'value' => number_format($overview['vat_details']['deductible'], 2).' DH', 'tone' => 'emerald', 'hint' => 'Auto depuis factures'],
            ],
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
            'explanation' => 'Facture fournisseur = dette + TVA déductible (pas de mouvement tant que non payée). Paiement fournisseur = sortie + diminution dette. Dépense = sortie immédiate. BR/BL = stock/logistique uniquement.',
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
            'sectionDescription' => 'Suivi automatique des factures impayées, paiements partiels et restes dus.',
            'dataSource' => 'Factures non soldées et paiements partiels',
            'kpiCards' => [
                ['label' => 'Créances clients', 'value' => number_format($clients['total'], 2).' DH', 'tone' => 'amber', 'hint' => $clients['count'].' facture(s)'],
                ['label' => 'Dettes fournisseurs', 'value' => number_format($suppliers['total'], 2).' DH', 'tone' => 'rose', 'hint' => $suppliers['count'].' facture(s)'],
                ['label' => 'À encaisser', 'value' => number_format($overview['client_receivables'], 2).' DH', 'tone' => 'emerald'],
                ['label' => 'À payer', 'value' => number_format($overview['supplier_payables'], 2).' DH', 'tone' => 'blue'],
            ],
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
                ['label' => 'Relancer', 'url' => route('financial.creances-dettes.relancer'), 'method' => 'POST'],
                ['label' => 'Voir factures', 'url' => route('invoices.index')],
                ['label' => 'Exporter l\'état', 'url' => route('financial.export', request()->only(['date_from', 'date_to']))],
            ],
            'explanation' => 'Facture client → créance + TVA collectée. Paiement client → entrée trésorerie + baisse créance. Facture fournisseur → dette. Paiement fournisseur → sortie + baisse dette.',
            'historyRows' => $combined,
            'filterRoute' => 'financial.creances-dettes',
        ]));
    }

    public function relancer(Request $request)
    {
        $unpaid = Invoice::query()
            ->with(['client'])
            ->withSum('payments as payments_sum', 'amount')
            ->get()
            ->filter(function (Invoice $invoice) {
                $paid = (float) ($invoice->payments_sum ?? 0);

                return $paid + 0.00001 < (float) $invoice->total;
            });

        $count = $unpaid->count();

        return redirect()
            ->route('sales.payments.index', ['payment_status' => 'unpaid'])
            ->with(
                'success',
                $count > 0
                    ? "Relance préparée pour {$count} facture(s) impayée(s). Contactez les clients depuis la liste des paiements."
                    : 'Aucune créance à relancer.'
            );
    }

    public function declarations(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $overview = $this->financial->getOverview($dateFrom, $dateTo);
        $health = $this->financial->getPeriodHealth($dateFrom, $dateTo);
        $rows = $this->financial->getDeclarationRows($dateFrom, $dateTo);
        $declaration = $this->declarations->findOrCreateForPeriod($dateFrom, $dateTo);
        $statusLabels = FinancialDeclaration::statusLabels();

        return view('financial.declarations', array_merge($this->sectionBase($request, $dateFrom, $dateTo, $overview), [
            'breadcrumb' => 'DÉCLARATIONS',
            'sectionTitle' => 'Déclarations et clôtures',
            'sectionDescription' => 'Contrôler, valider et clôturer une période fiscale avec traçabilité.',
            'dataSource' => 'Toutes les opérations validées de la période',
            'kpiCards' => [
                ['label' => 'Période', 'value' => $dateFrom->format('d/m').' → '.$dateTo->format('d/m/Y'), 'tone' => 'slate'],
                ['label' => 'TVA nette', 'value' => number_format($overview['vat_net'], 2).' DH', 'tone' => 'blue'],
                ['label' => 'Pièces / anomalies', 'value' => $health['anomalies_count'] > 0 ? $health['anomalies_count'].' signal(s)' : 'Aucune', 'tone' => $health['anomalies_count'] > 0 ? 'amber' : 'emerald'],
                ['label' => 'Statut', 'value' => $statusLabels[$declaration->status] ?? $health['status_label'], 'tone' => $declaration->status === 'cloturee' ? 'slate' : 'violet'],
            ],
            'infoItems' => [
                ['label' => 'Période', 'value' => $dateFrom->format('d/m/Y').' → '.$dateTo->format('d/m/Y'), 'url' => null],
                ['label' => 'TVA nette', 'value' => number_format($overview['vat_net'], 2).' DH', 'url' => route('financial.tva', request()->only(['date_from', 'date_to']))],
                ['label' => 'Pièces manquantes', 'value' => $health['anomalies_count'] > 0 ? $health['anomalies_count'].' signal(s)' : 'Aucune', 'url' => null],
                ['label' => 'Anomalies', 'value' => $health['anomalies'] !== [] ? implode(' · ', array_slice($health['anomalies'], 0, 2)) : 'Aucune', 'url' => null],
                ['label' => 'Statut de clôture', 'value' => $statusLabels[$declaration->status] ?? $health['status_label'], 'url' => null],
            ],
            'primaryAction' => ['label' => 'Lancer les contrôles', 'url' => route('financial.declarations.control'), 'method' => 'POST'],
            'secondaryActions' => [
                ['label' => 'Valider', 'url' => route('financial.declarations.validate'), 'method' => 'POST'],
                ['label' => 'Clôturer', 'url' => route('financial.declarations.close'), 'method' => 'POST'],
            ],
            'linkAction' => ['label' => 'Rouvrir avec motif', 'url' => '#', 'modal' => 'reopen-modal'],
            'explanation' => 'La synthèse reprend CA, achats, dépenses, TVA et trésorerie. Validez puis clôturez pour verrouiller la période. Réouverture possible avec motif tracé.',
            'declarationRows' => $rows,
            'declaration' => $declaration,
            'recentTransactions' => $this->financial->getRecentTransactions(15, $dateFrom, $dateTo),
            'filterRoute' => 'financial.declarations',
            'showReopenModal' => true,
        ]));
    }

    public function declarationsControl(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $result = $this->declarations->runControls($dateFrom, $dateTo);
        $count = count($result['controls']);

        return redirect()
            ->route('financial.declarations', $request->only(['date_from', 'date_to', 'month']))
            ->with(
                $count > 0 ? 'warning' : 'success',
                $count > 0
                    ? "Contrôles terminés : {$count} point(s) à traiter."
                    : 'Contrôles terminés : période saine.'
            );
    }

    public function declarationsValidate(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        try {
            $this->declarations->validate($dateFrom, $dateTo);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('financial.declarations', $request->only(['date_from', 'date_to', 'month']))
            ->with('success', 'Période validée.');
    }

    public function declarationsClose(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $this->declarations->close($dateFrom, $dateTo);

        return redirect()
            ->route('financial.declarations', $request->only(['date_from', 'date_to', 'month']))
            ->with('success', 'Période clôturée.');
    }

    public function declarationsReopen(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $validated = $request->validate([
            'reopen_reason' => 'required|string|min:5|max:1000',
        ]);

        try {
            $this->declarations->reopen($dateFrom, $dateTo, $validated['reopen_reason']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('financial.declarations', $request->only(['date_from', 'date_to', 'month']))
            ->with('success', 'Période réouverte avec motif enregistré.');
    }

    public function export(Request $request): StreamedResponse
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $rows = $this->financial->getDeclarationRows($dateFrom, $dateTo);
        $format = $request->input('format', 'excel');
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
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
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
