<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Rules for acting on other user accounts.
 *
 * Admins never reach these methods: Gate::before (AppServiceProvider) lets
 * them through. These rules therefore describe what a NON-admin may do.
 */
class UserPolicy
{
    /**
     * Edit an account: needs "employees.edit" and the target must not be an
     * admin (prevents taking over an admin by changing email/password).
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('employees.edit') && ! $target->isAdmin();
    }

    /**
     * Delete an account: needs "employees.delete", never yourself, never an admin.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->hasPermission('employees.delete')
            && $actor->id !== $target->id
            && ! $target->isAdmin();
    }

    /**
     * Give a role to someone: only admins may grant the admin role.
     */
    public function assignRole(User $actor, ?Role $role = null): bool
    {
        return $role === null || ! $role->is_admin;
    }
}
