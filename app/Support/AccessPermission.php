<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Contrôle des permissions applicatives (côté backend).
 * Le rôle est un modèle ; les overrides utilisateur priment.
 */
class AccessPermission
{
    public static function allows(?User $user, string $permissionKey): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isAccountActive()) {
            return false;
        }

        $granted = self::effectiveKeys($user);

        return in_array($permissionKey, $granted, true);
    }

    public static function allowsAny(?User $user, array $permissionKeys): bool
    {
        foreach ($permissionKeys as $key) {
            if (self::allows($user, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function effectiveKeys(User $user): array
    {
        $cacheKey = 'access_permissions.user.'.$user->id;

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $keys = [];

            $role = $user->primaryRole;
            if (! $role && $user->relationLoaded('roles')) {
                $role = $user->roles->first();
            }
            if (! $role) {
                $role = $user->roles()->first();
            }

            if ($role) {
                $role->loadMissing('permissions');
                $keys = $role->permissions->pluck('key')->all();
            }

            $overrides = $user->permissionOverrides()->get();
            foreach ($overrides as $permission) {
                $granted = (bool) $permission->pivot->granted;
                if ($granted) {
                    $keys[] = $permission->key;
                } else {
                    $keys = array_values(array_filter($keys, fn ($k) => $k !== $permission->key));
                }
            }

            return array_values(array_unique($keys));
        });
    }

    public static function forgetCache(User $user): void
    {
        Cache::forget('access_permissions.user.'.$user->id);
    }

    public static function canManageAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasRole('admin') || $user->hasRole('administrateur')) {
            return true;
        }

        return self::allows($user, 'sensible.gerer_utilisateurs')
            || self::allows($user, 'sensible.gerer_permissions')
            || self::allows($user, 'administration.utilisateurs.voir');
    }
}
