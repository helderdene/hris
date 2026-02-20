<?php

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeController;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForFormPage(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForFormPage(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function createStoreEmployeeFormRequest(array $data, User $user): StoreEmployeeRequest
{
    $request = StoreEmployeeRequest::create('/employees', 'POST', $data);
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
function createUpdateEmployeeFormRequest(array $data, User $user, int $employeeId): UpdateEmployeeRequest
{
    $request = UpdateEmployeeRequest::create("/employees/{$employeeId}", 'PUT', $data);
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

    // Create an UpdateEmployeeRequest instance with route resolver to get proper rules
    $requestInstance = new UpdateEmployeeRequest;
    $requestInstance->setContainer(app());
    $requestInstance->setRouteResolver(fn () => new class($employeeId)
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

    $validator = Validator::make($data, $requestInstance->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

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

it('renders create employee page with required dropdown data', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create reference data for dropdowns
    $department = Department::factory()->create(['name' => 'Engineering']);
    $position = Position::factory()->create(['title' => 'Software Engineer']);
    $workLocation = WorkLocation::factory()->create(['name' => 'Head Office']);
    $supervisor = Employee::factory()->create([
        'first_name' => 'Jane',
        'middle_name' => null, // Explicitly set to null to avoid factory middle name
        'last_name' => 'Manager',
        'employee_number' => 'EMP-001',
    ]);

    // Test the controller directly to avoid Vite manifest issues
    $controller = new EmployeeController;
    $inertiaResponse = $controller->create();

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);
    expect($componentProperty->getValue($inertiaResponse))->toBe('Employees/Create');

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check that departments are returned
    expect($props['departments'])->toHaveCount(1);
    expect($props['departments']->first()->name)->toBe('Engineering');

    // Check that positions are returned
    expect($props['positions'])->toHaveCount(1);
    expect($props['positions']->first()->title)->toBe('Software Engineer');

    // Check that work locations are returned
    expect($props['workLocations'])->toHaveCount(1);
    expect($props['workLocations']->first()->name)->toBe('Head Office');

    // Check that employees (for supervisor dropdown) are returned
    expect($props['employees'])->toHaveCount(1);
    expect($props['employees']->first()['full_name'])->toBe('Jane Manager');

    // Check that employment types and statuses are returned
    expect($props['employmentTypes'])->toHaveCount(count(EmploymentType::cases()));
    expect($props['employmentStatuses'])->toHaveCount(count(EmploymentStatus::cases()));
});

it('creates new employee via store request', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $department = Department::factory()->create();
    $position = Position::factory()->create();

    $employeeData = [
        'employee_number' => 'EMP-2026-001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'hire_date' => '2026-01-01',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'employment_type' => EmploymentType::Regular->value,
        'employment_status' => EmploymentStatus::Active->value,
    ];

    $storeRequest = createStoreEmployeeFormRequest($employeeData, $admin);

    // Create employee directly to test the creation logic
    Employee::create($storeRequest->validated());

    // Check employee was created
    $employee = Employee::where('employee_number', 'EMP-2026-001')->first();
    expect($employee)->not->toBeNull();
    expect($employee->first_name)->toBe('John');
    expect($employee->last_name)->toBe('Doe');
    expect($employee->email)->toBe('john.doe@example.com');
    expect($employee->department_id)->toBe($department->id);
    expect($employee->position_id)->toBe($position->id);
});

it('validates required fields on store', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Test validation rules directly
    $validator = Validator::make([], (new StoreEmployeeRequest)->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('employee_number'))->toBeTrue();
    expect($validator->errors()->has('first_name'))->toBeTrue();
    expect($validator->errors()->has('last_name'))->toBeTrue();
    expect($validator->errors()->has('email'))->toBeTrue();
    expect($validator->errors()->has('hire_date'))->toBeTrue();
});

it('validates unique employee number on store', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create existing employee
    Employee::factory()->create(['employee_number' => 'EMP-EXISTING']);

    // Test validation with duplicate employee number
    $data = [
        'employee_number' => 'EMP-EXISTING', // Duplicate
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'hire_date' => '2026-01-01',
    ];

    $validator = Validator::make($data, (new StoreEmployeeRequest)->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('employee_number'))->toBeTrue();
});

it('renders edit employee page with employee data pre-populated', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $department = Department::factory()->create(['name' => 'Engineering']);
    $position = Position::factory()->create(['title' => 'Software Engineer']);
    $workLocation = WorkLocation::factory()->create(['name' => 'Branch Office']);

    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-2025-001',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria.santos@example.com',
        'department_id' => $department->id,
        'position_id' => $position->id,
        'work_location_id' => $workLocation->id,
        'employment_type' => EmploymentType::Probationary,
        'employment_status' => EmploymentStatus::Active,
    ]);

    // Test the controller directly (passing tenant slug as first argument for subdomain route)
    $controller = new EmployeeController;
    $inertiaResponse = $controller->edit($employee);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);
    expect($componentProperty->getValue($inertiaResponse))->toBe('Employees/Edit');

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check that employee data is passed
    $employeeResource = $props['employee']->toArray(request());
    expect($employeeResource['id'])->toBe($employee->id);
    expect($employeeResource['employee_number'])->toBe('EMP-2025-001');
    expect($employeeResource['first_name'])->toBe('Maria');
    expect($employeeResource['last_name'])->toBe('Santos');
    expect($employeeResource['email'])->toBe('maria.santos@example.com');

    // Check that dropdown data is also passed
    expect($props['departments'])->toHaveCount(1);
    expect($props['positions'])->toHaveCount(1);
    expect($props['workLocations'])->toHaveCount(1);
});

it('updates employee via update request', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-2025-001',
        'first_name' => 'Original',
        'last_name' => 'Name',
        'email' => 'original@example.com',
        'hire_date' => '2025-01-01',
    ]);

    $updatedData = [
        'employee_number' => 'EMP-2025-001',
        'first_name' => 'Updated',
        'last_name' => 'Employee',
        'email' => 'updated@example.com',
        'hire_date' => '2025-01-01',
        'phone' => '+63 912 345 6789',
        'tin' => '123-456-789-000',
    ];

    $updateRequest = createUpdateEmployeeFormRequest($updatedData, $admin, $employee->id);

    // Update employee directly to test the update logic
    $employee->update($updateRequest->validated());

    // Check employee was updated
    $employee->refresh();
    expect($employee->first_name)->toBe('Updated');
    expect($employee->last_name)->toBe('Employee');
    expect($employee->email)->toBe('updated@example.com');
    expect($employee->phone)->toBe('+63 912 345 6789');
    expect($employee->tin)->toBe('123-456-789-000');
});

it('allows same employee number on update for same employee', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-UNIQUE-001',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'hire_date' => '2025-01-01',
    ]);

    // Create an UpdateEmployeeRequest instance with route resolver to get proper rules
    $requestInstance = new UpdateEmployeeRequest;
    $requestInstance->setContainer(app());
    $requestInstance->setRouteResolver(fn () => new class($employee->id)
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

    $validator = Validator::make([
        'employee_number' => 'EMP-UNIQUE-001',
        'first_name' => 'Updated',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'hire_date' => '2025-01-01',
    ], $requestInstance->rules());

    // Should pass validation - same employee can keep same employee_number
    expect($validator->passes())->toBeTrue();
});

it('validates unique email across employees on update', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create another employee with specific email
    Employee::factory()->create([
        'employee_number' => 'EMP-001',
        'email' => 'taken@example.com',
    ]);

    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-002',
        'email' => 'original@example.com',
        'hire_date' => '2025-01-01',
    ]);

    // Create an UpdateEmployeeRequest instance with route resolver to get proper rules
    $requestInstance = new UpdateEmployeeRequest;
    $requestInstance->setContainer(app());
    $requestInstance->setRouteResolver(fn () => new class($employee->id)
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

    $validator = Validator::make([
        'employee_number' => 'EMP-002',
        'first_name' => $employee->first_name,
        'last_name' => $employee->last_name,
        'email' => 'taken@example.com',
        'hire_date' => '2025-01-01',
    ], $requestInstance->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('email'))->toBeTrue();
});

it('stores employee with address and emergency contact JSON fields', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $employeeData = [
        'employee_number' => 'EMP-JSON-001',
        'first_name' => 'Test',
        'last_name' => 'JSON',
        'email' => 'json@example.com',
        'hire_date' => '2026-01-01',
        'address' => [
            'street' => '123 Main Street',
            'barangay' => 'San Antonio',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'postal_code' => '1234',
        ],
        'emergency_contact' => [
            'name' => 'Emergency Person',
            'relationship' => 'Spouse',
            'phone' => '+63 917 123 4567',
        ],
    ];

    $storeRequest = createStoreEmployeeFormRequest($employeeData, $admin);

    // Create employee directly
    Employee::create($storeRequest->validated());

    $employee = Employee::where('employee_number', 'EMP-JSON-001')->first();
    expect($employee)->not->toBeNull();
    expect($employee->address['street'])->toBe('123 Main Street');
    expect($employee->address['city'])->toBe('Makati');
    expect($employee->emergency_contact['name'])->toBe('Emergency Person');
    expect($employee->emergency_contact['relationship'])->toBe('Spouse');
});

it('returns all employees for supervisor dropdown in edit', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForFormPage($tenant);

    $admin = createTenantUserForFormPage($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $employee = Employee::factory()->create([
        'first_name' => 'Current',
        'last_name' => 'Employee',
        'employee_number' => 'EMP-CURRENT',
    ]);

    $otherEmployee = Employee::factory()->create([
        'first_name' => 'Other',
        'last_name' => 'Employee',
        'employee_number' => 'EMP-OTHER',
    ]);

    // Test the controller directly (passing tenant slug as first argument for subdomain route)
    $controller = new EmployeeController;
    $inertiaResponse = $controller->edit($employee);

    $reflection = new ReflectionClass($inertiaResponse);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // The employees list should contain both (frontend will filter out current)
    expect($props['employees'])->toHaveCount(2);
});
