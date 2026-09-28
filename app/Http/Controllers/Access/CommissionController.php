<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\CommissionRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommissionController extends Controller
{
    use FiltersIndexTables;

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
            'rules' => CommissionRule::query()->notArchived()->orderBy('name')->get(),
        ]);
    }

    public function rules(Request $request)
    {
        $editing = null;
        if ($request->filled('edit')) {
            $editing = CommissionRule::query()
                ->with('collaborator')
                ->whereKey((int) $request->input('edit'))
                ->whereNull('archived_at')
                ->first();
        }

        return view('access.commissions.rules', [
            'rules' => CommissionRule::query()
                ->with(['collaborator'])
                ->withCount('commissions')
                ->orderByRaw('archived_at is not null')
                ->orderBy('name')
                ->get(),
            'collaborators' => Collaborator::query()
                ->where('is_commercial', true)
                ->where('status', Collaborator::STATUS_ACTIF)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'editing' => $editing,
        ]);
    }

    public function storeRule(Request $request)
    {
        $validated = $this->validatedRule($request);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_default'] = $request->boolean('is_default');
        $validated['collaborator_id'] = $this->normalizeCollaboratorId($validated['collaborator_id'] ?? null);

        if ($validated['is_default'] && $validated['collaborator_id'] !== null) {
            throw ValidationException::withMessages([
                'is_default' => 'Une règle par défaut doit s’appliquer à tous les commerciaux.',
            ]);
        }

        $rule = CommissionRule::query()->create($validated);
        $this->syncDefaultFlag($rule);

        return redirect()
            ->route('access.commissions.rules')
            ->with('success', 'Règle de commission créée.');
    }

    public function updateRule(Request $request, CommissionRule $rule)
    {
        if ($rule->isArchived()) {
            return back()->with('error', 'Impossible de modifier une règle archivée.');
        }

        $validated = $this->validatedRule($request);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_default'] = $request->boolean('is_default');
        $validated['collaborator_id'] = $this->normalizeCollaboratorId($validated['collaborator_id'] ?? null);

        if ($validated['is_default'] && $validated['collaborator_id'] !== null) {
            throw ValidationException::withMessages([
                'is_default' => 'Une règle par défaut doit s’appliquer à tous les commerciaux.',
            ]);
        }

        // In-place update only — never creates a new row; existing commissions keep their snapshot.
        $rule->update($validated);
        $this->syncDefaultFlag($rule->fresh());

        return redirect()
            ->route('access.commissions.rules')
            ->with('success', 'Règle mise à jour. Les commissions déjà acquises ne sont pas modifiées.');
    }

    public function toggleRule(CommissionRule $rule)
    {
        if ($rule->isArchived()) {
            return redirect()
                ->route('access.commissions.rules')
                ->with('error', 'Impossible d’activer/désactiver une règle archivée.');
        }

        $rule->update(['is_active' => ! $rule->is_active]);

        $label = $rule->is_active ? 'activée' : 'désactivée';

        return redirect()
            ->route('access.commissions.rules')
            ->with('success', "Règle {$label}.");
    }

    public function destroyRule(CommissionRule $rule)
    {
        if ($rule->isArchived()) {
            return redirect()
                ->route('access.commissions.rules')
                ->with('error', 'Cette règle est déjà archivée.');
        }

        if ($rule->hasHistory()) {
            return redirect()
                ->route('access.commissions.rules')
                ->with('error', 'Règle utilisée, archivage requis')
                ->with('archive_rule_id', $rule->id);
        }

        $rule->delete();

        return redirect()
            ->route('access.commissions.rules')
            ->with('success', 'Règle supprimée.');
    }

    public function archiveRule(CommissionRule $rule)
    {
        if ($rule->isArchived()) {
            return redirect()
                ->route('access.commissions.rules')
                ->with('error', 'Cette règle est déjà archivée.');
        }

        if (! $rule->hasHistory()) {
            return redirect()
                ->route('access.commissions.rules')
                ->with('error', 'Sans historique, supprimez la règle plutôt que de l’archiver.');
        }

        $rule->update([
            'archived_at' => now(),
            'is_active' => false,
            'is_default' => false,
        ]);

        return redirect()
            ->route('access.commissions.rules')
            ->with('success', 'Règle archivée. L’historique des commissions est conservé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRule(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'collaborator_id' => [
                'nullable',
                Rule::exists('collaborators', 'id')->where(fn ($q) => $q->where('is_commercial', true)),
            ],
            'type' => ['required', Rule::in(array_keys(CommissionRule::TYPES))],
            'base' => ['required', Rule::in(array_keys(CommissionRule::BASES))],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'trigger' => ['required', Rule::in(array_keys(CommissionRule::TRIGGERS))],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function normalizeCollaboratorId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        return (int) $value;
    }

    private function syncDefaultFlag(CommissionRule $rule): void
    {
        if (! $rule->is_default) {
            return;
        }

        CommissionRule::query()
            ->where('id', '!=', $rule->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
