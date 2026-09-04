<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Services\Access\CommissionSettlementService;
use Illuminate\Http\Request;

class CommissionSettlementController extends Controller
{
    public function __construct(
        protected CommissionSettlementService $settlements,
    ) {}

    public function linkPayroll(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:commissions,id'],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'label' => ['nullable', 'string', 'max:190'],
        ]);

        try {
            $this->settlements->linkToPayroll(
                $validated['ids'],
                (int) $validated['period_year'],
                (int) $validated['period_month'],
                $validated['label'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Commissions liées aux éléments variables de paie (contrôle Admin).');
    }

    public function freelancePayout(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:commissions,id'],
            'date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:120'],
            'payment_reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $this->settlements->createFreelancePayout($validated['ids'], $validated);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Règlement freelance enregistré (hors paie salarié).');
    }
}
