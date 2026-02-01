<?php

/**
 * Tests for the AcceptInvitation page UI and user interactions.
 *
 * These tests focus on the page rendering, form submission,
 * and error state handling for the invitation acceptance flow.
 */

use App\Actions\InviteUserAction;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['app.main_domain' => 'kasamahr.test']);
});

describe('AcceptInvitation Page', function () {
    it('renders with invitation details including user name and tenant name', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create(['name' => 'Acme Corporation']);
        $inviter = User::factory()->create();

        $inviteAction = new InviteUserAction;
        $user = $inviteAction->execute(
            email: 'newemployee@example.com',
            name: 'John Doe',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $response = $this->get(route('invitation.accept', ['token' => $tenantUser->invitation_token]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('auth/AcceptInvitation')
            ->where('user.name', 'John Doe')
            ->where('user.email', 'newemployee@example.com')
            ->where('tenant.name', 'Acme Corporation')
            ->has('token')
        );
    });

    it('displays validation errors when password confirmation does not match', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        $inviteAction = new InviteUserAction;
        $user = $inviteAction->execute(
            email: 'passwordtest@example.com',
            name: 'Password Test User',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $response = $this->post(route('invitation.accept.submit', ['token' => $tenantUser->invitation_token]), [
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'DifferentPassword456!',
        ]);

        $response->assertSessionHasErrors('password');
    });

    it('displays validation error when password is too short', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        $inviteAction = new InviteUserAction;
        $user = $inviteAction->execute(
            email: 'shortpassword@example.com',
            name: 'Short Password User',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $response = $this->post(route('invitation.accept.submit', ['token' => $tenantUser->invitation_token]), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    });

    it('redirects to login with error message for invalid token', function () {
        $response = $this->get(route('invitation.accept', ['token' => 'invalid-token-that-does-not-exist']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'This invitation link is invalid.');
    });

    it('redirects to login with error message for expired invitation', function () {
        $user = User::factory()->create([
            'password' => null,
            'email_verified_at' => null,
        ]);
        $tenant = Tenant::factory()->create();

        // Create expired invitation directly
        $expiredToken = 'expired-token-12345678901234567890123456789012345678901234567890';
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee,
            'invited_at' => now()->subDays(10),
            'invitation_token' => $expiredToken,
            'invitation_expires_at' => now()->subDays(3),
        ]);

        $response = $this->get(route('invitation.accept', ['token' => $expiredToken]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'This invitation link has expired. Please contact your administrator for a new invitation.');
    });
});
