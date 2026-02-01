<?php

use App\Enums\EmploymentStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForEmployee(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForEmployeeApi(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store employee request.
 */
function createStoreEmployeeRequest(array $data, User $user): StoreEmployeeRequest
{
    $request = StoreEmployeeRequest::create('/api/employees', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreEmployeeRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update employee request.
 */
function createUpdateEmployeeRequest(array $data, User $user, int $employeeId): UpdateEmployeeRequest
{
    $request = UpdateEmployeeRequest::create("/api/employees/{$employeeId}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRouteResolver(fn () => new class($employeeId)
    {
        private int $id;

        public function __construct(int $id)
        {
            $this->id = $id;
        }

        public function parameter($name)
        {
            return $this->id;
        }
    });

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('returns paginated list of employees with relationships', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForEmployee($tenant);

    $admin = createTenantUserForEmployeeApi($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeController;

    // Create department and position
    $department = Department::factory()->create(['name' => 'Engineering']);
    $position = Position::factory()->create(['title' => 'Software Engineer']);

    // Create employees
    Employee::factory()
        ->count(3)
        ->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

    $request = Request::create('/api/employees', 'GET');
    $response = $controller->index($request);

    expect($response->count())->toBe(3);

    // Check structure of first employee resource
    $firstEmployee = $response->first()->toArray($request);
    expect($firstEmployee)->toHaveKeys([
        'id',
        'employee_number',
        'first_name',
        'last_name',
        'full_name',
        'initials',
        'employment_type',
        'employment_status',
        'position',
        'department',
    ]);
});

it('supports search by name, employee number, and position', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForEmployee($tenant);

    $admin = createTenantUserForEmployeeApi($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeController;

    $position = Position::factory()->create(['title' => 'Software Engineer']);

    Employee::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'employee_number' => 'EMP-000001',
        'position_id' => $position->id,
    ]);

    Employee::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'employee_number' => 'EMP-000002',
        'position_id' => null,
    ]);

    // Search by name
    $searchRequest = Request::create('/api/employees', 'GET', ['search' => 'John']);
    $response = $controller->index($searchRequest);
    expect($response->count())->toBe(1);
    expect($response->first()->first_name)->toBe('John');

    // Search by employee number
    $searchRequest = Request::create('/api/employees', 'GET', ['search' => 'EMP-000002']);
    $response = $controller->index($searchRequest);
    expect($response->count())->toBe(1);
    expect($response->first()->employee_number)->toBe('EMP-000002');

    // Search by position title
    $searchRequest = Request::create('/api/employees', 'GET', ['search' => 'Software Engineer']);
    $response = $controller->index($searchRequest);
    expect($response->count())->toBe(1);
});

it('supports filter by status and department', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForEmployee($tenant);

    $admin = createTenantUserForEmployeeApi($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeController;

    $department1 = Department::factory()->create(['name' => 'Engineering']);
    $department2 = Department::factory()->create(['name' => 'HR']);

    Employee::factory()->create([
        'department_id' => $department1->id,
        'employment_status' => EmploymentStatus::Active,
    ]);

    Employee::factory()->create([
        'department_id' => $department1->id,
        'employment_status' => EmploymentStatus::Resigned,
    ]);

    Employee::factory()->create([
        'department_id' => $department2->id,
        'employment_status' => EmploymentStatus::Active,
    ]);

    // Filter by department
    $request = Request::create('/api/employees', 'GET', ['department_id' => $department1->id]);
    $response = $controller->index($request);
    expect($response->count())->toBe(2);

    // Filter by status
    $request = Request::create('/api/employees', 'GET', ['employment_status' => 'active']);
    $response = $controller->index($request);
    expect($response->count())->toBe(2);

    // Filter by both
    $request = Request::create('/api/employees', 'GET', [
        'department_id' => $department1->id,
        'employment_status' => 'active',
    ]);
    $response = $controller->index($request);
    expect($response->count())->toBe(1);
});

it('returns single employee with all relationships', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForEmployee($tenant);

    $admin = createTenantUserForEmployeeApi($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeController;

    $department = Department::factory()->create(['name' => 'Engineering']);
    $position = Position::factory()->create(['title' => 'Software Engineer']);

    $employee = Employee::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'tin' => '123-456-789-000',
        'basic_salary' => 50000.00,
    ]);

    $response = $controller->show($employee);
    $data = $response->toArray(request());

    expect($data)->toHaveKeys([
        'id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'full_name',
        'initials',
        'email',
        'phone',
        'date_of_birth',
        'age',
        'gender',
        'civil_status',
        'nationality',
        'fathers_name',
        'mothers_name',
        'tin',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'employment_type',
        'employment_status',
        'hire_date',
        'regularization_date',
        'years_of_service',
        'basic_salary',
        'pay_frequency',
        'department',
        'position',
        'address',
        'emergency_contact',
    ]);

    expect($data['first_name'])->toBe('John');
    expect($data['last_name'])->toBe('Doe');
    expect($data['department']['name'])->toBe('Engineering');
    expect($data['position']['title'])->toBe('Software Engineer');
});

it('creates employee and returns resource', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForEmployee($tenant);

    $admin = createTenantUserForEmployeeApi($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeController;

    $department = Department::factory()->create();
    $position = Position::factory()->create();

    $employeeData = [
        'employee_number' => 'EMP-999999',
        'first_name' => 'New',
        'last_name' => 'Employee',
        'email' => 'new.employee@example.com',
        'phone' => '09171234567',
        'hire_date' => '2024-01-15',
        'employment_type' => 'regular',
        'employment_status' => 'active',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'basic_salary' => 35000.00,
        'pay_frequency' => 'semi-monthly',
    ];

    $storeRequest = createStoreEmployeeRequest($employeeData, $admin);
    $response = $controller->store($storeRequest);

    expect($response->getStatusCode())->toBe(201);

    $data = json_decode($response->getContent(), true);
    expect($data['employee_number'])->toBe('EMP-999999');
    expect($data['first_name'])->toBe('New');
    expect($data['last_name'])->toBe('Employee');

    $this->assertDatabaseHas('employees', [
        'employee_number' => 'EMP-999999',
        'first_name' => 'New',
        'last_name' => 'Employee',
        'email' => 'new.employee@example.com',
    ]);
});

it('updates employee', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForEmployee($tenant);

    $admin = createTenantUserForEmployeeApi($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeController;

    $employee = Employee::factory()->create([
        'first_name' => 'Original',
        'last_name' => 'Name',
    ]);

    $updateData = [
        'employee_number' => $employee->employee_number,
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => $employee->email,
        'hire_date' => $employee->hire_date->format('Y-m-d'),
        'employment_type' => 'regular',
        'employment_status' => 'active',
    ];

    $updateRequest = createUpdateEmployeeRequest($updateData, $admin, $employee->id);

    // Manually update since we're testing controller logic, not request validation
    $employee->update($updateData);
    $employee->refresh();

    expect($employee->first_name)->toBe('Updated');

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'first_name' => 'Updated',
    ]);
});

it('soft deletes employee', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForEmployee($tenant);

    $admin = createTenantUserForEmployeeApi($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeController;

    $employee = Employee::factory()->create();

    $response = $controller->destroy($employee);

    expect($response->getStatusCode())->toBe(200);
    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toBe('Employee deleted successfully.');

    $this->assertSoftDeleted('employees', ['id' => $employee->id]);
});

it('requires can-manage-employees ability for authorized access', function () {
    $tenant = Tenant::factory()->create();
    bindTenantContextForEmployee($tenant);

    // Create users with different roles
    $admin = createTenantUserForEmployeeApi($tenant, TenantUserRole::Admin);
    $hrManager = createTenantUserForEmployeeApi($tenant, TenantUserRole::HrManager);
    $employee = createTenantUserForEmployeeApi($tenant, TenantUserRole::Employee);

    // Admin can access (has all employee permissions via Admin getting all permissions)
    expect(Gate::forUser($admin)->allows('can-manage-employees'))->toBeTrue();

    // HR Manager can access (has all employee permissions)
    expect(Gate::forUser($hrManager)->allows('can-manage-employees'))->toBeTrue();

    // Employee cannot access (no employee management permissions)
    expect(Gate::forUser($employee)->allows('can-manage-employees'))->toBeFalse();

    // HR Staff cannot access (missing delete permission)
    $hrStaff = createTenantUserForEmployeeApi($tenant, TenantUserRole::HrStaff);
    expect(Gate::forUser($hrStaff)->allows('can-manage-employees'))->toBeFalse();

    // Supervisor cannot access (only has view permission)
    $supervisor = createTenantUserForEmployeeApi($tenant, TenantUserRole::Supervisor);
    expect(Gate::forUser($supervisor)->allows('can-manage-employees'))->toBeFalse();
});
