<?php

use App\Enums\AssignmentType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeAssignmentController;
use App\Http\Controllers\EmployeeController;
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
function bindTenantContextForAssignmentIntegration(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForAssignmentIntegration(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function createStoreAssignmentRequestForIntegration(array $data, User $user): StoreEmployeeAssignmentRequest
{
    $request = StoreEmployeeAssignmentRequest::create('/api/employees/1/assignments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    // Create the validator using the request's rules method so it has access to input
    $validator = Validator::make($data, $request->rules());

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

describe('Employee Assignment Management Full Integration Flow', function () {
    it('displays employee profile page with current assignments and dropdown data', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForAssignmentIntegration($tenant);

        $admin = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create(['name' => 'Engineering']);
        $position = Position::factory()->create(['title' => 'Software Developer']);
        $workLocation = WorkLocation::factory()->create(['name' => 'Head Office']);
        $supervisor = Employee::factory()->create([
            'first_name' => 'Super',
            'middle_name' => null, // Explicitly null to avoid factory middle name
            'last_name' => 'Visor',
        ]);

        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'work_location_id' => $workLocation->id,
            'supervisor_id' => $supervisor->id,
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

        // Check employee data with assignments
        $employeeData = $props['employee']->toArray(request());
        expect($employeeData['first_name'])->toBe('John');
        expect($employeeData['last_name'])->toBe('Doe');
        expect($employeeData['department']['name'])->toBe('Engineering');
        expect($employeeData['position']['title'])->toBe('Software Developer');
        expect($employeeData['work_location']['name'])->toBe('Head Office');
        expect($employeeData['supervisor']['full_name'])->toBe('Super Visor');

        // Verify dropdown data is present (for assignment modal)
        expect($props)->toHaveKey('departments');
        expect($props)->toHaveKey('positions');
        expect($props)->toHaveKey('workLocations');
        expect($props)->toHaveKey('supervisorOptions');
    });

    it('allows authorized user to create new assignment via controller', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForAssignmentIntegration($tenant);

        $admin = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $oldDepartment = Department::factory()->create(['name' => 'Sales']);
        $newDepartment = Department::factory()->create(['name' => 'Marketing']);

        $employee = Employee::factory()->create([
            'department_id' => $oldDepartment->id,
        ]);

        // Create initial assignment history
        EmployeeAssignmentHistory::factory()->create([
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
            'remarks' => 'Transferred to Marketing department',
        ];

        $request = createStoreAssignmentRequestForIntegration($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        $assignmentData = $data['data'] ?? $data;

        expect($assignmentData['assignment_type']['value'])->toBe('department');
        expect($assignmentData['new_value_id'])->toBe($newDepartment->id);
        expect($assignmentData['remarks'])->toBe('Transferred to Marketing department');

        // Verify employee was updated
        $employee->refresh();
        expect($employee->department_id)->toBe($newDepartment->id);

        // Verify assignment history was created
        $currentAssignment = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->forType(AssignmentType::Department)
            ->current()
            ->first();

        expect($currentAssignment)->not->toBeNull();
        expect($currentAssignment->new_value_id)->toBe($newDepartment->id);
        expect($currentAssignment->previous_value_id)->toBe($oldDepartment->id);
    });

    it('returns assignment history via controller index method', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForAssignmentIntegration($tenant);

        $admin = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create(['name' => 'Engineering']);
        $position = Position::factory()->create(['title' => 'Developer']);

        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        // Create multiple assignment history records
        EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => null,
            'new_value_id' => $department->id,
            'effective_date' => now()->subMonth(),
            'remarks' => 'Initial department',
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

        // Check structure of first resource
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
    });

    it('prohibits unauthorized users from creating assignments', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForAssignmentIntegration($tenant);

        // Admin should have access
        $admin = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Admin);
        expect(Gate::forUser($admin)->allows('can-manage-employees'))->toBeTrue();

        // HR Manager should have access
        $hrManager = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::HrManager);
        expect(Gate::forUser($hrManager)->allows('can-manage-employees'))->toBeTrue();

        // User with Employee role cannot manage employees
        $employeeUser = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Employee);
        expect(Gate::forUser($employeeUser)->allows('can-manage-employees'))->toBeFalse();

        // Supervisor cannot manage employees (only has view permission)
        $supervisor = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Supervisor);
        expect(Gate::forUser($supervisor)->allows('can-manage-employees'))->toBeFalse();
    });

    it('validates required fields when creating assignment', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForAssignmentIntegration($tenant);

        // Create a request with empty data to check validation
        $request = new StoreEmployeeAssignmentRequest;
        $request->setContainer(app());

        // Test validation with missing required fields using the request's rules
        $validator = Validator::make([], $request->rules());
        expect($validator->fails())->toBeTrue();

        $errors = $validator->errors();
        expect($errors->has('assignment_type'))->toBeTrue();
        expect($errors->has('new_value_id'))->toBeTrue();
        expect($errors->has('effective_date'))->toBeTrue();
    });
});

describe('Assignment Change Workflow', function () {
    it('ends previous assignment when creating new assignment of same type', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForAssignmentIntegration($tenant);

        $admin = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $position1 = Position::factory()->create(['title' => 'Junior Developer']);
        $position2 = Position::factory()->create(['title' => 'Senior Developer']);

        $employee = Employee::factory()->create([
            'position_id' => $position1->id,
        ]);

        // Create initial assignment history
        $initialAssignment = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Position,
            'previous_value_id' => null,
            'new_value_id' => $position1->id,
            'effective_date' => now()->subYear(),
            'ended_at' => null,
        ]);

        // Create new assignment (promotion) - need to include assignment_type in data
        $requestData = [
            'assignment_type' => 'position',
            'new_value_id' => $position2->id,
            'effective_date' => now()->format('Y-m-d'),
            'remarks' => 'Promoted to Senior Developer',
        ];

        $request = createStoreAssignmentRequestForIntegration($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        // Verify previous assignment was ended
        $initialAssignment->refresh();
        expect($initialAssignment->ended_at)->not->toBeNull();

        // Verify only one current assignment exists for this type
        $currentPositionAssignments = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->forType(AssignmentType::Position)
            ->current()
            ->count();

        expect($currentPositionAssignments)->toBe(1);
    });

    it('allows multiple concurrent assignments of different types', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForAssignmentIntegration($tenant);

        $admin = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create();
        $position = Position::factory()->create();
        $location = WorkLocation::factory()->create();
        $supervisor = Employee::factory()->create();

        $employee = Employee::factory()->create();

        $controller = new EmployeeAssignmentController(new AssignmentService);

        // Create assignments for all types
        $assignmentTypes = [
            ['type' => 'department', 'id' => $department->id],
            ['type' => 'position', 'id' => $position->id],
            ['type' => 'location', 'id' => $location->id],
            ['type' => 'supervisor', 'id' => $supervisor->id],
        ];

        foreach ($assignmentTypes as $assignment) {
            $requestData = [
                'assignment_type' => $assignment['type'],
                'new_value_id' => $assignment['id'],
                'effective_date' => now()->format('Y-m-d'),
            ];

            $request = createStoreAssignmentRequestForIntegration($requestData, $admin);
            $request->merge($requestData);

            $response = $controller->store($request, $employee);
            expect($response->getStatusCode())->toBe(201);
        }

        // All 4 current assignments should exist
        $currentAssignments = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->current()
            ->count();

        expect($currentAssignments)->toBe(4);

        // Each assignment type should have exactly one current record
        foreach (AssignmentType::cases() as $type) {
            $count = EmployeeAssignmentHistory::where('employee_id', $employee->id)
                ->forType($type)
                ->current()
                ->count();

            expect($count)->toBe(1, "Expected 1 current assignment for type: {$type->value}");
        }
    });

    it('tracks changed_by user when creating assignments', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForAssignmentIntegration($tenant);

        $admin = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create();
        $employee = Employee::factory()->create();

        $requestData = [
            'assignment_type' => 'department',
            'new_value_id' => $department->id,
            'effective_date' => now()->format('Y-m-d'),
        ];

        $request = createStoreAssignmentRequestForIntegration($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        $assignment = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->latest()
            ->first();

        expect($assignment->changed_by)->toBe($admin->id);
    });
});

describe('Employee Profile Page Integration', function () {
    it('loads dropdown data for assignment modal for authorized users', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForAssignmentIntegration($tenant);

        $admin = createTenantUserForAssignmentIntegration($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create some dropdown options
        $dept1 = Department::factory()->create(['name' => 'Engineering']);
        $dept2 = Department::factory()->create(['name' => 'Sales']);
        $dept3 = Department::factory()->create(['name' => 'HR']);

        $pos1 = Position::factory()->create(['title' => 'Developer']);
        $pos2 = Position::factory()->create(['title' => 'Manager']);
        $pos3 = Position::factory()->create(['title' => 'Director']);

        $loc1 = WorkLocation::factory()->create(['name' => 'Head Office']);
        $loc2 = WorkLocation::factory()->create(['name' => 'Branch Office']);

        $supervisor1 = Employee::factory()->create(['first_name' => 'Super', 'last_name' => 'Visor']);
        $supervisor2 = Employee::factory()->create(['first_name' => 'Big', 'last_name' => 'Boss']);

        $employee = Employee::factory()->create();

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

        // Check department count
        $deptCollection = $props['departments'];
        expect(count($deptCollection))->toBe(3);

        // Check position count
        $posCollection = $props['positions'];
        expect(count($posCollection))->toBe(3);

        // Check work location count
        $locCollection = $props['workLocations'];
        expect(count($locCollection))->toBe(2);

        // Check supervisor options - should include all employees except the current one
        $supCollection = $props['supervisorOptions'];
        // Should have the 2 supervisors we created + the current employee (3 total) minus the current employee = should be at least 2
        expect(count($supCollection))->toBeGreaterThanOrEqual(2);
    });
});
