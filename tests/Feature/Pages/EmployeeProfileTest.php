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
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForProfile(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForProfile(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

it('renders employee profile page with all employee data', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForProfile($tenant);

    $admin = createTenantUserForProfile($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $department = Department::factory()->create(['name' => 'Operations']);
    $position = Position::factory()->create(['title' => 'Operations Manager']);
    $workLocation = WorkLocation::factory()->create(['name' => 'Manila Head Office']);

    $employee = Employee::factory()->create([
        'first_name' => 'Ricardo',
        'middle_name' => 'Mendoza',
        'last_name' => 'Dela Cruz',
        'employee_number' => '2019-0001',
        'email' => 'ricardo.delacruz@email.com',
        'phone' => '+63 917 123 4567',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'work_location_id' => $workLocation->id,
        'employment_type' => EmploymentType::Regular,
        'employment_status' => EmploymentStatus::Active,
        'tin' => '123-456-789-000',
        'sss_number' => '34-1234567-8',
        'philhealth_number' => '12-345678901-2',
        'pagibig_number' => '1234-5678-9012',
    ]);

    // Test the controller directly (passing tenant slug as first argument for subdomain route)
    $controller = new EmployeeController;
    $inertiaResponse = $controller->show($employee);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);
    expect($componentProperty->getValue($inertiaResponse))->toBe('Employees/Show');

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check employee data
    $employeeData = $props['employee']->toArray(request());
    expect($employeeData['first_name'])->toBe('Ricardo');
    expect($employeeData['middle_name'])->toBe('Mendoza');
    expect($employeeData['last_name'])->toBe('Dela Cruz');
    expect($employeeData['employee_number'])->toBe('2019-0001');
    expect($employeeData['email'])->toBe('ricardo.delacruz@email.com');
    expect($employeeData['department']['name'])->toBe('Operations');
    expect($employeeData['position']['title'])->toBe('Operations Manager');
    expect($employeeData['work_location']['name'])->toBe('Manila Head Office');
});

it('displays personal info fields correctly', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForProfile($tenant);

    $admin = createTenantUserForProfile($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create([
        'first_name' => 'Ricardo',
        'middle_name' => 'Mendoza',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
        'date_of_birth' => '1978-05-15',
        'gender' => 'male',
        'civil_status' => 'married',
        'nationality' => 'Filipino',
        'fathers_name' => 'Juan Dela Cruz Sr.',
        'mothers_name' => 'Maria Santos',
    ]);

    // Test the controller directly (passing tenant slug as first argument for subdomain route)
    $controller = new EmployeeController;
    $inertiaResponse = $controller->show($employee);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check personal info fields
    $employeeData = $props['employee']->toArray(request());
    expect($employeeData['date_of_birth'])->toBe('1978-05-15');
    expect($employeeData['gender'])->toBe('male');
    expect($employeeData['civil_status'])->toBe('married');
    expect($employeeData['nationality'])->toBe('Filipino');
    expect($employeeData['fathers_name'])->toBe('Juan Dela Cruz Sr.');
    expect($employeeData['mothers_name'])->toBe('Maria Santos');
    expect($employeeData['age'])->toBeInt();
});

it('displays employment details correctly', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForProfile($tenant);

    $admin = createTenantUserForProfile($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $department = Department::factory()->create(['name' => 'Operations']);
    $position = Position::factory()->create(['title' => 'Operations Manager']);
    $workLocation = WorkLocation::factory()->create(['name' => 'Manila Head Office']);

    $employee = Employee::factory()->create([
        'employee_number' => '2019-0001',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'work_location_id' => $workLocation->id,
        'employment_type' => EmploymentType::Regular,
        'employment_status' => EmploymentStatus::Active,
        'hire_date' => '2019-03-01',
        'regularization_date' => '2019-09-01',
        'basic_salary' => 95000.00,
        'pay_frequency' => 'semi-monthly',
    ]);

    // Test the controller directly (passing tenant slug as first argument for subdomain route)
    $controller = new EmployeeController;
    $inertiaResponse = $controller->show($employee);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check employment details
    $employeeData = $props['employee']->toArray(request());
    expect($employeeData['employee_number'])->toBe('2019-0001');
    expect($employeeData['employment_type'])->toBe('regular');
    expect($employeeData['employment_type_label'])->toBe('Regular');
    expect($employeeData['employment_status'])->toBe('active');
    expect($employeeData['hire_date'])->toBe('2019-03-01');
    expect($employeeData['regularization_date'])->toBe('2019-09-01');
    expect($employeeData['basic_salary'])->toBe('95000.00');
    expect($employeeData['pay_frequency'])->toBe('semi-monthly');
    expect($employeeData['department']['name'])->toBe('Operations');
    expect($employeeData['position']['title'])->toBe('Operations Manager');
    expect($employeeData['work_location']['name'])->toBe('Manila Head Office');
});

it('displays government IDs correctly', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForProfile($tenant);

    $admin = createTenantUserForProfile($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create([
        'tin' => '123-456-789-000',
        'sss_number' => '34-1234567-8',
        'philhealth_number' => '12-345678901-2',
        'pagibig_number' => '1234-5678-9012',
        'umid' => null,
        'passport_number' => null,
        'drivers_license' => null,
    ]);

    // Test the controller directly (passing tenant slug as first argument for subdomain route)
    $controller = new EmployeeController;
    $inertiaResponse = $controller->show($employee);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check government IDs
    $employeeData = $props['employee']->toArray(request());
    expect($employeeData['tin'])->toBe('123-456-789-000');
    expect($employeeData['sss_number'])->toBe('34-1234567-8');
    expect($employeeData['philhealth_number'])->toBe('12-345678901-2');
    expect($employeeData['pagibig_number'])->toBe('1234-5678-9012');
});
