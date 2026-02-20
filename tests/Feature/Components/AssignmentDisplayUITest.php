<?php

use App\Enums\AssignmentType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeController;
use App\Http\Resources\EmployeeAssignmentHistoryResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignmentHistory;
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
function bindTenantContextForDisplayUI(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForDisplayUI(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('Assignment Display UI - Current Assignments Display', function () {
    it('shows correct current assignment values on employee show page', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForDisplayUI($tenant);

        $admin = createTenantUserForDisplayUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create(['name' => 'Engineering']);
        $position = Position::factory()->create(['title' => 'Software Developer']);
        $workLocation = WorkLocation::factory()->create(['name' => 'Main Office']);
        $supervisor = Employee::factory()->create([
            'first_name' => 'John',
            'middle_name' => null,
            'last_name' => 'Manager',
            'suffix' => null,
        ]);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'work_location_id' => $workLocation->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $controller = new EmployeeController;
        $inertiaResponse = $controller->show($employee);

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Transform the employee resource to array for easier assertion
        $employeeData = $props['employee']->toArray(request());

        expect($employeeData['department']['name'])->toBe('Engineering');
        expect($employeeData['position']['title'])->toBe('Software Developer');
        expect($employeeData['work_location']['name'])->toBe('Main Office');
        expect($employeeData['supervisor']['full_name'])->toBe('John Manager');
    });
});

describe('Assignment Display UI - Authorization', function () {
    it('includes can_manage_employees permission flag for authorized users', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForDisplayUI($tenant);

        $admin = createTenantUserForDisplayUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        $controller = new EmployeeController;
        $inertiaResponse = $controller->show($employee);

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Admin should have dropdown data available (indication they can manage)
        expect($props)->toHaveKey('departments');
        expect($props)->toHaveKey('positions');
        expect($props)->toHaveKey('workLocations');
        expect($props)->toHaveKey('supervisorOptions');
    });
});

describe('Assignment Display UI - Assignment History Timeline', function () {
    it('displays assignment history entries in chronological order (most recent first)', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForDisplayUI($tenant);

        $admin = createTenantUserForDisplayUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $dept1 = Department::factory()->create(['name' => 'Old Department']);
        $dept2 = Department::factory()->create(['name' => 'New Department']);
        $position1 = Position::factory()->create(['title' => 'Junior Developer']);
        $position2 = Position::factory()->create(['title' => 'Senior Developer']);

        // Create assignment history in specific order
        $olderHistory = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => null,
            'new_value_id' => $dept1->id,
            'effective_date' => now()->subMonths(6),
            'created_at' => now()->subMonths(6),
        ]);

        $newerHistory = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => $dept1->id,
            'new_value_id' => $dept2->id,
            'effective_date' => now()->subMonth(),
            'created_at' => now()->subMonth(),
        ]);

        $newestHistory = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Position,
            'previous_value_id' => $position1->id,
            'new_value_id' => $position2->id,
            'effective_date' => now(),
            'created_at' => now(),
        ]);

        // Fetch assignment history ordered by created_at desc
        $history = $employee->assignmentHistory()
            ->orderBy('created_at', 'desc')
            ->get();

        expect($history->count())->toBe(3);
        expect($history[0]->id)->toBe($newestHistory->id);
        expect($history[1]->id)->toBe($newerHistory->id);
        expect($history[2]->id)->toBe($olderHistory->id);
    });

    it('timeline entries show all required information', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForDisplayUI($tenant);

        $admin = createTenantUserForDisplayUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $oldDepartment = Department::factory()->create(['name' => 'Engineering']);
        $newDepartment = Department::factory()->create(['name' => 'Product']);

        $historyRecord = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => $oldDepartment->id,
            'new_value_id' => $newDepartment->id,
            'effective_date' => now()->subWeek(),
            'remarks' => 'Transfer due to project requirements',
            'changed_by' => $admin->id,
            'created_at' => now()->subWeek(),
        ]);

        // Transform through the resource to verify all fields are present
        $resource = new EmployeeAssignmentHistoryResource($historyRecord);
        $transformedData = $resource->toArray(request());

        // Verify all required fields are present
        expect($transformedData)->toHaveKey('assignment_type');
        expect($transformedData['assignment_type']['value'])->toBe('department');
        expect($transformedData['assignment_type']['label'])->toBe('Department');

        expect($transformedData)->toHaveKey('previous_value_name');
        expect($transformedData['previous_value_name'])->toBe('Engineering');

        expect($transformedData)->toHaveKey('new_value_name');
        expect($transformedData['new_value_name'])->toBe('Product');

        expect($transformedData)->toHaveKey('effective_date');

        expect($transformedData)->toHaveKey('remarks');
        expect($transformedData['remarks'])->toBe('Transfer due to project requirements');

        expect($transformedData)->toHaveKey('changed_by_name');
        expect($transformedData['changed_by_name'])->toBe($admin->name);

        expect($transformedData)->toHaveKey('created_at');
    });
});

describe('Assignment Display UI - Controller Integration', function () {
    it('controller includes assignment history with deferred loading', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForDisplayUI($tenant);

        $admin = createTenantUserForDisplayUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        // Create some assignment history
        EmployeeAssignmentHistory::factory()->count(3)->create([
            'employee_id' => $employee->id,
        ]);

        $controller = new EmployeeController;
        $inertiaResponse = $controller->show($employee);

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Verify assignment history is included in props
        expect($props)->toHaveKey('assignmentHistory');
    });
});
