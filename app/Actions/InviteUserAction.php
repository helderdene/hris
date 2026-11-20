<?php

namespace App\Actions;

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Str;

class InviteUserAction
{
    /**
     * Invite a user to a tenant.
     *
     * Creates a new user if one doesn't exist with the given email,
     * creates the tenant membership with invitation fields,
     * and sends an invitation notification.
     */
    public function execute(
        string $email,
        string $name,
        TenantUserRole $role,
        int $tenantId,
        int $inviterId,
        ?int $employeeId = null,
    ): User {
        // Find or create the user
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => null,
                'email_verified_at' => null,
            ]
        );

        // If user exists but was found (not created), update the name if different
        if (! $user->wasRecentlyCreated && $user->name !== $name) {
            $user->update(['name' => $name]);
        }

        // Generate secure invitation token (64 characters)
        $token = Str::random(64);

        // Create or update the tenant membership with invitation details
        $user->tenants()->syncWithoutDetaching([
            $tenantId => [
                'role' => $role->value,
                'invited_at' => now(),
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addDays(7),
                'invitation_accepted_at' => null,
            ],
        ]);

        // Get tenant and inviter for notification
        $tenant = Tenant::findOrFail($tenantId);
        $inviter = User::findOrFail($inviterId);

        // No employee picked: link the one unlinked employee with the invited email, if any
        if ($employeeId === null && $user->ownsEmail() && $this->tenantHasEmployees() && ! Employee::where('user_id', $user->id)->exists()) {
            $matches = Employee::query()
                ->whereNull('user_id')
                ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
                ->pluck('id');

            $employeeId = $matches->count() === 1 ? $matches->first() : null;
        }

        // Link employee to user if provided
        if ($employeeId !== null) {
            Employee::where('id', $employeeId)
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);
        }

        // Send invitation notification
        $user->notify(new UserInvitation($tenant, $inviter, $token));

        return $user;
    }

    /**
     * Whether the tenant's employee table exists yet (it does not while a tenant is still being provisioned).
     */
    private function tenantHasEmployees(): bool
    {
        $employee = new Employee;

        return $employee->getConnection()->getSchemaBuilder()->hasTable($employee->getTable());
    }
}
