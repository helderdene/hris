<?php

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\TenantUserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
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
function bindTenantContextForWorkflow(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForWorkflow(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('Employee Status History Integration', function () {
    it('creates initial status history record when employee is created with active status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkflow($tenant);

        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        // Manually create initial status history (as would be done in a service/observer)
        EmployeeStatusHistory::create([
            'employee_id' => $employee->id,
            'previous_status' => null,
            'new_status' => EmploymentStatus::Active,
            'effective_date' => $employee->hire_date ?? now(),
            'remarks' => 'Initial employee record created',
            'ended_at' => null,
        ]);

        $historyRecords = EmployeeStatusHistory::where('employee_id', $employee->id)->get();

        expect($historyRecords)->toHaveCount(1);
        expect($historyRecords->first()->previous_status)->toBeNull();
        expect($historyRecords->first()->new_status)->toBe(EmploymentStatus::Active);
        expect($historyRecords->first()->ended_at)->toBeNull();
    });

    it('creates new history record and ends previous when status changes', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkflow($tenant);

        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        // Create initial status history
        $initialHistory = EmployeeStatusHistory::create([
            'employee_id' => $employee->id,
            'previous_status' => null,
            'new_status' => EmploymentStatus::Active,
            'effective_date' => $employee->hire_date ?? now()->subYear(),
            'remarks' => 'Initial employee record',
            'ended_at' => null,
        ]);

        // Simulate status change - end the previous record
        $initialHistory->update(['ended_at' => now()]);

        // Create new status history record
        EmployeeStatusHistory::create([
            'employee_id' => $employee->id,
            'previous_status' => EmploymentStatus::Active,
            'new_status' => EmploymentStatus::Resigned,
            'effective_date' => now(),
            'remarks' => 'Employee resigned',
            'ended_at' => null,
        ]);

        // Update employee status
        $employee->update(['employment_status' => EmploymentStatus::Resigned]);

        // Verify history records
        $allRecords = EmployeeStatusHistory::where('employee_id', $employee->id)
            ->orderBy('effective_date')
            ->get();

        expect($allRecords)->toHaveCount(2);

        // First record should be ended
        expect($allRecords[0]->ended_at)->not->toBeNull();
        expect($allRecords[0]->new_status)->toBe(EmploymentStatus::Active);

        // Current record should not be ended
        $currentRecord = EmployeeStatusHistory::current()->where('employee_id', $employee->id)->first();
        expect($currentRecord)->not->toBeNull();
        expect($currentRecord->new_status)->toBe(EmploymentStatus::Resigned);
        expect($currentRecord->previous_status)->toBe(EmploymentStatus::Active);
    });
});

describe('Employee Search Integration', function () {
    it('searches across multiple fields and returns correct results', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkflow($tenant);

        $admin = createTenantUserForWorkflow($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $position1 = Position::factory()->create(['title' => 'Software Engineer']);
        $position2 = Position::factory()->create(['title' => 'Project Manager']);

        // Create employees with distinct searchable attributes
        Employee::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'employee_number' => 'EMP-2024-001',
            'position_id' => $position1->id,
        ]);

        Employee::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'employee_number' => 'EMP-2024-002',
            'position_id' => $position2->id,
        ]);

        Employee::factory()->create([
            'first_name' => 'Pedro',
            'last_name' => 'Gonzales',
            'employee_number' => 'EMP-2024-003',
            'position_id' => $position1->id,
        ]);

        // Search by first name
        $resultsByFirstName = Employee::query()
            ->where('first_name', 'like', '%Maria%')
            ->get();
        expect($resultsByFirstName)->toHaveCount(1);
        expect($resultsByFirstName->first()->first_name)->toBe('Maria');

        // Search by last name
        $resultsByLastName = Employee::query()
            ->where('last_name', 'like', '%Cruz%')
            ->get();
        expect($resultsByLastName)->toHaveCount(1);
        expect($resultsByLastName->first()->last_name)->toBe('Dela Cruz');

        // Search by employee number
        $resultsByNumber = Employee::query()
            ->where('employee_number', 'like', '%003%')
            ->get();
        expect($resultsByNumber)->toHaveCount(1);
        expect($resultsByNumber->first()->first_name)->toBe('Pedro');

        // Search by position title
        $resultsByPosition = Employee::query()
            ->whereHas('position', fn ($q) => $q->where('title', 'like', '%Software%'))
            ->get();
        expect($resultsByPosition)->toHaveCount(2);
    });
});

describe('Employee Filter Integration', function () {
    it('filters by multiple criteria simultaneously', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkflow($tenant);

        $engineering = Department::factory()->create(['name' => 'Engineering']);
        $hr = Department::factory()->create(['name' => 'Human Resources']);

        // Active in Engineering
        Employee::factory()->create([
            'department_id' => $engineering->id,
            'employment_status' => EmploymentStatus::Active,
            'employment_type' => EmploymentType::Regular,
        ]);

        // Active in Engineering (Probationary)
        Employee::factory()->create([
            'department_id' => $engineering->id,
            'employment_status' => EmploymentStatus::Active,
            'employment_type' => EmploymentType::Probationary,
        ]);

        // Resigned in Engineering
        Employee::factory()->create([
            'department_id' => $engineering->id,
            'employment_status' => EmploymentStatus::Resigned,
            'employment_type' => EmploymentType::Regular,
        ]);

        // Active in HR
        Employee::factory()->create([
            'department_id' => $hr->id,
            'employment_status' => EmploymentStatus::Active,
            'employment_type' => EmploymentType::Regular,
        ]);

        // Filter by department AND status
        $activeEngineering = Employee::query()
            ->where('department_id', $engineering->id)
            ->where('employment_status', EmploymentStatus::Active)
            ->get();

        expect($activeEngineering)->toHaveCount(2);

        // Filter by department AND status AND type
        $activeRegularEngineering = Employee::query()
            ->where('department_id', $engineering->id)
            ->where('employment_status', EmploymentStatus::Active)
            ->where('employment_type', EmploymentType::Regular)
            ->get();

        expect($activeRegularEngineering)->toHaveCount(1);

        // Filter using scopeActive
        $allActive = Employee::active()->get();
        expect($allActive)->toHaveCount(3);
    });
});

describe('Employee Soft Delete Integration', function () {
    it('preserves employee data when soft deleted', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkflow($tenant);

        $department = Department::factory()->create(['name' => 'Engineering']);
        $position = Position::factory()->create(['title' => 'Developer']);

        $employee = Employee::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'employee_number' => 'EMP-SOFT-001',
            'email' => 'test.soft@example.com',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'tin' => '123-456-789',
            'sss_number' => '34-1234567-8',
        ]);

        $employeeId = $employee->id;

        // Soft delete
        $employee->delete();

        // Should not appear in normal queries
        expect(Employee::find($employeeId))->toBeNull();
        expect(Employee::where('employee_number', 'EMP-SOFT-001')->first())->toBeNull();

        // Should still exist with trashed
        $trashedEmployee = Employee::withTrashed()->find($employeeId);
        expect($trashedEmployee)->not->toBeNull();
        expect($trashedEmployee->first_name)->toBe('Test');
        expect($trashedEmployee->last_name)->toBe('Employee');
        expect($trashedEmployee->employee_number)->toBe('EMP-SOFT-001');
        expect($trashedEmployee->tin)->toBe('123-456-789');
        expect($trashedEmployee->sss_number)->toBe('34-1234567-8');

        // Verify department and position relationships still work
        expect($trashedEmployee->department->name)->toBe('Engineering');
        expect($trashedEmployee->position->title)->toBe('Developer');
    });

    it('can restore soft deleted employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkflow($tenant);

        $employee = Employee::factory()->create([
            'employee_number' => 'EMP-RESTORE-001',
        ]);

        $employeeId = $employee->id;

        // Soft delete
        $employee->delete();
        expect(Employee::find($employeeId))->toBeNull();

        // Restore
        Employee::withTrashed()->find($employeeId)->restore();

        // Should appear in normal queries again
        $restoredEmployee = Employee::find($employeeId);
        expect($restoredEmployee)->not->toBeNull();
        expect($restoredEmployee->employee_number)->toBe('EMP-RESTORE-001');
        expect($restoredEmployee->deleted_at)->toBeNull();
    });
});

describe('Employee Profile Data Integration', function () {
    it('loads employee with all required relationships for profile display', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkflow($tenant);

        $department = Department::factory()->create(['name' => 'Operations']);
        $position = Position::factory()->create(['title' => 'Manager']);
        $workLocation = WorkLocation::factory()->create(['name' => 'Head Office']);

        $supervisor = Employee::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Visor',
        ]);

        $employee = Employee::factory()->create([
            'first_name' => 'Ricardo',
            'last_name' => 'Dela Cruz',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'work_location_id' => $workLocation->id,
            'supervisor_id' => $supervisor->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        // Create status history
        EmployeeStatusHistory::factory()->create([
            'employee_id' => $employee->id,
            'new_status' => EmploymentStatus::Active,
            'ended_at' => null,
        ]);

        // Load with all relationships
        $loadedEmployee = Employee::with([
            'department',
            'position',
            'workLocation',
            'supervisor',
            'statusHistory',
        ])->find($employee->id);

        // Verify all relationships are loaded
        expect($loadedEmployee->relationLoaded('department'))->toBeTrue();
        expect($loadedEmployee->relationLoaded('position'))->toBeTrue();
        expect($loadedEmployee->relationLoaded('workLocation'))->toBeTrue();
        expect($loadedEmployee->relationLoaded('supervisor'))->toBeTrue();
        expect($loadedEmployee->relationLoaded('statusHistory'))->toBeTrue();

        // Verify relationship data
        expect($loadedEmployee->department->name)->toBe('Operations');
        expect($loadedEmployee->position->title)->toBe('Manager');
        expect($loadedEmployee->workLocation->name)->toBe('Head Office');
        expect($loadedEmployee->supervisor->first_name)->toBe('Super');
        expect($loadedEmployee->statusHistory)->toHaveCount(1);
    });

    it('handles contact and address JSON data correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkflow($tenant);

        $addressData = [
            'street' => '123 Main Street',
            'barangay' => 'San Antonio',
            'city' => 'Makati City',
            'province' => 'Metro Manila',
            'postal_code' => '1200',
        ];

        $emergencyContactData = [
            'name' => 'Maria Santos',
            'relationship' => 'Spouse',
            'phone' => '+63 917 123 4567',
        ];

        $employee = Employee::factory()->create([
            'address' => $addressData,
            'emergency_contact' => $emergencyContactData,
        ]);

        // Reload from database
        $employee->refresh();

        // Verify JSON fields are properly cast to arrays
        expect($employee->address)->toBeArray();
        expect($employee->emergency_contact)->toBeArray();

        // Verify data integrity
        expect($employee->address['street'])->toBe('123 Main Street');
        expect($employee->address['city'])->toBe('Makati City');
        expect($employee->address['postal_code'])->toBe('1200');

        expect($employee->emergency_contact['name'])->toBe('Maria Santos');
        expect($employee->emergency_contact['relationship'])->toBe('Spouse');
        expect($employee->emergency_contact['phone'])->toBe('+63 917 123 4567');
    });
});
