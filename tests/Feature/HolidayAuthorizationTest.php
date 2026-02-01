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

describe('Holiday Authorization', function () {
    it('allows HR Manager to manage holidays', function () {
        $hrManager = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'hr-manager-tenant']);

        // Make user an HR Manager of the tenant
        $hrManager->tenants()->attach($tenant->id, ['role' => 'hr_manager']);

        // Bind tenant context
        app()->instance('tenant', $tenant);

        // HR Manager should be able to manage holidays
        expect(Gate::forUser($hrManager)->allows('can-manage-holidays'))->toBeTrue();
    });

    it('allows HR Staff to manage holidays', function () {
        $hrStaff = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'hr-staff-tenant']);

        // Make user an HR Staff of the tenant
        $hrStaff->tenants()->attach($tenant->id, ['role' => 'hr_staff']);

        // Bind tenant context
        app()->instance('tenant', $tenant);

        // HR Staff should be able to manage holidays
        expect(Gate::forUser($hrStaff)->allows('can-manage-holidays'))->toBeTrue();
    });

    it('denies regular employee from managing holidays', function () {
        $employee = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'employee-tenant']);

        // Make user a regular employee of the tenant
        $employee->tenants()->attach($tenant->id, ['role' => 'employee']);

        // Bind tenant context
        app()->instance('tenant', $tenant);

        // Employee should not be able to manage holidays (create/edit/delete)
        expect(Gate::forUser($employee)->allows('can-manage-holidays'))->toBeFalse();
    });

    it('allows all authenticated tenant users to view holidays', function () {
        $employee = User::factory()->create();
        $supervisor = User::factory()->create();
        $hrManager = User::factory()->create();
        $tenant = Tenant::factory()->create(['slug' => 'view-tenant']);

        // Assign various roles to users
        $employee->tenants()->attach($tenant->id, ['role' => 'employee']);
        $supervisor->tenants()->attach($tenant->id, ['role' => 'supervisor']);
        $hrManager->tenants()->attach($tenant->id, ['role' => 'hr_manager']);

        // Bind tenant context
        app()->instance('tenant', $tenant);

        // All authenticated tenant users should be able to view holidays (read-only)
        // This is verified through the tenant-member gate since viewing holidays
        // only requires being a tenant member
        expect(Gate::forUser($employee)->allows('tenant-member'))->toBeTrue();
        expect(Gate::forUser($supervisor)->allows('tenant-member'))->toBeTrue();
        expect(Gate::forUser($hrManager)->allows('tenant-member'))->toBeTrue();
    });
});
