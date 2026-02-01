<?php

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('TenantUserRole Enum', function () {
    it('has all 6 tenant roles', function () {
        $roles = TenantUserRole::cases();

        expect($roles)->toHaveCount(6);
        expect(TenantUserRole::Admin->value)->toBe('admin');
        expect(TenantUserRole::HrManager->value)->toBe('hr_manager');
        expect(TenantUserRole::HrStaff->value)->toBe('hr_staff');
        expect(TenantUserRole::HrConsultant->value)->toBe('hr_consultant');
        expect(TenantUserRole::Supervisor->value)->toBe('supervisor');
        expect(TenantUserRole::Employee->value)->toBe('employee');
    });

    it('provides human-readable labels for each role', function () {
        expect(TenantUserRole::Admin->label())->toBe('Admin');
        expect(TenantUserRole::HrManager->label())->toBe('HR Manager');
        expect(TenantUserRole::HrStaff->label())->toBe('HR Staff');
        expect(TenantUserRole::HrConsultant->label())->toBe('HR Consultant');
        expect(TenantUserRole::Supervisor->label())->toBe('Supervisor');
        expect(TenantUserRole::Employee->label())->toBe('Employee');
    });

    it('validates roles correctly with isValid method', function () {
        expect(TenantUserRole::isValid('admin'))->toBeTrue();
        expect(TenantUserRole::isValid('hr_manager'))->toBeTrue();
        expect(TenantUserRole::isValid('hr_staff'))->toBeTrue();
        expect(TenantUserRole::isValid('hr_consultant'))->toBeTrue();
        expect(TenantUserRole::isValid('supervisor'))->toBeTrue();
        expect(TenantUserRole::isValid('employee'))->toBeTrue();
        expect(TenantUserRole::isValid('invalid_role'))->toBeFalse();
        expect(TenantUserRole::isValid('member'))->toBeFalse();
    });

    it('returns all role values as array', function () {
        $values = TenantUserRole::values();

        expect($values)->toBeArray();
        expect($values)->toContain('admin');
        expect($values)->toContain('hr_manager');
        expect($values)->toContain('hr_staff');
        expect($values)->toContain('hr_consultant');
        expect($values)->toContain('supervisor');
        expect($values)->toContain('employee');
    });
});

describe('TenantUser Pivot Model', function () {
    it('casts role to TenantUserRole enum', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->tenants()->attach($tenant->id, ['role' => 'admin']);

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser->role)->toBeInstanceOf(TenantUserRole::class);
        expect($tenantUser->role)->toBe(TenantUserRole::Admin);
    });

    it('supports invitation tracking fields', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $invitedAt = now();
        $expiresAt = now()->addDays(7);

        $user->tenants()->attach($tenant->id, [
            'role' => 'employee',
            'invited_at' => $invitedAt,
            'invitation_token' => 'test-token-123',
            'invitation_expires_at' => $expiresAt,
        ]);

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser->invited_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($tenantUser->invitation_token)->toBe('test-token-123');
        expect($tenantUser->invitation_expires_at)->toBeInstanceOf(\Carbon\Carbon::class);
        expect($tenantUser->invitation_accepted_at)->toBeNull();
    });

    it('detects pending invitation status correctly', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        // Pending invitation: invited but not accepted, not expired
        $user->tenants()->attach($tenant->id, [
            'role' => 'employee',
            'invited_at' => now(),
            'invitation_expires_at' => now()->addDays(7),
        ]);

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser->isInvitationPending())->toBeTrue();
        expect($tenantUser->hasAcceptedInvitation())->toBeFalse();
        expect($tenantUser->isInvitationExpired())->toBeFalse();
    });

    it('detects expired invitation status correctly', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        // Expired invitation
        $user->tenants()->attach($tenant->id, [
            'role' => 'employee',
            'invited_at' => now()->subDays(8),
            'invitation_expires_at' => now()->subDays(1),
        ]);

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser->isInvitationExpired())->toBeTrue();
        expect($tenantUser->isInvitationPending())->toBeFalse();
    });

    it('detects accepted invitation status correctly', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        // Accepted invitation
        $user->tenants()->attach($tenant->id, [
            'role' => 'employee',
            'invited_at' => now()->subDays(3),
            'invitation_accepted_at' => now()->subDays(2),
        ]);

        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser->hasAcceptedInvitation())->toBeTrue();
        expect($tenantUser->isInvitationPending())->toBeFalse();
    });
});

describe('User Model Role Methods', function () {
    it('returns correct role in tenant via getRoleInTenant', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->tenants()->attach($tenant->id, ['role' => 'hr_manager']);

        $role = $user->getRoleInTenant($tenant);

        expect($role)->toBeInstanceOf(TenantUserRole::class);
        expect($role)->toBe(TenantUserRole::HrManager);
    });

    it('returns null for non-member via getRoleInTenant', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        // User is not a member of this tenant
        $role = $user->getRoleInTenant($tenant);

        expect($role)->toBeNull();
    });

    it('correctly identifies admin via isAdminInTenant', function () {
        $adminUser = User::factory()->create();
        $regularUser = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $adminUser->tenants()->attach($tenant->id, ['role' => 'admin']);
        $regularUser->tenants()->attach($tenant->id, ['role' => 'employee']);

        expect($adminUser->isAdminInTenant($tenant))->toBeTrue();
        expect($regularUser->isAdminInTenant($tenant))->toBeFalse();
    });
});
