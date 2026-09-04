<?php

namespace Database\Seeders;

use App\Services\Access\RolePermissionService;
use Illuminate\Database\Seeder;

class AccessManagementSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(RolePermissionService::class);
        $service->syncCatalog();
        $service->ensureBaseRoles();
        $service->applyRoleTemplates();

        // Align existing users with primary_role_id when missing
        \App\Models\User::query()->each(function (\App\Models\User $user) {
            if ($user->primary_role_id) {
                return;
            }
            $role = $user->roles()->orderBy('roles.id')->first();
            if ($role) {
                $user->forceFill([
                    'primary_role_id' => $role->id,
                    'status' => $user->status ?: 'actif',
                    'data_scope' => $user->data_scope ?: ($user->isSuperAdmin() ? 'all' : 'own'),
                    'activated_at' => $user->activated_at ?: now(),
                ])->save();
            }
        });
    }
}
