<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up the main domain for the tests
    config(['app.main_domain' => 'kasamahr.test']);
});

it('allows super admin to access all tenants via gate', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $tenant1 = Tenant::factory()->create(['slug' => 'tenant-one']);
    $tenant2 = Tenant::factory()->create(['slug' => 'tenant-two']);

    // Super admin is not a member of these tenants
    // But should pass super-admin gate
    expect(Gate::forUser($superAdmin)->allows('super-admin'))->toBeTrue();

    // Bind tenant and check access
    app()->instance('tenant', $tenant1);
    expect(Gate::forUser($superAdmin)->allows('tenant-member'))->toBeTrue();

    app()->instance('tenant', $tenant2);
    expect(Gate::forUser($superAdmin)->allows('tenant-member'))->toBeTrue();
});

it('allows tenant admin to access only their tenant via gate', function () {
    $tenantAdmin = User::factory()->create();

    $ownTenant = Tenant::factory()->create(['slug' => 'own-tenant']);
    $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant']);

    // Make user an admin of their own tenant
    $tenantAdmin->tenants()->attach($ownTenant->id, ['role' => 'admin']);

    // Regular user should not pass super-admin gate
    expect(Gate::forUser($tenantAdmin)->allows('super-admin'))->toBeFalse();

    // Should pass tenant-admin gate for own tenant
    app()->instance('tenant', $ownTenant);
    expect(Gate::forUser($tenantAdmin)->allows('tenant-admin'))->toBeTrue();
    expect(Gate::forUser($tenantAdmin)->allows('tenant-member'))->toBeTrue();

    // Should not pass tenant-admin gate for other tenant
    app()->instance('tenant', $otherTenant);
    expect(Gate::forUser($tenantAdmin)->allows('tenant-admin'))->toBeFalse();
    expect(Gate::forUser($tenantAdmin)->allows('tenant-member'))->toBeFalse();
});

it('denies non-member access to tenant subdomain via gate', function () {
    $nonMember = User::factory()->create();

    $tenant = Tenant::factory()->create(['slug' => 'restricted-tenant']);

    // User is not a member of this tenant
    app()->instance('tenant', $tenant);

    expect(Gate::forUser($nonMember)->allows('tenant-member'))->toBeFalse();
    expect(Gate::forUser($nonMember)->allows('tenant-admin'))->toBeFalse();
});

it('allows tenant member with employee role to access tenant but not admin functions', function () {
    $tenantMember = User::factory()->create();

    $tenant = Tenant::factory()->create(['slug' => 'member-tenant']);

    // Make user a regular employee (not admin)
    $tenantMember->tenants()->attach($tenant->id, ['role' => 'employee']);

    app()->instance('tenant', $tenant);

    // Should pass tenant-member gate
    expect(Gate::forUser($tenantMember)->allows('tenant-member'))->toBeTrue();

    // Should not pass tenant-admin gate
    expect(Gate::forUser($tenantMember)->allows('tenant-admin'))->toBeFalse();
});

it('super admin passes all tenant gates regardless of membership', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $tenant = Tenant::factory()->create(['slug' => 'any-tenant']);

    // Super admin is not explicitly a member
    app()->instance('tenant', $tenant);

    expect(Gate::forUser($superAdmin)->allows('super-admin'))->toBeTrue();
    expect(Gate::forUser($superAdmin)->allows('tenant-admin'))->toBeTrue();
    expect(Gate::forUser($superAdmin)->allows('tenant-member'))->toBeTrue();
});
