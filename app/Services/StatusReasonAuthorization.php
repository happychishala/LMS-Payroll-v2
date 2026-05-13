<?php

namespace App\Services;

use App\Models\User;

class StatusReasonAuthorization
{
    public static function canAssign(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('update_loan')
            || static::hasRoleLike($user, ['officer', 'supervisor', 'manager', 'admin']);
    }

    public static function canRemove(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('delete_loan')
            || $user->can('update_loan')
            || static::hasRoleLike($user, ['supervisor', 'manager', 'admin']);
    }

    public static function canImport(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('update_loan')
            || static::hasRoleLike($user, ['officer', 'supervisor', 'manager', 'admin']);
    }

    protected static function hasRoleLike(User $user, array $needles): bool
    {
        $roles = $user->getRoleNames()->map(fn (string $role): string => strtolower($role));

        foreach ($roles as $role) {
            foreach ($needles as $needle) {
                if (str_contains($role, strtolower($needle))) {
                    return true;
                }
            }
        }

        return false;
    }
}
