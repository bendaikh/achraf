<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Access\UserAccountService;
use App\Support\AccessPermission;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserAccountController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected UserAccountService $accounts,
    ) {}

    public function index(Request $request)
    {
        $query = User::query()->with(['primaryRole', 'collaborator']);

        $this->applyTableSearch($query, $request, ['name', 'email']);
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableFilter($query, $request, 'primary_role_id', 'primary_role_id');
        $this->applyTableSort($query, $request, [
            'name' => 'name',
            'email' => 'email',
            'last_login_at' => 'last_login_at',
            'created_at' => 'created_at',
        ], 'name', 'asc');

        return view('access.users.index', [
            'users' => $this->paginateTable($query, $request),
            'roles' => Role::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('access.users.create', $this->formData([
            'prefillCollaboratorId' => $request->integer('collaborator_id') ?: null,
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPayload($request);
        $permissionIds = array_map('intval', $request->input('permissions', []));
        $warehouseIds = array_map('intval', $request->input('warehouses', []));

        $user = $this->accounts->create($validated, $permissionIds, $warehouseIds);

        return redirect()
            ->route('access.users.show', $user)
            ->with('success', 'Compte utilisateur créé. L\'authentification avancée (invitation, 2FA) sera branchée ultérieurement.');
    }

    public function show(User $user, Request $request)
    {
        $user->load(['primaryRole', 'collaborator.employee', 'permissionOverrides', 'warehouses']);

        return view('access.users.show', array_merge($this->formData([
            'user' => $user,
            'tab' => $request->input('tab', 'compte'),
            'effectiveKeys' => AccessPermission::effectiveKeys($user),
        ])));
    }

    public function edit(User $user)
    {
        $user->load(['permissionOverrides', 'warehouses']);

        return view('access.users.edit', $this->formData([
            'user' => $user,
            'selectedPermissionIds' => $this->selectedPermissionIds($user),
        ]));
    }

    public function update(Request $request, User $user)
    {
        $validated = $this->validatedPayload($request, $user);
        $permissionIds = array_map('intval', $request->input('permissions', []));
        $warehouseIds = array_map('intval', $request->input('warehouses', []));

        $this->accounts->update($user, $validated, $permissionIds, $warehouseIds);

        return redirect()
            ->route('access.users.show', $user)
            ->with('success', 'Compte utilisateur mis à jour.');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formData(array $extra = []): array
    {
        $excludeCollaboratorId = isset($extra['user'])
            ? $extra['user']->collaborator_id
            : null;

        $availableCollaborators = Collaborator::query()
            ->where(function ($q) use ($excludeCollaboratorId) {
                $q->whereDoesntHave('user');
                if ($excludeCollaboratorId) {
                    $q->orWhere('id', $excludeCollaboratorId);
                }
            })
            ->orderBy('last_name')
            ->get();

        $permissions = Permission::query()->orderBy('sort_order')->get()->groupBy('module');

        $rolePermissionMap = Role::query()
            ->with('permissions:id')
            ->get()
            ->mapWithKeys(fn (Role $role) => [
                $role->id => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ])
            ->all();

        return array_merge([
            'roles' => Role::query()->orderBy('sort_order')->orderBy('name')->get(),
            'availableCollaborators' => $availableCollaborators,
            'permissionsByModule' => $permissions,
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
            'modules' => PermissionCatalog::MODULES,
            'dataScopes' => PermissionCatalog::DATA_SCOPES,
            'actions' => PermissionCatalog::ACTIONS,
            'rolePermissionMap' => $rolePermissionMap,
        ], $extra);
    }

    /**
     * @return list<int>
     */
    private function selectedPermissionIds(User $user): array
    {
        $effective = AccessPermission::effectiveKeys($user);
        $map = Permission::query()->whereIn('key', $effective)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'collaborator_id' => [
                'nullable',
                'exists:collaborators,id',
                Rule::unique('users', 'collaborator_id')->ignore($user?->id),
            ],
            'primary_role_id' => ['required', 'exists:roles,id'],
            'status' => ['required', Rule::in(array_keys(User::STATUSES))],
            'data_scope' => ['required', Rule::in(array_keys(PermissionCatalog::DATA_SCOPES))],
        ]);
    }
}
