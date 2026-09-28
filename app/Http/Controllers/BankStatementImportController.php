<?php

namespace App\Http\Controllers;

use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\FinancialMovement;
use App\Services\FinanceTreasuryService;
use Illuminate\Http\Request;
use RuntimeException;

class BankStatementImportController extends Controller
{
    public function __construct(
        protected FinanceTreasuryService $treasury
    ) {}

    public function index()
    {
        $imports = BankStatementImport::query()
            ->with('importer')
            ->orderByDesc('id')
            ->paginate(20);

        return view('financial.bank-imports.index', [
            'imports' => $imports,
            'accountLabels' => FinancialMovement::accountLabels(),
        ]);
    }

    public function create()
    {
        return view('financial.bank-imports.create', [
            'accountLabels' => FinancialMovement::accountLabels(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account' => 'required|in:caisse,banque,other',
            'statement_file' => 'required|file|mimes:csv,txt,xls,xlsx|max:10240',
        ]);

        try {
            $import = $this->treasury->importBankStatement(
                $request->file('statement_file'),
                $validated['account']
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('financial.bank-imports.show', $import)
            ->with('success', $import->lines_count.' ligne(s) importée(s). Suggestions de rapprochement calculées.');
    }

    public function show(BankStatementImport $bankImport)
    {
        $bankImport->load(['lines.movement', 'importer']);

        $candidateMovements = FinancialMovement::query()
            ->where('account', $bankImport->account)
            ->where('status', '!=', FinancialMovement::STATUS_POINTE)
            ->where('is_opening_balance', false)
            ->orderByDesc('movement_date')
            ->limit(200)
            ->get();

        return view('financial.bank-imports.show', [
            'import' => $bankImport,
            'candidateMovements' => $candidateMovements,
            'accountLabels' => FinancialMovement::accountLabels(),
            'statusLabels' => [
                BankStatementLine::STATUS_UNMATCHED => 'Non rapproché',
                BankStatementLine::STATUS_SUGGESTED => 'Suggestion',
                BankStatementLine::STATUS_MATCHED => 'Rapproché',
                BankStatementLine::STATUS_VARIANCE => 'Écart',
            ],
        ]);
    }

    public function match(Request $request, BankStatementLine $line)
    {
        $validated = $request->validate([
            'financial_movement_id' => 'required|exists:financial_movements,id',
        ]);

        $movement = FinancialMovement::findOrFail($validated['financial_movement_id']);

        try {
            $this->treasury->matchLine($line, $movement);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ligne rapprochée (mouvement pointé, non recréé).');
    }

    public function confirmSuggestion(BankStatementLine $line)
    {
        if ($line->status !== BankStatementLine::STATUS_SUGGESTED || ! $line->financial_movement_id) {
            return back()->with('error', 'Aucune suggestion à confirmer.');
        }

        $movement = FinancialMovement::findOrFail($line->financial_movement_id);
        $this->treasury->matchLine($line, $movement);

        return back()->with('success', 'Suggestion confirmée.');
    }

    public function ignore(BankStatementLine $line)
    {
        $line->update([
            'status' => BankStatementLine::STATUS_VARIANCE,
            'financial_movement_id' => null,
            'notes' => trim(($line->notes ? $line->notes."\n" : '').'Ignoré manuellement'),
        ]);

        return back()->with('success', 'Ligne marquée comme écart / ignorée.');
    }
}
