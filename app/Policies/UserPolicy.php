<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Only Super Admin 1 & Super Admin 2 may view staff management.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->isSuperAdmin();
    }

    /**
     * Hierarchy rule:
     * - Only Super Admin 1 and Super Admin 2 may delete accounts.
     * - Neither Super Admin can delete the other, nor themselves.
     * - Super Admins can delete Teacher and Support accounts.
     * - Teacher, Support, and Student can never delete anyone.
     */
    public function delete(User $actor, User $target): bool
    {
        if (! $actor->is_active || ! $actor->isSuperAdmin()) {
            return false;
        }

        if ($actor->id === $target->id) {
            return false;
        }

        if ($target->isSuperAdmin()) {
            return false;
        }

        if ($target->trashed()) {
            return false;
        }

        return $target->isTeacher() || $target->isSupport();
    }

    /**
     * Only Super Admins may reset Teacher or Support passwords via Staff Management.
     * Super Admins cannot reset each other's passwords from this screen.
     */
    public function resetPassword(User $actor, User $target): bool
    {
        if (! $actor->is_active || ! $actor->isSuperAdmin()) {
            return false;
        }

        if ($target->isSuperAdmin() || $target->trashed()) {
            return false;
        }

        return $target->isTeacher() || $target->isSupport();
    }
}
