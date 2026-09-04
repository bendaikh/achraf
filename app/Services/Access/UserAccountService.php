<?php

namespace App\Services\Access;

use App\Models\Collaborator;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessPermission;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class UserAccountService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $permissionIds
     * @param  list<int>  $warehouseIds
     */
    public function create(array $data, array $permissionIds = [], array $warehouseIds = []): User
    {
        return DB::transaction(function () use ($data, $permissionIds, $warehouseIds) {
            if (empty($data['password'])) {
                // Placeholder until invitation/activation flow (Phase auth)
                $data['password'] = str()->random(32);
            }

            $data['activated_at'] = $data['activated_at'] ?? now();
            $data['status'] = $data['status'] ?? User::STATUS_ACTIF;

            /** @var User $user */
            $user = User::query()->create($data);

            if (! empty($data['primary_role_id'])) {
                $role = Role::query()->find($data['primary_role_id']);
                if ($role) {
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }
            }

            $this->syncPermissionOverrides($user, $permissionIds);
            $user->warehouses()->sync($warehouseIds);

            // Keep legacy employee.user_id in sync when collaborator is a salarié
            $this->syncEmployeeUserLink($user);

            AccessPermission::forgetCache($user);

            ActivityLogger::log(
                'creation',
                'Création compte utilisateur '.$user->email,
                $user,
                null,
                ['email' => $user->email, 'role_id' => $user->primary_role_id, 'collaborator_id' => $user->collaborator_id],
            );

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $permissionIds  null = leave overrides unchanged
     * @param  list<int>|null  $warehouseIds
     */
    public function update(User $user, array $data, ?array $permissionIds = null, ?array $warehouseIds = null): User
    {
        return DB::transaction(function () use ($user, $data, $permissionIds, $warehouseIds) {
            $old = $user->only(['email', 'status', 'primary_role_id', 'data_scope', 'collaborator_id']);

            if (array_key_exists('password', $data)) {
                if (! filled($data['password'])) {
                    unset($data['password']);
                }
            }

            $user->update($data);

            if (! empty($data['primary_role_id'])) {
                $user->roles()->sync([$data['primary_role_id']]);
            }

            if ($permissionIds !== null) {
                $this->syncPermissionOverrides($user, $permissionIds);
            }

            if ($warehouseIds !== null) {
                $user->warehouses()->sync($warehouseIds);
            }

            $this->syncEmployeeUserLink($user->fresh());

            AccessPermission::forgetCache($user);

            ActivityLogger::log(
                'modification',
                'Modification compte utilisateur '.$user->email,
                $user,
                $old,
                $user->only(['email', 'status', 'primary_role_id', 'data_scope', 'collaborator_id']),
            );

            return $user->refresh();
        });
    }

    /**
     * Apply a role template then replace user overrides with the given absolute set.
     * Permissions present on the role but not in $permissionIds become deny overrides.
     * Permissions in $permissionIds but not on the role become grant overrides.
     *
     * @param  list<int>  $permissionIds
     */
    public function syncPermissionOverrides(User $user, array $permissionIds): void
    {
        $permissionIds = array_map('intval', $permissionIds);
        $rolePermissionIds = [];

        if ($user->primary_role_id) {
            $rolePermissionIds = Permission::query()
                ->whereHas('roles', fn ($q) => $q->where('roles.id', $user->primary_role_id))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $sync = [];
        foreach ($permissionIds as $id) {
            if (! in_array($id, $rolePermissionIds, true)) {
                $sync[$id] = ['granted' => true];
            }
        }
        foreach ($rolePermissionIds as $id) {
            if (! in_array($id, $permissionIds, true)) {
                $sync[$id] = ['granted' => false];
            }
        }

        $user->permissionOverrides()->sync($sync);
        AccessPermission::forgetCache($user);
    }

    private function syncEmployeeUserLink(User $user): void
    {
        if (! $user->collaborator_id) {
            return;
        }

        $collaborator = Collaborator::query()->find($user->collaborator_id);
        if (! $collaborator?->employee_id) {
            return;
        }

        // Clear previous employee link to this user if any
        \App\Models\Employee::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $collaborator->employee_id)
            ->update(['user_id' => null]);

        \App\Models\Employee::query()
            ->where('id', $collaborator->employee_id)
            ->update(['user_id' => $user->id]);
    }
}
