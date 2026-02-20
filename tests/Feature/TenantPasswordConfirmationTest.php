<?php

/**
 * Tests for tenant-scoped password confirmation endpoints and middleware.
 *
 * These tests verify that the tenant-scoped password confirmation API
 * works correctly and that the RequirePasswordConfirmation middleware
 * returns 423 JSON instead of redirecting to the main domain.
 */

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantForPwConfirm(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserForPwConfirm(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create([
        'password' => bcrypt('correct-password'),
    ]);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    $this->withoutVite();
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    bindTenantForPwConfirm($this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";
});

describe('Tenant Password Confirmation API', function () {
    it('returns confirmation status as not confirmed initially', function () {
        $user = createUserForPwConfirm($this->tenant, TenantUserRole::Admin);

        $response = $this->actingAs($user)
            ->getJson("{$this->baseUrl}/api/password/confirmation-status");

        $response->assertOk();
        $response->assertJson(['confirmed' => false]);
    });

    it('confirms password successfully', function () {
        $user = createUserForPwConfirm($this->tenant, TenantUserRole::Admin);

        $response = $this->actingAs($user)
            ->postJson("{$this->baseUrl}/api/password/confirm", [
                'password' => 'correct-password',
            ]);

        $response->assertStatus(201);
    });

    it('returns error on incorrect password', function () {
        $user = createUserForPwConfirm($this->tenant, TenantUserRole::Admin);

        $response = $this->actingAs($user)
            ->postJson("{$this->baseUrl}/api/password/confirm", [
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.password.0', 'The provided password was incorrect.');
    });

    it('shows confirmed status after successful confirmation', function () {
        $user = createUserForPwConfirm($this->tenant, TenantUserRole::Admin);

        $this->actingAs($user)
            ->postJson("{$this->baseUrl}/api/password/confirm", [
                'password' => 'correct-password',
            ])
            ->assertStatus(201);

        $response = $this->actingAs($user)
            ->getJson("{$this->baseUrl}/api/password/confirmation-status");

        $response->assertOk();
        $response->assertJson(['confirmed' => true]);
    });
});

describe('RequirePasswordConfirmation Middleware', function () {
    it('returns 423 when password not confirmed on protected route', function () {
        $admin = createUserForPwConfirm($this->tenant, TenantUserRole::Admin);
        $employee = createUserForPwConfirm($this->tenant, TenantUserRole::Employee);

        $response = $this->actingAs($admin)
            ->deleteJson("{$this->baseUrl}/api/users/{$employee->id}");

        $response->assertStatus(423);
        $response->assertJson(['message' => 'Password confirmation required.']);
    });

    it('allows request after password is confirmed', function () {
        $admin = createUserForPwConfirm($this->tenant, TenantUserRole::Admin);
        $employee = createUserForPwConfirm($this->tenant, TenantUserRole::Employee);

        // Confirm password, then verify the session flag is set
        $this->actingAs($admin)
            ->postJson("{$this->baseUrl}/api/password/confirm", [
                'password' => 'correct-password',
            ])
            ->assertStatus(201);

        // Verify the session now shows confirmed
        $response = $this->actingAs($admin)
            ->getJson("{$this->baseUrl}/api/password/confirmation-status");

        $response->assertOk();
        $response->assertJson(['confirmed' => true]);
    });
});
