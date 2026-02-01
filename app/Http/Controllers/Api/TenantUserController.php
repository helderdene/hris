<?php

namespace App\Http\Controllers\Api;

use App\Actions\InviteUserAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteUserRequest;
use App\Http\Requests\UpdateTenantUserRequest;
use App\Http\Resources\TenantUserResource;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\NewHireAccountSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TenantUserController extends Controller
{
    /**
     * List all users in the current tenant with their roles.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-users');

        $tenant = tenant();

        $users = $tenant->users()
            ->select(['users.id', 'users.name', 'users.email'])
            ->orderBy('users.name')
            ->get();

        return TenantUserResource::collection($users);
    }

    /**
     * Invite a new user to the current tenant.
     */
    public function invite(InviteUserRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-users');

        $tenant = tenant();
        $inviter = $request->user();

        $action = new InviteUserAction;
        $user = $action->execute(
            email: $request->validated('email'),
            name: $request->validated('name'),
            role: TenantUserRole::from($request->validated('role')),
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        // Reload user with pivot data for the resource
        $user = $tenant->users()->where('users.id', $user->id)->first();

        return (new TenantUserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a user's role in the current tenant.
     */
    public function update(UpdateTenantUserRequest $request, User $user): TenantUserResource
    {
        Gate::authorize('can-manage-users');

        $tenant = tenant();

        // Verify the user is a member of this tenant
        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($tenantUser === null) {
            abort(404, 'User is not a member of this tenant.');
        }

        // Update the role
        $tenantUser->update([
            'role' => $request->validated('role'),
        ]);

        // Reload user with updated pivot data for the resource
        $user = $tenant->users()->where('users.id', $user->id)->first();

        return new TenantUserResource($user);
    }

    /**
     * Remove a user from the current tenant.
     */
    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('can-manage-users');

        $tenant = tenant();
        $currentUser = request()->user();

        // Prevent admin from removing themselves
        if ($user->id === $currentUser->id) {
            abort(422, 'You cannot remove yourself from the tenant.');
        }

        // Verify the user is a member of this tenant
        $isMember = $tenant->users()->where('users.id', $user->id)->exists();

        if (! $isMember) {
            abort(404, 'User is not a member of this tenant.');
        }

        // Detach the user from the tenant
        $tenant->users()->detach($user->id);

        return response()->json([
            'message' => 'User removed from tenant successfully.',
        ]);
    }

    /**
     * Send account setup email to a user who hasn't set their password yet.
     *
     * This is used for new hires whose accounts were created automatically
     * when they were hired, but need HR to manually send the setup email.
     */
    public function sendAccountSetupEmail(User $user): JsonResponse
    {
        Gate::authorize('can-manage-users');

        $tenant = tenant();

        // Verify the user is a member of this tenant
        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($tenantUser === null) {
            abort(404, 'User is not a member of this tenant.');
        }

        // Check if user has already set their password
        if ($user->password !== null && $tenantUser->invitation_accepted_at !== null) {
            abort(422, 'This user has already set up their account.');
        }

        // Generate a new token if needed (in case old one expired)
        if ($tenantUser->invitation_token === null || $tenantUser->isInvitationExpired()) {
            $token = Str::random(64);
            $tenantUser->update([
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addDays(30),
            ]);
        } else {
            $token = $tenantUser->invitation_token;
        }

        // Send the account setup notification
        $user->notify(new NewHireAccountSetup($tenant, $token));

        return response()->json([
            'message' => 'Account setup email sent successfully.',
        ]);
    }
}
