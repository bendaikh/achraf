<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Services\Access\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommissionController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected CommissionService $commissions,
    ) {
        $this->commissions->ensureDefaultRule();
    }

    public function index(Request $request)
    {
        $query = Commission::query()->with(['collaborator', 'rule'])->latest('id');

        $this->applyTableSearch($query, $request, ['document_ref', 'notes']);
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableFilter($query, $request, 'collaborator_id', 'collaborator_id');
        $this->applyTableDateRange($query, $request, 'created_at');

        // Commercials only see their own unless admin
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin() && ! $user->hasRole('admin') && ! $user->hasRole('administrateur') && ! $user->hasRole('responsable-commercial')) {
            if ($user->collaborator_id) {
                $query->where('collaborator_id', $user->collaborator_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return view('access.commissions.index', [
            'commissions' => $this->paginateTable($query, $request, 25),
            'collaborators' => Collaborator::query()->where('is_commercial', true)->orderBy('last_name')->get(),
            'rules' => CommissionRule::query()->orderBy('name')->get(),
        ]);
    }

    public function rules()
    {
        return view('access.commissions.rules', [
            'rules' => CommissionRule::query()->orderBy('name')->get(),
        ]);
    }

    public function storeRule(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'type' => ['required', Rule::in(['percent_ca', 'fixed', 'percent_margin'])],
            'base' => ['required', Rule::in(['ca_ht', 'ca_ttc', 'collected', 'margin', 'fixed'])],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'trigger' => ['required', Rule::in(['invoice_validated', 'delivered', 'paid', 'delivered_paid'])],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        CommissionRule::query()->create($validated);

        return back()->with('success', 'Règle de commission créée.');
    }

    public function updateRule(Request $request, CommissionRule $rule)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'type' => ['required', Rule::in(['percent_ca', 'fixed', 'percent_margin'])],
            'base' => ['required', Rule::in(['ca_ht', 'ca_ttc', 'collected', 'margin', 'fixed'])],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'trigger' => ['required', Rule::in(['invoice_validated', 'delivered', 'paid', 'delivered_paid'])],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $rule->update($validated);

        return back()->with('success', 'Règle mise à jour.');
    }

    public function validateSelected(Request $request)
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:commissions,id'],
        ])['ids'];

        $count = $this->commissions->validate($ids);

        return back()->with('success', "{$count} commission(s) validée(s).");
    }

    public function markPaid(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:commissions,id'],
            'date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:120'],
            'payment_reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric'],
        ]);

        $count = $this->commissions->markPaid($validated['ids'], $validated);

        return back()->with('success', "{$count} commission(s) marquée(s) comme payée(s).");
    }
}
