<?php

use App\Enums\AssignmentType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeAssignmentController;
use App\Http\Requests\StoreEmployeeAssignmentRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignmentHistory;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForAssignment(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForAssignment(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store assignment request.
 */
function createStoreAssignmentRequest(array $data, User $user): StoreEmployeeAssignmentRequest
{
    $request = StoreEmployeeAssignmentRequest::create('/api/employees/1/assignments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreEmployeeAssignmentRequest)->rules());

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

describe('GET /employees/{employee}/assignments (index)', function () {
    it('returns assignment history for employee with related data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAssignment($tenant);

        $admin = createTenantUserForAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $department = Department::factory()->create(['name' => 'Engineering']);
        $position = Position::factory()->create(['title' => 'Software Developer']);

        // Create assignment history records
        EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => null,
            'new_value_id' => $department->id,
            'effective_date' => now()->subMonth(),
            'remarks' => 'Initial assignment',
            'changed_by' => $admin->id,
            'ended_at' => null,
        ]);

        EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Position,
            'previous_value_id' => null,
            'new_value_id' => $position->id,
            'effective_date' => now()->subMonth(),
            'remarks' => 'Initial position',
            'changed_by' => $admin->id,
            'ended_at' => null,
        ]);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->index($employee);

        expect($response->count())->toBe(2);

        // Check structure of first resource by converting to array
        $firstResource = $response->first();
        $resourceArray = $firstResource->toArray(request());

        expect($resourceArray)->toHaveKeys([
            'id',
            'employee_id',
            'assignment_type',
            'previous_value_id',
            'previous_value_name',
            'new_value_id',
            'new_value_name',
            'effective_date',
            'remarks',
            'changed_by',
            'changed_by_name',
            'ended_at',
            'created_at',
            'updated_at',
        ]);

        // Verify assignment_type is properly transformed
        expect($resourceArray['assignment_type'])->toHaveKeys(['value', 'label']);

        // Verify changed_by_name is resolved
        expect($resourceArray['changed_by_name'])->toBe($admin->name);
    });
});

describe('POST /employees/{employee}/assignments (store)', function () {
    it('creates new assignment and ends previous assignment of same type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAssignment($tenant);

        $admin = createTenantUserForAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $oldDepartment = Department::factory()->create(['name' => 'Old Department']);
        $newDepartment = Department::factory()->create(['name' => 'New Department']);
        $employee = Employee::factory()->create(['department_id' => $oldDepartment->id]);

        // Create existing current assignment
        $existingAssignment = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => null,
            'new_value_id' => $oldDepartment->id,
            'effective_date' => now()->subMonth(),
            'ended_at' => null,
        ]);

        $requestData = [
            'assignment_type' => 'department',
            'new_value_id' => $newDepartment->id,
            'effective_date' => now()->format('Y-m-d'),
            'remarks' => 'Transferred to new department',
        ];

        $request = createStoreAssignmentRequest($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);

        // The JsonResource response wraps data in a 'data' key
        $assignmentData = $data['data'] ?? $data;
        expect($assignmentData['assignment_type']['value'])->toBe('department');
        expect($assignmentData['new_value_id'])->toBe($newDepartment->id);
        expect($assignmentData['previous_value_id'])->toBe($oldDepartment->id);

        // Verify previous assignment was ended
        $existingAssignment->refresh();
        expect($existingAssignment->ended_at)->not->toBeNull();

        // Verify employee's department_id was updated
        $employee->refresh();
        expect($employee->department_id)->toBe($newDepartment->id);
    });

    it('requires authorization with can-manage-employees gate', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAssignment($tenant);

        // Admin should have access
        $admin = createTenantUserForAssignment($tenant, TenantUserRole::Admin);
        expect(Gate::forUser($admin)->allows('can-manage-employees'))->toBeTrue();

        // HR Manager should have access
        $hrManager = createTenantUserForAssignment($tenant, TenantUserRole::HrManager);
        expect(Gate::forUser($hrManager)->allows('can-manage-employees'))->toBeTrue();

        // User with Employee role cannot manage employees
        $employeeUser = createTenantUserForAssignment($tenant, TenantUserRole::Employee);
        expect(Gate::forUser($employeeUser)->allows('can-manage-employees'))->toBeFalse();

        // HR Staff cannot manage employees (missing delete permission)
        $hrStaff = createTenantUserForAssignment($tenant, TenantUserRole::HrStaff);
        expect(Gate::forUser($hrStaff)->allows('can-manage-employees'))->toBeFalse();

        // Supervisor cannot manage employees (only has view permission)
        $supervisor = createTenantUserForAssignment($tenant, TenantUserRole::Supervisor);
        expect(Gate::forUser($supervisor)->allows('can-manage-employees'))->toBeFalse();
    });

    it('validates required fields', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAssignment($tenant);

        $admin = createTenantUserForAssignment($tenant, TenantUserRole::Admin);

        // Test validation with missing required fields
        $rules = (new StoreEmployeeAssignmentRequest)->rules();

        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();

        $errors = $validator->errors();
        expect($errors->has('assignment_type'))->toBeTrue();
        expect($errors->has('new_value_id'))->toBeTrue();
        expect($errors->has('effective_date'))->toBeTrue();
    });

    it('validates new_value_id exists in appropriate table based on assignment_type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForAssignment($tenant);

        $admin = createTenantUserForAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $department = Department::factory()->create();
        $position = Position::factory()->create();
        $location = WorkLocation::factory()->create();
        $supervisor = Employee::factory()->create();

        // Test with non-existent IDs - should fail validation
        $invalidCases = [
            ['assignment_type' => 'department', 'new_value_id' => 99999],
            ['assignment_type' => 'position', 'new_value_id' => 99999],
            ['assignment_type' => 'location', 'new_value_id' => 99999],
            ['assignment_type' => 'supervisor', 'new_value_id' => 99999],
        ];

        foreach ($invalidCases as $case) {
            $request = new StoreEmployeeAssignmentRequest($case + ['effective_date' => now()->format('Y-m-d')]);
            $request->setContainer(app());
            $rules = $request->rules();

            $validator = Validator::make($case + ['effective_date' => now()->format('Y-m-d')], $rules);
            expect($validator->fails())->toBeTrue("Should fail for assignment_type: {$case['assignment_type']}");
            expect($validator->errors()->has('new_value_id'))->toBeTrue();
        }

        // Test with valid IDs - should pass validation
        $validCases = [
            ['assignment_type' => 'department', 'new_value_id' => $department->id],
            ['assignment_type' => 'position', 'new_value_id' => $position->id],
            ['assignment_type' => 'location', 'new_value_id' => $location->id],
            ['assignment_type' => 'supervisor', 'new_value_id' => $supervisor->id],
        ];

        foreach ($validCases as $case) {
            $request = new StoreEmployeeAssignmentRequest($case + ['effective_date' => now()->format('Y-m-d')]);
            $request->setContainer(app());
            $rules = $request->rules();

            $validator = Validator::make($case + ['effective_date' => now()->format('Y-m-d')], $rules);
            expect($validator->fails())->toBeFalse("Should pass for assignment_type: {$case['assignment_type']}");
        }
    });
});
