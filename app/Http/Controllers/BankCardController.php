<?php

namespace App\Http\Controllers;

use App\Models\BankCard;
use App\Models\FinancialMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankCardController extends Controller
{
    public function index()
    {
        $cards = BankCard::query()
            ->with('endowments')
            ->orderByDesc('is_active')
            ->orderBy('label')
            ->get();

        return view('financial.cards.index', [
            'cards' => $cards,
            'accountLabels' => FinancialMovement::accountLabels(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'account' => ['required', Rule::in(['caisse', 'banque', 'other'])],
            'last_four' => 'nullable|digits:4',
            'currency' => 'nullable|string|max:10',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['currency'] = $validated['currency'] ?? 'MAD';
        $validated['is_active'] = $request->boolean('is_active', true);

        BankCard::create($validated);

        return redirect()->route('financial.cards.index')
            ->with('success', 'Carte bancaire créée.');
    }

    public function update(Request $request, BankCard $card)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'account' => ['required', Rule::in(['caisse', 'banque', 'other'])],
            'last_four' => 'nullable|digits:4',
            'currency' => 'nullable|string|max:10',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['currency'] = $validated['currency'] ?? 'MAD';
        $validated['is_active'] = $request->boolean('is_active');

        $card->update($validated);

        return redirect()->route('financial.cards.index')
            ->with('success', 'Carte mise à jour.');
    }

    public function destroy(BankCard $card)
    {
        $card->delete();

        return redirect()->route('financial.cards.index')
            ->with('success', 'Carte supprimée.');
    }
}
