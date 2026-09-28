<?php

namespace App\Http\Controllers;

use App\Models\FinanceOpeningBalance;
use App\Models\FinancePeriod;
use App\Models\FinancialMovement;
use App\Models\Setting;
use App\Services\FinanceTreasuryService;
use App\Support\FinanceSettings;
use Illuminate\Http\Request;
use RuntimeException;

class FinanceCutoffController extends Controller
{
    public function __construct(
        protected FinanceTreasuryService $treasury
    ) {}

    public function index()
    {
        $cutoff = FinanceSettings::cutoffDateString();
        $accounts = FinancialMovement::accountLabels();

        $openings = FinanceOpeningBalance::query()
            ->where('as_of_date', $cutoff)
            ->get()
            ->keyBy('account');

        $balances = [];
        foreach (array_keys($accounts) as $account) {
            $balances[$account] = [
                'opening' => (float) ($openings[$account]->amount ?? 0),
                'current' => $this->treasury->accountBalance($account),
            ];
        }

        $periods = FinancePeriod::query()
            ->orderByDesc('period_end')
            ->limit(24)
            ->get();

        return view('financial.cutoff.index', [
            'cutoff' => $cutoff,
            'accountLabels' => $accounts,
            'balances' => $balances,
            'periods' => $periods,
        ]);
    }

    public function updateCutoff(Request $request)
    {
        $validated = $request->validate([
            'finance_cutoff_date' => 'required|date',
        ]);

        Setting::set(
            'finance_cutoff_date',
            $validated['finance_cutoff_date'],
            'Date de bascule Finance (soldes d’ouverture)'
        );

        return redirect()->route('financial.cutoff.index')
            ->with('success', 'Date de bascule mise à jour.');
    }

    public function saveOpenings(Request $request)
    {
        $validated = $request->validate([
            'balances' => 'required|array',
            'balances.caisse' => 'nullable|numeric',
            'balances.banque' => 'nullable|numeric',
            'balances.other' => 'nullable|numeric',
            'notes' => 'nullable|string|max:2000',
        ]);

        $balances = [];
        foreach (['caisse', 'banque', 'other'] as $account) {
            if (array_key_exists($account, $validated['balances'] ?? [])) {
                $balances[$account] = (float) ($validated['balances'][$account] ?? 0);
            }
        }

        $this->treasury->saveOpeningBalances($balances, $validated['notes'] ?? null);

        return redirect()->route('financial.cutoff.index')
            ->with('success', 'Soldes d’ouverture enregistrés (hors CA).');
    }

    public function closePeriod(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'close_reason' => 'required|string|min:5|max:2000',
        ]);

        try {
            $this->treasury->closePeriod(
                $validated['period_start'],
                $validated['period_end'],
                $validated['close_reason']
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('financial.cutoff.index')
            ->with('success', 'Période clôturée.');
    }

    public function reopenPeriod(Request $request, FinancePeriod $period)
    {
        $validated = $request->validate([
            'reopen_reason' => 'required|string|min:5|max:2000',
        ]);

        try {
            $this->treasury->reopenPeriod($period, $validated['reopen_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('financial.cutoff.index')
            ->with('success', 'Période réouverte.');
    }

    public function adjust(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'account' => 'required|in:caisse,banque,other',
            'direction' => 'required|in:+,-',
            'amount' => 'required|numeric|min:0.01',
            'motif' => 'required|string|max:255',
            'commentaire' => 'nullable|string|max:2000',
        ]);

        try {
            $this->treasury->createAdjustment($validated);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('financial.cutoff.index')
            ->with('success', 'Ajustement de trésorerie enregistré.');
    }
}
