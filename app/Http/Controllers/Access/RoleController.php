<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Access\RolePermissionService;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected RolePermissionService $roles,
    ) {}

    public function index(Request $request)
    {
        $query = Role::query()->withCount(['permissions', 'primaryUsers']);

        $this->applyTableSearch($query, $request, ['name', 'slug', 'description']);
        $this->applyTableSort($query, $request, [
            'name' => 'name',
            'sort_order' => 'sort_order',
        ], 'sort_order', 'asc');

        return view('access.roles.index', [
            'roles' => $this->paginateTable($query, $request),
        ]);
    }

    public function create()
    {
        return view('access.roles.create', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:roles,name'],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = $this->roles->createRole(
            [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'sort_order' => 100,
            ],
            array_map('intval', $validated['permissions'] ?? [])
        );

        return redirect()
            ->route('access.roles.show', $role)
            ->with('success', 'Rôle créé. Il servira de modèle ; chaque utilisateur pourra être personnalisé ensuite.');
    }

    public function show(Role $role)
    {
        $role->load('permissions');

        return view('access.roles.show', $this->formData([
            'role' => $role,
            'selectedPermissionIds' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ]));
    }

    public function edit(Role $role)
    {
        $role->load('permissions');

        return view('access.roles.edit', $this->formData([
            'role' => $role,
            'selectedPermissionIds' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ]));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $this->roles->updateRole(
            $role,
            [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ],
            array_map('intval', $validated['permissions'] ?? [])
        );

        return redirect()
            ->route('access.roles.show', $role)
            ->with('success', 'Rôle mis à jour.');
    }

    public function duplicate(Role $role)
    {
        $copy = $this->roles->duplicateRole($role, $role->name.' (copie)');

        return redirect()
            ->route('access.roles.edit', $copy)
            ->with('success', 'Rôle dupliqué. Vous pouvez le personnaliser.');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formData(array $extra = []): array
    {
        return array_merge([
            'permissionsByModule' => Permission::query()->orderBy('sort_order')->get()->groupBy('module'),
            'modules' => PermissionCatalog::MODULES,
            'actions' => PermissionCatalog::ACTIONS,
        ], $extra);
    }
}
