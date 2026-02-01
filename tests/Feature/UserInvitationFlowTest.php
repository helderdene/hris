<?php

use App\Actions\AcceptInvitationAction;
use App\Actions\InviteUserAction;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
});

describe('InviteUserAction', function () {
    it('creates user and pivot record with pending status', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        $action = new InviteUserAction;
        $user = $action->execute(
            email: 'newuser@example.com',
            name: 'New User',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        expect($user)->toBeInstanceOf(User::class);
        expect($user->email)->toBe('newuser@example.com');
        expect($user->name)->toBe('New User');
        expect($user->password)->toBeNull();
        expect($user->email_verified_at)->toBeNull();

        // Check pivot record
        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser)->not->toBeNull();
        expect($tenantUser->role)->toBe(TenantUserRole::Employee);
        expect($tenantUser->invited_at)->not->toBeNull();
        expect($tenantUser->invitation_accepted_at)->toBeNull();
        expect($tenantUser->isInvitationPending())->toBeTrue();
    });

    it('generates secure 64-character invitation token', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        $action = new InviteUserAction;
        $user = $action->execute(
            email: 'tokentest@example.com',
            name: 'Token Test',
            role: TenantUserRole::HrStaff,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser->invitation_token)->not->toBeNull();
        expect(strlen($tenantUser->invitation_token))->toBe(64);
    });

    it('sends email notification when user is invited', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        $action = new InviteUserAction;
        $user = $action->execute(
            email: 'notifytest@example.com',
            name: 'Notify Test',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        Notification::assertSentTo($user, UserInvitation::class);
    });

    it('sets invitation expiration to 7 days from now', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        $action = new InviteUserAction;
        $user = $action->execute(
            email: 'expiretest@example.com',
            name: 'Expire Test',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser->invitation_expires_at)->not->toBeNull();
        // Check that expiration is approximately 7 days from now
        $expectedExpiration = now()->addDays(7);
        expect($tenantUser->invitation_expires_at->isSameDay($expectedExpiration))->toBeTrue();
    });
});

describe('AcceptInvitationAction', function () {
    it('sets password and clears token when accepting invitation', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        // Create invitation
        $inviteAction = new InviteUserAction;
        $user = $inviteAction->execute(
            email: 'accept@example.com',
            name: 'Accept Test',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();
        $token = $tenantUser->invitation_token;

        // Accept invitation
        $acceptAction = new AcceptInvitationAction;
        $acceptedUser = $acceptAction->execute(
            token: $token,
            password: 'SecurePassword123!'
        );

        expect($acceptedUser->id)->toBe($user->id);

        // Verify password is set
        expect(Hash::check('SecurePassword123!', $acceptedUser->password))->toBeTrue();

        // Verify email is verified
        expect($acceptedUser->email_verified_at)->not->toBeNull();

        // Verify pivot is updated
        $tenantUser->refresh();
        expect($tenantUser->invitation_accepted_at)->not->toBeNull();
        expect($tenantUser->invitation_token)->toBeNull();
        expect($tenantUser->invitation_expires_at)->toBeNull();
        expect($tenantUser->hasAcceptedInvitation())->toBeTrue();
    });

    it('throws exception for expired invitation token', function () {
        $user = User::factory()->create([
            'password' => null,
            'email_verified_at' => null,
        ]);
        $tenant = Tenant::factory()->create();

        // Create expired invitation directly
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee,
            'invited_at' => now()->subDays(8),
            'invitation_token' => 'expired-token-12345678901234567890123456789012345678901234567890',
            'invitation_expires_at' => now()->subDays(1),
        ]);

        $acceptAction = new AcceptInvitationAction;

        expect(fn () => $acceptAction->execute(
            token: 'expired-token-12345678901234567890123456789012345678901234567890',
            password: 'SecurePassword123!'
        ))->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('throws exception for invalid token', function () {
        $acceptAction = new AcceptInvitationAction;

        expect(fn () => $acceptAction->execute(
            token: 'nonexistent-token-12345678901234567890123456789012345678901234',
            password: 'SecurePassword123!'
        ))->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});

describe('Invitation Controller', function () {
    it('displays set password form for valid token', function () {
        $this->withoutVite();
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        $inviteAction = new InviteUserAction;
        $user = $inviteAction->execute(
            email: 'showform@example.com',
            name: 'Show Form Test',
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
            ->has('user')
            ->has('tenant')
            ->has('token')
        );
    });

    it('accepts invitation and redirects to login', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $inviter = User::factory()->create();

        $inviteAction = new InviteUserAction;
        $user = $inviteAction->execute(
            email: 'acceptform@example.com',
            name: 'Accept Form Test',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $inviter->id
        );

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $response = $this->post(route('invitation.accept.submit', ['token' => $tenantUser->invitation_token]), [
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Should redirect to tenant subdomain with auth token
        $response->assertRedirectContains($tenant->slug.'.kasamahr.test');

        // Verify user password was set
        $user->refresh();
        expect(Hash::check('SecurePassword123!', $user->password))->toBeTrue();

        // Verify user is logged in
        $this->assertAuthenticatedAs($user);

        // Verify redirect token was created
        expect(\App\Models\TenantRedirectToken::where('user_id', $user->id)->where('tenant_id', $tenant->id)->exists())->toBeTrue();
    });
});
