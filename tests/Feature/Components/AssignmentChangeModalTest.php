<?php

use App\Enums\AssignmentType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeAssignmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Requests\StoreEmployeeAssignmentRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForModal(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForModal(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store assignment request with proper data context.
 */
function createStoreAssignmentRequestForModal(array $data, User $user): StoreEmployeeAssignmentRequest
{
    // Create request with data
    $request = StoreEmployeeAssignmentRequest::create('/api/employees/1/assignments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    // Create a new request instance with the same data to get proper rules
    $rulesRequest = new StoreEmployeeAssignmentRequest($data);
    $rulesRequest->setContainer(app());

    // Use the rules from the request that has the data
    $validator = Validator::make($data, $rulesRequest->rules());

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

describe('Assignment Change Modal - Controller Data Loading', function () {
    it('loads dropdown data for assignment modal when user has can-manage-employees permission', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForModal($tenant);

        $admin = createTenantUserForModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create test data
        $department = Department::factory()->create(['name' => 'Engineering']);
        $position = Position::factory()->create(['title' => 'Software Developer']);
        $workLocation = WorkLocation::factory()->create(['name' => 'Main Office']);
        $supervisor = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Manager',
        ]);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'work_location_id' => $workLocation->id,
            'supervisor_id' => $supervisor->id,
        ]);

        // Test the controller directly
        $controller = new EmployeeController;
        $inertiaResponse = $controller->show($employee);

        // Use reflection to access protected properties
        $reflection = new ReflectionClass($inertiaResponse);

        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Verify dropdown data is present
        expect($props)->toHaveKey('departments');
        expect($props)->toHaveKey('positions');
        expect($props)->toHaveKey('workLocations');
        expect($props)->toHaveKey('supervisorOptions');

        // Verify data is correct
        expect($props['departments']->count())->toBeGreaterThanOrEqual(1);
        expect($props['positions']->count())->toBeGreaterThanOrEqual(1);
        expect($props['workLocations']->count())->toBeGreaterThanOrEqual(1);
        expect($props['supervisorOptions']->count())->toBeGreaterThanOrEqual(1);
    });
});

describe('Assignment Change Modal - Form Validation', function () {
    it('validates required fields for assignment form', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForModal($tenant);

        $admin = createTenantUserForModal($tenant, TenantUserRole::Admin);

        // Test validation with missing required fields
        $rules = (new StoreEmployeeAssignmentRequest)->rules();

        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();

        $errors = $validator->errors();
        expect($errors->has('assignment_type'))->toBeTrue();
        expect($errors->has('new_value_id'))->toBeTrue();
        expect($errors->has('effective_date'))->toBeTrue();

        // remarks is optional - should not have error
        expect($errors->has('remarks'))->toBeFalse();
    });

    it('validates assignment_type is a valid enum value', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForModal($tenant);

        $admin = createTenantUserForModal($tenant, TenantUserRole::Admin);

        $rules = (new StoreEmployeeAssignmentRequest)->rules();

        // Test with invalid assignment type
        $invalidValidator = Validator::make([
            'assignment_type' => 'invalid_type',
            'new_value_id' => 1,
            'effective_date' => now()->format('Y-m-d'),
        ], $rules);

        expect($invalidValidator->fails())->toBeTrue();
        expect($invalidValidator->errors()->has('assignment_type'))->toBeTrue();

        // Test with valid assignment types
        foreach (AssignmentType::values() as $type) {
            $validValidator = Validator::make([
                'assignment_type' => $type,
                'new_value_id' => 1,
                'effective_date' => now()->format('Y-m-d'),
            ], $rules);

            // Only assignment_type should be valid, new_value_id will fail as it doesn't exist
            expect($validValidator->errors()->has('assignment_type'))->toBeFalse();
        }
    });
});

describe('Assignment Change Modal - Form Submission', function () {
    it('successfully creates assignment and closes modal on valid submission', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForModal($tenant);

        $admin = createTenantUserForModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $oldDepartment = Department::factory()->create(['name' => 'Old Department']);
        $newDepartment = Department::factory()->create(['name' => 'New Department']);
        $employee = Employee::factory()->create(['department_id' => $oldDepartment->id]);

        $requestData = [
            'assignment_type' => 'department',
            'new_value_id' => $newDepartment->id,
            'effective_date' => now()->format('Y-m-d'),
            'remarks' => 'Transferred to new department for project',
        ];

        $request = createStoreAssignmentRequestForModal($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        $assignmentData = $data['data'] ?? $data;

        expect($assignmentData['assignment_type']['value'])->toBe('department');
        expect($assignmentData['new_value_id'])->toBe($newDepartment->id);
        expect($assignmentData['remarks'])->toBe('Transferred to new department for project');

        // Verify employee's department_id was updated
        $employee->refresh();
        expect($employee->department_id)->toBe($newDepartment->id);
    });

    it('creates assignment for position type correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForModal($tenant);

        $admin = createTenantUserForModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $position = Position::factory()->create();

        $requestData = [
            'assignment_type' => 'position',
            'new_value_id' => $position->id,
            'effective_date' => now()->format('Y-m-d'),
        ];

        $request = createStoreAssignmentRequestForModal($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        // Verify employee field was updated
        $employee->refresh();
        expect($employee->position_id)->toBe($position->id);
    });

    it('creates assignment for work location type correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForModal($tenant);

        $admin = createTenantUserForModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $workLocation = WorkLocation::factory()->create();

        $requestData = [
            'assignment_type' => 'location',
            'new_value_id' => $workLocation->id,
            'effective_date' => now()->format('Y-m-d'),
        ];

        $request = createStoreAssignmentRequestForModal($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        // Verify employee field was updated
        $employee->refresh();
        expect($employee->work_location_id)->toBe($workLocation->id);
    });
});

describe('Assignment Change Modal - Dropdown Options', function () {
    it('populates dropdown options correctly for each assignment type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForModal($tenant);

        $admin = createTenantUserForModal($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create multiple items for each dropdown
        $departments = Department::factory()->count(3)->create();
        $positions = Position::factory()->count(3)->create();
        $workLocations = WorkLocation::factory()->count(3)->create();
        $supervisors = Employee::factory()->count(3)->create();

        $employee = Employee::factory()->create();

        $controller = new EmployeeController;
        $inertiaResponse = $controller->show($employee);

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Check each dropdown has the correct data
        expect($props['departments']->count())->toBe(3);
        expect($props['positions']->count())->toBe(3);
        expect($props['workLocations']->count())->toBe(3);
        // supervisorOptions should include all employees (3 supervisors + 1 employee)
        expect($props['supervisorOptions']->count())->toBe(4);

        // Verify structure of dropdown options
        $firstDept = $props['departments']->first();
        expect($firstDept)->toHaveKey('id');
        expect($firstDept)->toHaveKey('name');

        $firstPosition = $props['positions']->first();
        expect($firstPosition)->toHaveKey('id');
        expect($firstPosition)->toHaveKey('title');

        $firstLocation = $props['workLocations']->first();
        expect($firstLocation)->toHaveKey('id');
        expect($firstLocation)->toHaveKey('name');

        $firstSupervisor = $props['supervisorOptions']->first();
        expect($firstSupervisor)->toHaveKey('id');
        expect($firstSupervisor)->toHaveKey('full_name');
    });
});
