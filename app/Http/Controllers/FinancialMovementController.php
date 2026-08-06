<?php

namespace App\Http\Controllers;

use App\Models\FinancialMovement;
use App\Models\FinancialPiece;
use App\Services\FinancialDeclarationService;
use App\Services\FinancialManagementService;
use App\Services\FinancialMovementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialMovementController extends Controller
{
    public function __construct(
        private FinancialMovementService $movements,
        private FinancialManagementService $financial,
        private FinancialDeclarationService $declarations,
    ) {}

    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        if (! FinancialMovement::query()->exists()) {
            $this->movements->backfill();
        }

        $query = FinancialMovement::query()
            ->with('user')
            ->whereBetween('movement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($request->filled('type') && $request->type !== 'all', fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('origin') && $request->origin !== 'all', fn ($q) => $q->where('origin', $request->origin))
            ->when($request->filled('account') && $request->account !== 'all', fn ($q) => $q->where('account', $request->account))
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $needle = '%'.$request->q.'%';
                $q->where(function ($inner) use ($needle) {
                    $inner->where('reference', 'like', $needle)
                        ->orWhere('label', 'like', $needle)
                        ->orWhere('notes', 'like', $needle);
                });
            })
            ->orderBy('movement_date')
            ->orderBy('id');

        $allForBalance = (clone $query)->get();
        $opening = $this->openingBalanceBefore($dateFrom);
        $withBalances = $this->movements->withRunningBalances($allForBalance, $opening);

        $perPage = 50;
        $page = max(1, (int) $request->input('page', 1));
        $total = $withBalances->count();
        $rows = $withBalances->slice(($page - 1) * $perPage, $perPage)->values();

        $treasury = $this->movements->treasuryFromMovements($dateFrom, $dateTo);

        return view('financial.mouvements.index', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
            'treasury' => $treasury,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'filters' => [
                'type' => $request->input('type', 'all'),
                'origin' => $request->input('origin', 'all'),
                'account' => $request->input('account', 'all'),
                'status' => $request->input('status', 'all'),
                'q' => $request->input('q', ''),
            ],
            'originLabels' => FinancialMovement::originLabels(),
            'typeLabels' => FinancialMovement::typeLabels(),
            'statusLabels' => FinancialMovement::statusLabels(),
            'accountLabels' => FinancialMovement::accountLabels(),
        ]);
    }

    public function create()
    {
        return view('financial.mouvements.create', [
            'originLabels' => FinancialMovement::originLabels(),
            'typeLabels' => FinancialMovement::typeLabels(),
            'accountLabels' => FinancialMovement::accountLabels(),
            'previewReference' => $this->movements->nextReference(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'movement_date' => 'required|date',
            'origin' => 'required|string',
            'type' => 'required|in:entree,sortie,virement',
            'label' => 'required|string|max:255',
            'account' => 'required|in:caisse,banque,other',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'justificatif' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('justificatif')) {
            $validated['justificatif_path'] = $request->file('justificatif')->store('financial/mouvements', 'public');
        }

        $this->movements->createManual($validated, $request->user()->id);

        return redirect()
            ->route('financial.mouvements.index')
            ->with('success', 'Mouvement manuel créé. Il alimente automatiquement la trésorerie.');
    }

    public function edit(FinancialMovement $mouvement)
    {
        if (! $mouvement->isEditable()) {
            return redirect()
                ->route('financial.mouvements.index')
                ->with('error', 'Seuls les mouvements manuels non clôturés peuvent être modifiés.');
        }

        return view('financial.mouvements.edit', [
            'mouvement' => $mouvement,
            'originLabels' => FinancialMovement::originLabels(),
            'typeLabels' => FinancialMovement::typeLabels(),
            'accountLabels' => FinancialMovement::accountLabels(),
        ]);
    }

    public function update(Request $request, FinancialMovement $mouvement)
    {
        $validated = $request->validate([
            'movement_date' => 'required|date',
            'origin' => 'required|string',
            'type' => 'required|in:entree,sortie,virement',
            'label' => 'required|string|max:255',
            'account' => 'required|in:caisse,banque,other',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'justificatif' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->hasFile('justificatif')) {
            if ($mouvement->justificatif_path) {
                Storage::disk('public')->delete($mouvement->justificatif_path);
            }
            $validated['justificatif_path'] = $request->file('justificatif')->store('financial/mouvements', 'public');
        }

        try {
            $this->movements->updateManual($mouvement, $validated);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('financial.mouvements.index')
            ->with('success', 'Mouvement mis à jour.');
    }

    public function destroy(FinancialMovement $mouvement)
    {
        if (! $mouvement->isDeletable()) {
            return back()->with('error', 'Ce mouvement ne peut pas être supprimé (automatique, pointé ou clôturé).');
        }

        if ($mouvement->justificatif_path) {
            Storage::disk('public')->delete($mouvement->justificatif_path);
        }

        $mouvement->delete();

        return back()->with('success', 'Mouvement supprimé.');
    }

    public function point(Request $request, FinancialMovement $mouvement)
    {
        try {
            $this->movements->point($mouvement, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Mouvement pointé.');
    }

    public function pointBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:financial_movements,id',
        ]);

        $count = 0;
        foreach ($validated['ids'] as $id) {
            $movement = FinancialMovement::find($id);
            if (! $movement || $movement->isLocked()) {
                continue;
            }
            $this->movements->point($movement, $request->user()->id);
            $count++;
        }

        return back()->with('success', $count.' mouvement(s) pointé(s).');
    }

    public function closeDay(Request $request)
    {
        $validated = $request->validate([
            'day' => 'required|date',
        ]);

        $count = $this->movements->closeDay(Carbon::parse($validated['day']), $request->user()->id);

        return back()->with('success', "Journée clôturée : {$count} mouvement(s) verrouillé(s).");
    }

    public function reconcile(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $unpointed = FinancialMovement::query()
            ->whereBetween('movement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('status', '!=', FinancialMovement::STATUS_POINTE)
            ->where('status', '!=', FinancialMovement::STATUS_CLOTURE)
            ->where('account', FinancialMovement::ACCOUNT_BANQUE)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $treasury = $this->movements->treasuryFromMovements($dateFrom, $dateTo);

        return view('financial.mouvements.reconcile', [
            'unpointed' => $unpointed,
            'treasury' => $treasury,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
        ]);
    }

    public function sync(Request $request)
    {
        $result = $this->movements->backfill();

        return back()->with(
            'success',
            "Journal synchronisé : {$result['created']} mouvement(s) traités, {$result['skipped']} ignoré(s) (doublons POS)."
        );
    }

    public function export(Request $request): StreamedResponse
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $format = $request->input('format', 'excel');

        $movements = FinancialMovement::query()
            ->with('user')
            ->whereBetween('movement_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $opening = $this->openingBalanceBefore($dateFrom);
        $rows = $this->movements->withRunningBalances($movements, $opening);
        $filename = sprintf('mouvements_%s_%s.csv', $dateFrom->format('Ymd'), $dateTo->format('Ymd'));

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'Date', 'Référence', 'Origine', 'Type', 'Libellé', 'Banque/Caisse',
                'Entrée', 'Sortie', 'Solde', 'Utilisateur', 'Statut', 'Justificatif',
            ], ';');

            foreach ($rows as $row) {
                /** @var FinancialMovement $m */
                $m = $row['movement'];
                fputcsv($handle, [
                    $m->movement_date?->format('d/m/Y'),
                    $m->reference,
                    FinancialMovement::originLabels()[$m->origin] ?? $m->origin,
                    FinancialMovement::typeLabels()[$m->type] ?? $m->type,
                    $m->label,
                    FinancialMovement::accountLabels()[$m->account] ?? $m->account,
                    number_format((float) $m->amount_in, 2, '.', ''),
                    number_format((float) $m->amount_out, 2, '.', ''),
                    number_format((float) $row['solde'], 2, '.', ''),
                    $m->user?->name ?? '',
                    FinancialMovement::statusLabels()[$m->status] ?? $m->status,
                    $m->justificatif_path ? 'Oui' : 'Non',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => $format === 'pdf'
                ? 'text/csv; charset=UTF-8'
                : 'text/csv; charset=UTF-8',
        ]);
    }

    public function storePiece(Request $request)
    {
        $validated = $request->validate([
            'piece_date' => 'required|date',
            'label' => 'required|string|max:255',
            'category' => 'nullable|string|max:40',
            'notes' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $path = $request->file('file')->store('financial/pieces', 'public');

        FinancialPiece::create([
            'piece_date' => $validated['piece_date'],
            'label' => $validated['label'],
            'category' => $validated['category'] ?? 'tva',
            'file_path' => $path,
            'user_id' => $request->user()->id,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Pièce justificative ajoutée.');
    }

    private function openingBalanceBefore(Carbon $dateFrom): float
    {
        $dayBefore = $dateFrom->copy()->subDay()->toDateString();

        $in = (float) FinancialMovement::query()
            ->whereDate('movement_date', '<=', $dayBefore)
            ->sum('amount_in');
        $out = (float) FinancialMovement::query()
            ->whereDate('movement_date', '<=', $dayBefore)
            ->sum('amount_out');

        return round($in - $out, 2);
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
}
