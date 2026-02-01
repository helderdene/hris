<?php

/**
 * Tests for the Enhanced Tenant Selector
 *
 * These tests verify that the tenant selector properly displays
 * user roles with human-readable labels and appropriate styling data.
 */

use App\Http\Controllers\TenantSelectorController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

describe('Enhanced Tenant Selector Role Labels', function () {
    it('includes human-readable role labels in tenant data', function () {
        $user = User::factory()->withoutTwoFactor()->create();
        $tenant = Tenant::factory()->create([
            'name' => 'Acme Corporation',
            'slug' => 'acme',
        ]);

        $user->tenants()->attach($tenant, ['role' => 'hr_manager']);

        $request = Request::create('/select-tenant', 'GET');
        $request->setUserResolver(fn () => $user);

        $controller = new TenantSelectorController;
        $inertiaResponse = $controller->index($request);

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $tenants = $props['tenants'];

        expect($tenants)->toHaveCount(1);
        expect($tenants[0]['role'])->toBe('hr_manager');
        expect($tenants[0]['role_label'])->toBe('HR Manager');
    });

    it('displays correct role labels for all tenant user roles', function (string $roleValue, string $expectedLabel) {
        $user = User::factory()->withoutTwoFactor()->create();
        $tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test',
        ]);

        $user->tenants()->attach($tenant, ['role' => $roleValue]);

        $request = Request::create('/select-tenant', 'GET');
        $request->setUserResolver(fn () => $user);

        $controller = new TenantSelectorController;
        $inertiaResponse = $controller->index($request);

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $tenants = $props['tenants'];

        expect($tenants[0]['role'])->toBe($roleValue);
        expect($tenants[0]['role_label'])->toBe($expectedLabel);
    })->with([
        'admin role' => ['admin', 'Admin'],
        'hr_manager role' => ['hr_manager', 'HR Manager'],
        'hr_staff role' => ['hr_staff', 'HR Staff'],
        'hr_consultant role' => ['hr_consultant', 'HR Consultant'],
        'supervisor role' => ['supervisor', 'Supervisor'],
        'employee role' => ['employee', 'Employee'],
    ]);

    it('provides role styling data for visual differentiation', function () {
        $user = User::factory()->withoutTwoFactor()->create();

        // Create tenants with different roles to verify styling data
        $adminTenant = Tenant::factory()->create(['name' => 'Admin Tenant', 'slug' => 'admin-tenant']);
        $employeeTenant = Tenant::factory()->create(['name' => 'Employee Tenant', 'slug' => 'employee-tenant']);
        $hrManagerTenant = Tenant::factory()->create(['name' => 'HR Tenant', 'slug' => 'hr-tenant']);

        $user->tenants()->attach($adminTenant, ['role' => 'admin']);
        $user->tenants()->attach($employeeTenant, ['role' => 'employee']);
        $user->tenants()->attach($hrManagerTenant, ['role' => 'hr_manager']);

        $request = Request::create('/select-tenant', 'GET');
        $request->setUserResolver(fn () => $user);

        $controller = new TenantSelectorController;
        $inertiaResponse = $controller->index($request);

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $tenants = collect($props['tenants']);

        // Find each tenant in the response
        $adminData = $tenants->firstWhere('slug', 'admin-tenant');
        $employeeData = $tenants->firstWhere('slug', 'employee-tenant');
        $hrManagerData = $tenants->firstWhere('slug', 'hr-tenant');

        // Verify role labels are human-readable
        expect($adminData['role_label'])->toBe('Admin');
        expect($employeeData['role_label'])->toBe('Employee');
        expect($hrManagerData['role_label'])->toBe('HR Manager');

        // Verify role values are preserved for conditional styling in frontend
        expect($adminData['role'])->toBe('admin');
        expect($employeeData['role'])->toBe('employee');
        expect($hrManagerData['role'])->toBe('hr_manager');
    });
});
