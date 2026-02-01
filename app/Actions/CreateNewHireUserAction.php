<?php

namespace App\Actions;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates a user account for a newly hired candidate.
 *
 * Similar to InviteUserAction but does NOT send the invitation email.
 * HR can manually trigger the email later.
 */
class CreateNewHireUserAction
{
    /**
     * Create a user account for a new hire.
     *
     * Creates the user with null password, links them to the tenant
     * with the 'employee' role, and generates an invitation token.
     * Does NOT send any notification - HR will trigger that manually.
     *
     * @return array{user: User, token: string, is_new: bool}
     */
    public function execute(
        string $email,
        string $name,
        Tenant $tenant
    ): array {
        // Find or create the user
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => null,
                'email_verified_at' => null,
            ]
        );

        $isNew = $user->wasRecentlyCreated;

        // If user exists but was found (not created), update the name if different
        if (! $isNew && $user->name !== $name) {
            $user->update(['name' => $name]);
        }

        // Generate secure invitation token (64 characters)
        $token = Str::random(64);

        // Create or update the tenant membership with invitation details
        $user->tenants()->syncWithoutDetaching([
            $tenant->id => [
                'role' => TenantUserRole::Employee->value,
                'invited_at' => now(),
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addDays(30), // Longer expiry for new hires
                'invitation_accepted_at' => null,
            ],
        ]);

        return [
            'user' => $user->fresh(),
            'token' => $token,
            'is_new' => $isNew,
        ];
    }
}
