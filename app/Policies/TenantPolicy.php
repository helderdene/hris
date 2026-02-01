<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Only Super Admins can view the list of all tenants.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the model.
     *
     * Super Admins can view any tenant.
     * Tenant members can view their own tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenants()->where('tenants.id', $tenant->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     *
     * Any authenticated user can create a new tenant (self-service registration).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * Super Admins can update any tenant.
     * Tenant Admins can update their own tenant.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $membership = $user->tenants()->where('tenants.id', $tenant->id)->first();

        return $membership !== null && $membership->pivot->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Only Super Admins can delete tenants.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     *
     * Only Super Admins can restore tenants.
     */
    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * Only Super Admins can permanently delete tenants.
     */
    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }
}
