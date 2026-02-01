<?php

use App\Enums\AssignmentType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeAssignmentController;
use App\Http\Controllers\OrganizationController;
use App\Http\Requests\StoreEmployeeAssignmentRequest;
use App\Models\Department;
use App\Models\DepartmentHeadHistory;
use App\Models\Employee;
use App\Models\EmployeeAssignmentHistory;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForDeptHead(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForDeptHead(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
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
function createStoreAssignmentRequestForDeptHead(array $data, User $user): StoreEmployeeAssignmentRequest
{
    $request = StoreEmployeeAssignmentRequest::create('/api/employees/1/assignments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

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

    // Set up tenant context
    $this->tenant = Tenant::factory()->create(['slug' => 'test-company']);
    bindTenantContextForDeptHead($this->tenant);

    // Create admin user
    $this->user = createTenantUserForDeptHead($this->tenant, TenantUserRole::Admin);
    $this->actingAs($this->user);
});

it('sets employee as department head when checkbox is checked', function () {
    $employee = Employee::factory()->create();
    $department = Department::factory()->create();

    $requestData = [
        'assignment_type' => 'department',
        'new_value_id' => $department->id,
        'effective_date' => now()->toDateString(),
        'remarks' => 'Promoted to department head',
        'set_as_department_head' => true,
    ];

    $request = createStoreAssignmentRequestForDeptHead($requestData, $this->user);
    $controller = new EmployeeAssignmentController(new AssignmentService);
    $response = $controller->store($request, $this->tenant->slug, $employee);

    expect($response->getStatusCode())->toBe(201);

    // Verify employee is assigned to department
    $employee->refresh();
    expect($employee->department_id)->toBe($department->id);

    // Verify department head is set
    $department->refresh();
    expect($department->department_head_id)->toBe($employee->id);

    // Verify department head history is created
    $history = DepartmentHeadHistory::where('department_id', $department->id)
        ->where('employee_id', $employee->id)
        ->whereNull('ended_at')
        ->first();

    expect($history)->not->toBeNull();
    expect($history->started_at->toDateString())->toBe(now()->toDateString());
});

it('does not set department head when checkbox is unchecked', function () {
    $employee = Employee::factory()->create();
    $department = Department::factory()->create();

    $requestData = [
        'assignment_type' => 'department',
        'new_value_id' => $department->id,
        'effective_date' => now()->toDateString(),
        'set_as_department_head' => false,
    ];

    $request = createStoreAssignmentRequestForDeptHead($requestData, $this->user);
    $controller = new EmployeeAssignmentController(new AssignmentService);
    $response = $controller->store($request, $this->tenant->slug, $employee);

    expect($response->getStatusCode())->toBe(201);

    // Verify employee is assigned to department
    $employee->refresh();
    expect($employee->department_id)->toBe($department->id);

    // Verify department head is NOT set
    $department->refresh();
    expect($department->department_head_id)->toBeNull();

    // Verify no department head history is created
    $history = DepartmentHeadHistory::where('department_id', $department->id)
        ->where('employee_id', $employee->id)
        ->first();

    expect($history)->toBeNull();
});

it('does not set department head when checkbox is not provided', function () {
    $employee = Employee::factory()->create();
    $department = Department::factory()->create();

    $requestData = [
        'assignment_type' => 'department',
        'new_value_id' => $department->id,
        'effective_date' => now()->toDateString(),
        // set_as_department_head not provided
    ];

    $request = createStoreAssignmentRequestForDeptHead($requestData, $this->user);
    $controller = new EmployeeAssignmentController(new AssignmentService);
    $response = $controller->store($request, $this->tenant->slug, $employee);

    expect($response->getStatusCode())->toBe(201);

    // Verify department head is NOT set
    $department->refresh();
    expect($department->department_head_id)->toBeNull();
});

it('replaces previous department head when new one is assigned', function () {
    $previousHead = Employee::factory()->create();
    $newHead = Employee::factory()->create();
    $department = Department::factory()->create([
        'department_head_id' => $previousHead->id,
    ]);

    // Create existing department head history
    DepartmentHeadHistory::create([
        'department_id' => $department->id,
        'employee_id' => $previousHead->id,
        'started_at' => now()->subMonth(),
        'ended_at' => null,
    ]);

    $requestData = [
        'assignment_type' => 'department',
        'new_value_id' => $department->id,
        'effective_date' => now()->toDateString(),
        'set_as_department_head' => true,
    ];

    $request = createStoreAssignmentRequestForDeptHead($requestData, $this->user);
    $controller = new EmployeeAssignmentController(new AssignmentService);
    $response = $controller->store($request, $this->tenant->slug, $newHead);

    expect($response->getStatusCode())->toBe(201);

    // Verify new department head is set
    $department->refresh();
    expect($department->department_head_id)->toBe($newHead->id);

    // Verify previous head history is ended
    $previousHistory = DepartmentHeadHistory::where('department_id', $department->id)
        ->where('employee_id', $previousHead->id)
        ->first();

    expect($previousHistory->ended_at)->not->toBeNull();

    // Verify new head history is created
    $newHistory = DepartmentHeadHistory::where('department_id', $department->id)
        ->where('employee_id', $newHead->id)
        ->whereNull('ended_at')
        ->first();

    expect($newHistory)->not->toBeNull();
});

it('only applies department head setting for department assignment type', function () {
    $employee = Employee::factory()->create();
    $position = Position::factory()->create();

    $requestData = [
        'assignment_type' => 'position',
        'new_value_id' => $position->id,
        'effective_date' => now()->toDateString(),
        'set_as_department_head' => true, // Should be ignored for position
    ];

    $request = createStoreAssignmentRequestForDeptHead($requestData, $this->user);
    $controller = new EmployeeAssignmentController(new AssignmentService);
    $response = $controller->store($request, $this->tenant->slug, $employee);

    expect($response->getStatusCode())->toBe(201);

    // Verify no department head history is created
    $history = DepartmentHeadHistory::where('employee_id', $employee->id)->first();
    expect($history)->toBeNull();
});

it('creates assignment history regardless of department head setting', function () {
    $employee = Employee::factory()->create();
    $department = Department::factory()->create();

    $requestData = [
        'assignment_type' => 'department',
        'new_value_id' => $department->id,
        'effective_date' => now()->toDateString(),
        'remarks' => 'Test assignment',
        'set_as_department_head' => true,
    ];

    $request = createStoreAssignmentRequestForDeptHead($requestData, $this->user);
    $controller = new EmployeeAssignmentController(new AssignmentService);
    $controller->store($request, $this->tenant->slug, $employee);

    // Verify assignment history is created
    $assignmentHistory = EmployeeAssignmentHistory::where('employee_id', $employee->id)
        ->where('assignment_type', AssignmentType::Department)
        ->first();

    expect($assignmentHistory)->not->toBeNull();
    expect($assignmentHistory->new_value_id)->toBe($department->id);
    expect($assignmentHistory->remarks)->toBe('Test assignment');
});

it('org chart reflects the new department head', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'middle_name' => null,
    ]);
    $department = Department::factory()->create();

    // Assign employee as department head
    $requestData = [
        'assignment_type' => 'department',
        'new_value_id' => $department->id,
        'effective_date' => now()->toDateString(),
        'set_as_department_head' => true,
    ];

    $request = createStoreAssignmentRequestForDeptHead($requestData, $this->user);
    $controller = new EmployeeAssignmentController(new AssignmentService);
    $controller->store($request, $this->tenant->slug, $employee);

    // Access org chart via controller
    $orgController = new OrganizationController;
    $inertiaResponse = $orgController->orgChart();

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check that department data includes the head info
    $departments = collect($props['departments']->toArray(request()));
    $dept = $departments->firstWhere('id', $department->id);

    expect($dept['department_head_id'])->toBe($employee->id);
    expect($dept['department_head_name'])->toBe('John Doe');
});
