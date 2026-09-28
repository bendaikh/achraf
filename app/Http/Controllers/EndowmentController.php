<?php

namespace App\Http\Controllers;

use App\Models\BankCard;
use App\Models\Endowment;
use App\Models\FinancialMovement;
use App\Services\EndowmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EndowmentController extends Controller
{
    public function index()
    {
        $endowments = Endowment::query()
            ->with(['cards', 'consumptions' => fn ($q) => $q->limit(5)])
            ->orderByDesc('is_active')
            ->orderByDesc('starts_on')
            ->orderBy('label')
            ->get();

        $cards = BankCard::query()->active()->orderBy('label')->get();

        return view('financial.endowments.index', [
            'endowments' => $endowments,
            'cards' => $cards,
            'accountLabels' => FinancialMovement::accountLabels(),
        ]);
    }

    public function show(Endowment $endowment)
    {
        $endowment->load(['cards', 'consumptions.user']);

        return view('financial.endowments.show', [
            'endowment' => $endowment,
            'accountLabels' => FinancialMovement::accountLabels(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $cardIds = $validated['bank_card_ids'] ?? [];
        unset($validated['bank_card_ids']);

        $validated['consumed_amount'] = 0;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['currency'] = $validated['currency'] ?? 'MAD';

        $endowment = Endowment::create($validated);
        $endowment->cards()->sync($cardIds);

        return redirect()->route('financial.endowments.index')
            ->with('success', 'Dotation créée.');
    }

    public function update(Request $request, Endowment $endowment)
    {
        $validated = $this->validated($request, $endowment);
        $cardIds = $validated['bank_card_ids'] ?? [];
        unset($validated['bank_card_ids']);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['currency'] = $validated['currency'] ?? 'MAD';
        // Never overwrite consumed_amount from the form.
        unset($validated['consumed_amount']);

        $endowment->update($validated);
        $endowment->cards()->sync($cardIds);

        return redirect()->route('financial.endowments.show', $endowment)
            ->with('success', 'Dotation mise à jour.');
    }

    public function destroy(Endowment $endowment)
    {
        $endowment->delete();

        return redirect()->route('financial.endowments.index')
            ->with('success', 'Dotation supprimée.');
    }

    public function recalc(Endowment $endowment, EndowmentService $service)
    {
        $service->recalcConsumed($endowment);

        return redirect()->route('financial.endowments.show', $endowment)
            ->with('success', 'Consommation recalculée.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Endowment $endowment = null): array
    {
        return $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'initial_amount' => 'required|numeric|min:0.01',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'bank_account' => ['required', Rule::in(['caisse', 'banque', 'other'])],
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
            'bank_card_ids' => 'nullable|array',
            'bank_card_ids.*' => 'integer|exists:bank_cards,id',
        ]);
    }
}
