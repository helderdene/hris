<?php

/**
 * Tests for the Attendance Log Page
 *
 * These tests verify the Inertia page rendering, filtering, and access control
 * for the attendance log feature.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\AttendanceLogController;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForAttendance(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForAttendance(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to extract Inertia response data without triggering full HTTP response.
 */
function getInertiaResponseDataForAttendance(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

/**
 * Helper to get the Inertia component name.
 */
function getInertiaComponentForAttendance(\Inertia\Response $response): string
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('component');
    $property->setAccessible(true);

    return $property->getValue($response);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Attendance Log Page', function () {
    it('renders attendance page with log data', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAttendance($tenant);

        $admin = createTenantUserForAttendance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['name' => 'Main Office']);
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee = Employee::factory()->create();

        AttendanceLog::factory()->count(3)->create([
            'biometric_device_id' => $device->id,
            'employee_id' => $employee->id,
            'logged_at' => now(),
        ]);

        $controller = new AttendanceLogController;
        $request = Request::create('/attendance', 'GET');
        app()->instance('request', $request);

        $response = $controller->index($request);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getInertiaComponentForAttendance($response))->toBe('Attendance/Index');

        $data = getInertiaResponseDataForAttendance($response);
        expect($data)->toHaveKey('logs');
        expect($data)->toHaveKey('employees');
        expect($data)->toHaveKey('devices');
        expect($data)->toHaveKey('filters');
    });

    it('filters logs by date range', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAttendance($tenant);

        $admin = createTenantUserForAttendance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee = Employee::factory()->create();

        // Create logs for today
        AttendanceLog::factory()->count(2)->create([
            'biometric_device_id' => $device->id,
            'employee_id' => $employee->id,
            'logged_at' => now(),
        ]);

        // Create logs for yesterday
        AttendanceLog::factory()->count(3)->create([
            'biometric_device_id' => $device->id,
            'employee_id' => $employee->id,
            'logged_at' => now()->subDay(),
        ]);

        $controller = new AttendanceLogController;
        $request = Request::create('/attendance', 'GET', [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);
        app()->instance('request', $request);

        $response = $controller->index($request);

        $data = getInertiaResponseDataForAttendance($response);
        // Should only return today's logs (2)
        expect(count($data['logs']->items()))->toBe(2);
    });

    it('filters logs by employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAttendance($tenant);

        $admin = createTenantUserForAttendance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        AttendanceLog::factory()->count(2)->create([
            'biometric_device_id' => $device->id,
            'employee_id' => $employee1->id,
            'logged_at' => now(),
        ]);
        AttendanceLog::factory()->count(3)->create([
            'biometric_device_id' => $device->id,
            'employee_id' => $employee2->id,
            'logged_at' => now(),
        ]);

        $controller = new AttendanceLogController;
        $request = Request::create('/attendance', 'GET', [
            'employee_id' => (string) $employee1->id,
        ]);
        app()->instance('request', $request);

        $response = $controller->index($request);

        $data = getInertiaResponseDataForAttendance($response);
        expect(count($data['logs']->items()))->toBe(2);
        expect($data['filters']['employee_id'])->toBe((string) $employee1->id);
    });

    it('filters logs by device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAttendance($tenant);

        $admin = createTenantUserForAttendance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();
        $device1 = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device2 = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee = Employee::factory()->create();

        AttendanceLog::factory()->count(2)->create([
            'biometric_device_id' => $device1->id,
            'employee_id' => $employee->id,
            'logged_at' => now(),
        ]);
        AttendanceLog::factory()->count(3)->create([
            'biometric_device_id' => $device2->id,
            'employee_id' => $employee->id,
            'logged_at' => now(),
        ]);

        $controller = new AttendanceLogController;
        $request = Request::create('/attendance', 'GET', [
            'device_id' => (string) $device1->id,
        ]);
        app()->instance('request', $request);

        $response = $controller->index($request);

        $data = getInertiaResponseDataForAttendance($response);
        expect(count($data['logs']->items()))->toBe(2);
        expect($data['filters']['device_id'])->toBe((string) $device1->id);
    });

    it('denies access to unauthorized users', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAttendance($tenant);

        // Employee role should not have access
        $employee = createTenantUserForAttendance($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        // Verify Gate does not authorize employee
        expect(Gate::allows('can-manage-employees'))->toBeFalse();
    });

    it('defaults to today date filter', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAttendance($tenant);

        $admin = createTenantUserForAttendance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new AttendanceLogController;
        $request = Request::create('/attendance', 'GET');
        app()->instance('request', $request);

        $response = $controller->index($request);

        $data = getInertiaResponseDataForAttendance($response);
        expect($data['filters']['date_from'])->toBe(now()->toDateString());
        expect($data['filters']['date_to'])->toBe(now()->toDateString());
    });

    it('provides employees dropdown data for filtering', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAttendance($tenant);

        $admin = createTenantUserForAttendance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Employee::factory()->count(3)->create();

        $controller = new AttendanceLogController;
        $request = Request::create('/attendance', 'GET');
        app()->instance('request', $request);

        $response = $controller->index($request);

        $data = getInertiaResponseDataForAttendance($response);
        expect($data)->toHaveKey('employees');
        expect(count($data['employees']))->toBe(3);
    });

    it('provides devices dropdown data for filtering', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAttendance($tenant);

        $admin = createTenantUserForAttendance($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();
        BiometricDevice::factory()->count(2)->create([
            'work_location_id' => $workLocation->id,
            'is_active' => true,
        ]);
        // Inactive device should not be in dropdown
        BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
            'is_active' => false,
        ]);

        $controller = new AttendanceLogController;
        $request = Request::create('/attendance', 'GET');
        app()->instance('request', $request);

        $response = $controller->index($request);

        $data = getInertiaResponseDataForAttendance($response);
        expect($data)->toHaveKey('devices');
        expect(count($data['devices']))->toBe(2);
    });
});
