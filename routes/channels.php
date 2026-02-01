<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Tenant-scoped Attendance Channel
|--------------------------------------------------------------------------
|
| Authorizes users to receive real-time attendance updates for their tenant.
| Only authenticated users belonging to the specific tenant can subscribe.
|
*/
Broadcast::channel('tenant.{tenantId}.attendance', function ($user, $tenantId) {
    // Get the user's tenant membership
    $tenantUser = $user->tenants()->where('tenants.id', $tenantId)->first();

    return $tenantUser !== null;
});

/*
|--------------------------------------------------------------------------
| Tenant-scoped Action Center Channel
|--------------------------------------------------------------------------
|
| Authorizes users to receive real-time action center updates for their tenant.
| Only authenticated users belonging to the specific tenant can subscribe.
|
*/
Broadcast::channel('tenant.{tenantId}.action-center', function ($user, $tenantId) {
    // Get the user's tenant membership
    $tenantUser = $user->tenants()->where('tenants.id', $tenantId)->first();

    if ($tenantUser === null) {
        return false;
    }

    // Only HR and management roles should receive action center updates
    // Note: role is cast to enum in the pivot model, so compare enum objects directly
    $role = $tenantUser->pivot->role ?? null;

    return in_array($role, [
        \App\Enums\TenantUserRole::Admin,
        \App\Enums\TenantUserRole::HrManager,
        \App\Enums\TenantUserRole::HrStaff,
        \App\Enums\TenantUserRole::Supervisor,
    ], true);
});
