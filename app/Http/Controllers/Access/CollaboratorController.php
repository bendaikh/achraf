<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\Employee;
use App\Services\Access\CollaboratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollaboratorController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected CollaboratorService $collaborators,
    ) {}

    public function index(Request $request)
    {
        $query = Collaborator::query()->with(['employee', 'user', 'manager']);

        $this->applyTableSearch($query, $request, [
            'first_name', 'last_name', 'email', 'phone', 'job_title', 'department',
        ]);
        $this->applyTableFilter($query, $request, 'type', 'type');
        $this->applyTableFilter($query, $request, 'status', 'status');
        if ($request->boolean('commercials_only')) {
            $query->where('is_commercial', true);
        }
        $this->applyTableSort($query, $request, [
            'last_name' => 'last_name',
            'type' => 'type',
            'start_date' => 'start_date',
            'created_at' => 'created_at',
        ], 'last_name', 'asc');

        return view('access.collaborators.index', [
            'collaborators' => $this->paginateTable($query, $request),
        ]);
    }

    public function create()
    {
        return view('access.collaborators.create', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPayload($request);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('collaborators/photos', 'public');
        }

        $collaborator = $this->collaborators->create($validated);

        return redirect()
            ->route('access.collaborators.show', $collaborator)
            ->with('success', 'Collaborateur créé.');
    }

    public function show(Collaborator $collaborator, Request $request)
    {
        $collaborator->load(['employee.department', 'user.primaryRole', 'manager']);

        return view('access.collaborators.show', [
            'collaborator' => $collaborator,
            'tab' => $request->input('tab', 'profil'),
        ]);
    }

    public function edit(Collaborator $collaborator)
    {
        return view('access.collaborators.edit', $this->formData([
            'collaborator' => $collaborator,
        ]));
    }

    public function update(Request $request, Collaborator $collaborator)
    {
        $validated = $this->validatedPayload($request, $collaborator);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $this->collaborators->storePhoto($collaborator, $request->file('photo'));
        }

        $this->collaborators->update($collaborator, $validated);

        return redirect()
            ->route('access.collaborators.show', $collaborator)
            ->with('success', 'Fiche collaborateur mise à jour.');
    }

    public function syncFromHr()
    {
        $count = $this->collaborators->syncFromEmployees();

        return redirect()
            ->route('access.collaborators.index')
            ->with('success', $count === 0
                ? 'Tous les salariés RH sont déjà liés à un collaborateur.'
                : "{$count} collaborateur(s) créé(s) depuis les fiches RH existantes (aucune duplication).");
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formData(array $extra = []): array
    {
        $excludeEmployeeId = isset($extra['collaborator'])
            ? $extra['collaborator']->employee_id
            : null;

        $availableEmployees = Employee::query()
            ->with('department')
            ->where(function ($q) use ($excludeEmployeeId) {
                $q->whereDoesntHave('collaborator');
                if ($excludeEmployeeId) {
                    $q->orWhere('id', $excludeEmployeeId);
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return array_merge([
            'availableEmployees' => $availableEmployees,
            'managers' => Collaborator::query()
                ->where('status', Collaborator::STATUS_ACTIF)
                ->when(isset($extra['collaborator']), fn ($q) => $q->where('id', '!=', $extra['collaborator']->id))
                ->orderBy('last_name')
                ->get(),
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?Collaborator $collaborator = null): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Collaborator::TYPES))],
            'last_name' => ['required', 'string', 'max:120'],
            'first_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'job_title' => ['nullable', 'string', 'max:190'],
            'department' => ['nullable', 'string', 'max:190'],
            'team' => ['nullable', 'string', 'max:190'],
            'manager_id' => ['nullable', 'exists:collaborators,id'],
            'employee_id' => [
                'nullable',
                'exists:employees,id',
                Rule::unique('collaborators', 'employee_id')->ignore($collaborator?->id),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(array_keys(Collaborator::STATUSES))],
            'is_commercial' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $validated['is_commercial'] = $request->boolean('is_commercial');

        if (($validated['type'] ?? null) !== Collaborator::TYPE_SALARIE) {
            $validated['employee_id'] = null;
        }

        return $validated;
    }
}
