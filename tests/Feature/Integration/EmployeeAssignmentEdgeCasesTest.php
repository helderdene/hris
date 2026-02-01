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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForEdgeCases(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForEdgeCases(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function createStoreAssignmentRequestForEdgeCases(array $data, User $user): StoreEmployeeAssignmentRequest
{
    $request = StoreEmployeeAssignmentRequest::create('/api/employees/1/assignments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rulesRequest = new StoreEmployeeAssignmentRequest($data);
    $rulesRequest->setContainer(app());

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

describe('Edge Case: Employee with no existing assignments', function () {
    it('creates first assignment when employee has no prior assignment history', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEdgeCases($tenant);

        $admin = createTenantUserForEdgeCases($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create(['name' => 'Engineering']);

        // Create employee with no assignments
        $employee = Employee::factory()->create([
            'department_id' => null,
            'position_id' => null,
            'work_location_id' => null,
            'supervisor_id' => null,
        ]);

        // Verify no assignment history exists
        expect(EmployeeAssignmentHistory::where('employee_id', $employee->id)->count())->toBe(0);

        $requestData = [
            'assignment_type' => 'department',
            'new_value_id' => $department->id,
            'effective_date' => now()->format('Y-m-d'),
            'remarks' => 'First department assignment',
        ];

        $request = createStoreAssignmentRequestForEdgeCases($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        $assignmentData = $data['data'] ?? $data;

        // Verify previous_value_id is null for first assignment
        expect($assignmentData['previous_value_id'])->toBeNull();
        expect($assignmentData['new_value_id'])->toBe($department->id);

        // Verify employee was updated
        $employee->refresh();
        expect($employee->department_id)->toBe($department->id);

        // Verify history record was created
        expect(EmployeeAssignmentHistory::where('employee_id', $employee->id)->count())->toBe(1);
    });
});

describe('Edge Case: Changing to same assignment value', function () {
    it('allows changing to the same value and creates history record', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEdgeCases($tenant);

        $admin = createTenantUserForEdgeCases($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create(['name' => 'Engineering']);
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        // Create existing assignment
        $existingAssignment = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => null,
            'new_value_id' => $department->id,
            'effective_date' => now()->subMonth(),
            'ended_at' => null,
        ]);

        // Try to assign the same department again
        $requestData = [
            'assignment_type' => 'department',
            'new_value_id' => $department->id,
            'effective_date' => now()->format('Y-m-d'),
            'remarks' => 'Reconfirming department assignment',
        ];

        $request = createStoreAssignmentRequestForEdgeCases($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        // Verify the previous assignment was ended
        $existingAssignment->refresh();
        expect($existingAssignment->ended_at)->not->toBeNull();

        // Verify new assignment was created
        $newAssignment = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->forType(AssignmentType::Department)
            ->current()
            ->first();

        expect($newAssignment->id)->not->toBe($existingAssignment->id);
        expect($newAssignment->previous_value_id)->toBe($department->id);
        expect($newAssignment->new_value_id)->toBe($department->id);
    });
});

describe('Edge Case: Supervisor assignment type', function () {
    it('creates supervisor assignment correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEdgeCases($tenant);

        $admin = createTenantUserForEdgeCases($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $oldSupervisor = Employee::factory()->create([
            'first_name' => 'Old',
            'middle_name' => null,
            'last_name' => 'Supervisor',
        ]);
        $newSupervisor = Employee::factory()->create([
            'first_name' => 'New',
            'middle_name' => null,
            'last_name' => 'Supervisor',
        ]);
        $employee = Employee::factory()->create(['supervisor_id' => $oldSupervisor->id]);

        // Create existing supervisor assignment
        EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Supervisor,
            'previous_value_id' => null,
            'new_value_id' => $oldSupervisor->id,
            'effective_date' => now()->subMonth(),
            'ended_at' => null,
        ]);

        $requestData = [
            'assignment_type' => 'supervisor',
            'new_value_id' => $newSupervisor->id,
            'effective_date' => now()->format('Y-m-d'),
            'remarks' => 'Changed supervisor',
        ];

        $request = createStoreAssignmentRequestForEdgeCases($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController(new AssignmentService);
        $response = $controller->store($request, $employee);

        expect($response->getStatusCode())->toBe(201);

        // Verify employee was updated
        $employee->refresh();
        expect($employee->supervisor_id)->toBe($newSupervisor->id);

        // Verify history has correct values
        $currentAssignment = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->forType(AssignmentType::Supervisor)
            ->current()
            ->first();

        expect($currentAssignment->previous_value_id)->toBe($oldSupervisor->id);
        expect($currentAssignment->new_value_id)->toBe($newSupervisor->id);
    });
});

describe('Edge Case: Value name resolution for deleted entities', function () {
    it('handles deleted department gracefully when resolving names', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEdgeCases($tenant);

        $admin = createTenantUserForEdgeCases($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create(['name' => 'Deleted Department']);
        $employee = Employee::factory()->create();

        // Create assignment history referencing the department
        $history = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => null,
            'new_value_id' => $department->id,
            'effective_date' => now(),
        ]);

        // Verify name resolves correctly before deletion
        expect($history->new_value_name)->toBe('Deleted Department');

        // Soft delete or hard delete the department
        $department->delete();

        // Refresh the history to clear any cached values
        $history->refresh();

        // The name resolution should return null for deleted entity
        expect($history->new_value_name)->toBeNull();
    });
});

describe('Edge Case: Transaction atomicity', function () {
    it('rolls back changes if error occurs during assignment creation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEdgeCases($tenant);

        $admin = createTenantUserForEdgeCases($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => null]);

        // Create an existing assignment
        $existingAssignment = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => null,
            'new_value_id' => $department->id,
            'effective_date' => now()->subMonth(),
            'ended_at' => null,
        ]);

        // Record initial state
        $initialHistoryCount = EmployeeAssignmentHistory::where('employee_id', $employee->id)->count();

        // Mock the service to throw an exception during creation
        $mockService = new class extends AssignmentService
        {
            public function createAssignment(Employee $employee, array $data, ?int $changedBy = null): EmployeeAssignmentHistory
            {
                return DB::transaction(function () use ($employee, $data) {
                    $assignmentType = AssignmentType::from($data['assignment_type']);

                    // End the previous assignment
                    EmployeeAssignmentHistory::query()
                        ->where('employee_id', $employee->id)
                        ->where('assignment_type', $assignmentType)
                        ->whereNull('ended_at')
                        ->update(['ended_at' => now()]);

                    // Simulate an error after ending but before creating new record
                    throw new \RuntimeException('Simulated database error');
                });
            }
        };

        // Try to create assignment
        $requestData = [
            'assignment_type' => 'department',
            'new_value_id' => $department->id,
            'effective_date' => now()->format('Y-m-d'),
        ];

        $request = createStoreAssignmentRequestForEdgeCases($requestData, $admin);
        $request->merge($requestData);

        $controller = new EmployeeAssignmentController($mockService);

        try {
            $controller->store($request, $employee);
        } catch (\RuntimeException) {
            // Expected exception
        }

        // Verify the previous assignment was NOT ended (transaction was rolled back)
        $existingAssignment->refresh();
        expect($existingAssignment->ended_at)->toBeNull();

        // Verify no new history records were created
        expect(EmployeeAssignmentHistory::where('employee_id', $employee->id)->count())->toBe($initialHistoryCount);
    });
});

describe('Edge Case: Multiple rapid assignment changes', function () {
    it('maintains data integrity when multiple assignments are created in quick succession', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEdgeCases($tenant);

        $admin = createTenantUserForEdgeCases($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $departments = Department::factory()->count(5)->create();
        $employee = Employee::factory()->create(['department_id' => null]);

        $controller = new EmployeeAssignmentController(new AssignmentService);

        // Create 5 department changes rapidly
        foreach ($departments as $index => $department) {
            $requestData = [
                'assignment_type' => 'department',
                'new_value_id' => $department->id,
                'effective_date' => now()->format('Y-m-d'),
                'remarks' => "Change #{$index}",
            ];

            $request = createStoreAssignmentRequestForEdgeCases($requestData, $admin);
            $request->merge($requestData);

            $response = $controller->store($request, $employee);
            expect($response->getStatusCode())->toBe(201);
        }

        // Verify only one current assignment exists
        $currentAssignments = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->forType(AssignmentType::Department)
            ->current()
            ->count();

        expect($currentAssignments)->toBe(1);

        // Verify all 5 history records exist
        $totalHistoryRecords = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->forType(AssignmentType::Department)
            ->count();

        expect($totalHistoryRecords)->toBe(5);

        // Verify ended_at is properly set on all but the last record
        $endedRecords = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->forType(AssignmentType::Department)
            ->whereNotNull('ended_at')
            ->count();

        expect($endedRecords)->toBe(4);

        // Verify employee has the last department assigned
        $employee->refresh();
        expect($employee->department_id)->toBe($departments->last()->id);
    });
});

describe('Edge Case: All assignment types in sequence', function () {
    it('correctly handles sequential changes across all assignment types', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEdgeCases($tenant);

        $admin = createTenantUserForEdgeCases($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create initial entities
        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();
        $pos1 = Position::factory()->create();
        $pos2 = Position::factory()->create();
        $loc1 = WorkLocation::factory()->create();
        $loc2 = WorkLocation::factory()->create();
        $sup1 = Employee::factory()->create();
        $sup2 = Employee::factory()->create();

        $employee = Employee::factory()->create([
            'department_id' => $dept1->id,
            'position_id' => $pos1->id,
            'work_location_id' => $loc1->id,
            'supervisor_id' => $sup1->id,
        ]);

        // Create initial assignment history for all types
        foreach (AssignmentType::cases() as $type) {
            $valueId = match ($type) {
                AssignmentType::Department => $dept1->id,
                AssignmentType::Position => $pos1->id,
                AssignmentType::Location => $loc1->id,
                AssignmentType::Supervisor => $sup1->id,
            };

            EmployeeAssignmentHistory::factory()->create([
                'employee_id' => $employee->id,
                'assignment_type' => $type,
                'previous_value_id' => null,
                'new_value_id' => $valueId,
                'effective_date' => now()->subMonth(),
                'ended_at' => null,
            ]);
        }

        $controller = new EmployeeAssignmentController(new AssignmentService);

        // Change all assignment types to new values
        $changes = [
            ['type' => 'department', 'id' => $dept2->id],
            ['type' => 'position', 'id' => $pos2->id],
            ['type' => 'location', 'id' => $loc2->id],
            ['type' => 'supervisor', 'id' => $sup2->id],
        ];

        foreach ($changes as $change) {
            $requestData = [
                'assignment_type' => $change['type'],
                'new_value_id' => $change['id'],
                'effective_date' => now()->format('Y-m-d'),
            ];

            $request = createStoreAssignmentRequestForEdgeCases($requestData, $admin);
            $request->merge($requestData);

            $response = $controller->store($request, $employee);
            expect($response->getStatusCode())->toBe(201);
        }

        // Verify exactly 4 current assignments (one per type)
        $currentCount = EmployeeAssignmentHistory::where('employee_id', $employee->id)
            ->current()
            ->count();

        expect($currentCount)->toBe(4);

        // Verify each type has exactly one current assignment
        foreach (AssignmentType::cases() as $type) {
            $typeCount = EmployeeAssignmentHistory::where('employee_id', $employee->id)
                ->forType($type)
                ->current()
                ->count();

            expect($typeCount)->toBe(1, "Expected exactly 1 current assignment for type: {$type->value}");
        }

        // Verify employee model was updated
        $employee->refresh();
        expect($employee->department_id)->toBe($dept2->id);
        expect($employee->position_id)->toBe($pos2->id);
        expect($employee->work_location_id)->toBe($loc2->id);
        expect($employee->supervisor_id)->toBe($sup2->id);
    });
});
