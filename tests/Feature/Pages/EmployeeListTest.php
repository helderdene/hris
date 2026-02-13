<?php

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForPage(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPage(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('renders employee list page with correct data', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForPage($tenant);

    $admin = createTenantUserForPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create department and position
    $department = Department::factory()->create(['name' => 'Engineering']);
    $position = Position::factory()->create(['title' => 'Software Engineer']);

    // Create employees
    Employee::factory()
        ->count(3)
        ->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employment_type' => EmploymentType::Regular,
            'employment_status' => EmploymentStatus::Active,
        ]);

    // Test the controller directly to avoid Vite manifest issues
    $request = Request::create('/employees', 'GET');
    $request->setUserResolver(fn () => $admin);

    $controller = new EmployeeController;
    $inertiaResponse = $controller->index($request);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);
    expect($componentProperty->getValue($inertiaResponse))->toBe('Employees/Index');

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check that 3 employees are returned (paginated response)
    $employeesResource = $props['employees'];
    $employees = $employeesResource->resource;
    expect($employees)->toHaveCount(3);

    // Check that departments are included
    expect($props['departments'])->toHaveCount(1);
});

it('displays correct table columns in employee list', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForPage($tenant);

    $admin = createTenantUserForPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $department = Department::factory()->create(['name' => 'Operations']);
    $position = Position::factory()->create(['title' => 'Operations Manager']);

    Employee::factory()->create([
        'first_name' => 'Ricardo',
        'last_name' => 'Dela Cruz',
        'employee_number' => '2019-0001',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employment_type' => EmploymentType::Regular,
        'employment_status' => EmploymentStatus::Active,
    ]);

    // Test the controller directly
    $request = Request::create('/employees', 'GET');
    $request->setUserResolver(fn () => $admin);

    $controller = new EmployeeController;
    $inertiaResponse = $controller->index($request);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check employee data (paginated response)
    $employeesResource = $props['employees'];
    $employees = $employeesResource->resource;
    expect($employees)->toHaveCount(1);

    $employee = $employees->first();
    $employee->load(['department', 'position', 'workLocation']);
    $employee = (new \App\Http\Resources\EmployeeListResource($employee))->toArray(request());
    expect($employee['first_name'])->toBe('Ricardo');
    expect($employee['last_name'])->toBe('Dela Cruz');
    expect($employee['employee_number'])->toBe('2019-0001');
    expect($employee['position']['title'])->toBe('Operations Manager');
    expect($employee['department']['name'])->toBe('Operations');
});

it('supports search functionality', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForPage($tenant);

    $admin = createTenantUserForPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    Employee::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'employee_number' => 'EMP-001',
    ]);

    Employee::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'employee_number' => 'EMP-002',
    ]);

    // Test the controller directly with search query
    $request = Request::create('/employees', 'GET', ['search' => 'John']);
    $request->setUserResolver(fn () => $admin);

    $controller = new EmployeeController;
    $inertiaResponse = $controller->index($request);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check that only 1 employee is returned (John Doe) - paginated response
    $employees = $props['employees']->resource;
    expect($employees)->toHaveCount(1);
    expect($employees->first()->first_name)->toBe('John');

    // Check filters are passed
    expect($props['filters']['search'])->toBe('John');
});

it('provides department filter options to employee list', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForPage($tenant);

    $admin = createTenantUserForPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    Department::factory()->create(['name' => 'Engineering']);
    Department::factory()->create(['name' => 'HR']);
    Department::factory()->create(['name' => 'Finance']);

    // Test the controller directly
    $request = Request::create('/employees', 'GET');
    $request->setUserResolver(fn () => $admin);

    $controller = new EmployeeController;
    $inertiaResponse = $controller->index($request);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check that departments are returned
    expect($props['departments'])->toHaveCount(3);
});
