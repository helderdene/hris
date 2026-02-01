<?php

namespace App\Actions;

use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AcceptInvitationAction
{
    /**
     * Accept an invitation by setting the user's password.
     *
     * Validates the token exists and is not expired,
     * sets the user's password and email verification,
     * and clears the invitation token.
     *
     * @throws ValidationException
     */
    public function execute(string $token, string $password): User
    {
        // Find the tenant user record by token
        $tenantUser = TenantUser::where('invitation_token', $token)->first();

        if ($tenantUser === null) {
            throw ValidationException::withMessages([
                'token' => ['This invitation link is invalid.'],
            ]);
        }

        // Check if invitation has expired
        if ($tenantUser->isInvitationExpired()) {
            throw ValidationException::withMessages([
                'token' => ['This invitation link has expired. Please contact your administrator for a new invitation.'],
            ]);
        }

        // Get the user
        $user = $tenantUser->user;

        // Set the user's password (will be automatically hashed via cast)
        $user->forceFill([
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ])->save();

        // Update the pivot record to mark invitation as accepted
        $tenantUser->update([
            'invitation_accepted_at' => now(),
            'invitation_token' => null,
            'invitation_expires_at' => null,
        ]);

        return $user->fresh();
    }
}
