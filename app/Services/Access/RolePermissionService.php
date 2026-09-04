<?php

namespace App\Services\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Support\ActivityLogger;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolePermissionService
{
    public function syncCatalog(): int
    {
        $count = 0;
        foreach (PermissionCatalog::all() as $item) {
            Permission::query()->updateOrCreate(
                ['key' => $item['key']],
                [
                    'module' => $item['module'],
                    'resource' => $item['resource'],
                    'action' => $item['action'],
                    'label' => $item['label'],
                    'group_label' => $item['group_label'],
                    'is_sensitive' => $item['is_sensitive'],
                    'sort_order' => $item['sort_order'],
                ]
            );
            $count++;
        }

        return $count;
    }

    public function ensureBaseRoles(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'superadmin', 'description' => 'Accès complet au système', 'is_system' => true, 'sort_order' => 10],
            ['name' => 'Administrateur', 'slug' => 'administrateur', 'description' => 'Gestion globale selon permissions', 'is_system' => true, 'sort_order' => 20],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrateur (historique)', 'is_system' => true, 'sort_order' => 25],
            ['name' => 'Comptable', 'slug' => 'comptable', 'description' => 'Comptabilité, factures, règlements', 'is_system' => false, 'sort_order' => 30],
            ['name' => 'Responsable commercial', 'slug' => 'responsable-commercial', 'description' => 'Équipe commerciale, performances, commissions', 'is_system' => false, 'sort_order' => 40],
            ['name' => 'Commercial', 'slug' => 'commercial', 'description' => 'Clients, devis, commandes et suivi', 'is_system' => false, 'sort_order' => 50],
            ['name' => 'Magasinier / Stock', 'slug' => 'magasinier', 'description' => 'Stock, dépôts, BR/BL, inventaires', 'is_system' => false, 'sort_order' => 60],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Accès de base (historique)', 'is_system' => true, 'sort_order' => 90],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                array_merge($role, ['is_template' => true])
            );
        }
    }

    public function applyRoleTemplates(): void
    {
        $allKeys = Permission::query()->pluck('key')->all();
        $keyToId = Permission::query()->pluck('id', 'key')->all();

        foreach (PermissionCatalog::roleTemplates() as $slug => $patterns) {
            $role = Role::query()->where('slug', $slug)->first();
            if (! $role) {
                continue;
            }

            $patterns = is_array($patterns) ? $patterns : [$patterns];
            $keys = PermissionCatalog::expandPatterns($patterns, $allKeys);
            $ids = array_values(array_filter(array_map(fn ($k) => $keyToId[$k] ?? null, $keys)));
            $role->permissions()->sync($ids);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $permissionIds
     */
    public function createRole(array $data, array $permissionIds = []): Role
    {
        return DB::transaction(function () use ($data, $permissionIds) {
            $data['slug'] = $data['slug'] ?? Role::makeSlug($data['name']);
            $data['is_template'] = true;
            $data['is_system'] = false;

            $role = Role::query()->create($data);
            $role->permissions()->sync($permissionIds);

            ActivityLogger::log('creation', 'Création rôle '.$role->name, $role);

            return $role;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $permissionIds
     */
    public function updateRole(Role $role, array $data, ?array $permissionIds = null): Role
    {
        return DB::transaction(function () use ($role, $data, $permissionIds) {
            if ($role->is_system) {
                unset($data['slug'], $data['is_system']);
            } elseif (! empty($data['name']) && empty($data['slug'])) {
                $data['slug'] = Role::makeSlug($data['name']);
            }

            $old = $role->only(['name', 'description']);
            $role->update($data);

            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }

            ActivityLogger::log(
                'modification_role',
                'Modification rôle '.$role->name,
                $role,
                $old,
                $role->only(['name', 'description']),
            );

            return $role->refresh();
        });
    }

    public function duplicateRole(Role $role, string $name): Role
    {
        $permissionIds = $role->permissions()->pluck('permissions.id')->all();

        return $this->createRole([
            'name' => $name,
            'slug' => Role::makeSlug($name).'-'.Str::lower(Str::random(4)),
            'description' => 'Copie de '.$role->name,
            'sort_order' => ($role->sort_order ?? 0) + 1,
        ], $permissionIds);
    }
}
