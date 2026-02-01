<?php

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use App\Models\Position;
use App\Models\TenantModel;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('EmploymentType Enum ProjectBased case', function () {
    it('has ProjectBased case in EmploymentType enum', function () {
        expect(EmploymentType::ProjectBased->value)->toBe('project_based');
        expect(EmploymentType::ProjectBased->label())->toBe('Project-based');
        expect(EmploymentType::isValid('project_based'))->toBeTrue();
    });
});

describe('Employee Model', function () {
    it('extends TenantModel for multi-tenant database isolation', function () {
        $employee = new Employee;

        expect($employee)->toBeInstanceOf(TenantModel::class);
    });

    it('has relationships with department, position, workLocation, and supervisor', function () {
        $department = Department::factory()->create(['name' => 'Engineering']);
        $position = Position::factory()->create(['title' => 'Software Engineer']);
        $workLocation = WorkLocation::factory()->create(['name' => 'Main Office']);

        $supervisor = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Manager',
            'department_id' => $department->id,
        ]);

        $employee = Employee::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Developer',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'work_location_id' => $workLocation->id,
            'supervisor_id' => $supervisor->id,
        ]);

        // Test department relationship
        expect($employee->department)->toBeInstanceOf(Department::class);
        expect($employee->department->id)->toBe($department->id);

        // Test position relationship
        expect($employee->position)->toBeInstanceOf(Position::class);
        expect($employee->position->id)->toBe($position->id);

        // Test workLocation relationship
        expect($employee->workLocation)->toBeInstanceOf(WorkLocation::class);
        expect($employee->workLocation->id)->toBe($workLocation->id);

        // Test supervisor relationship
        expect($employee->supervisor)->toBeInstanceOf(Employee::class);
        expect($employee->supervisor->id)->toBe($supervisor->id);

        // Test subordinates relationship
        expect($supervisor->subordinates)->toHaveCount(1);
        expect($supervisor->subordinates->first()->id)->toBe($employee->id);
    });

    it('filters active employees using scopeActive', function () {
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Resigned,
        ]);
        Employee::factory()->create([
            'employment_status' => EmploymentStatus::Terminated,
        ]);

        $activeEmployees = Employee::active()->get();

        expect($activeEmployees)->toHaveCount(2);
        $activeEmployees->each(function ($employee) {
            expect($employee->employment_status)->toBe(EmploymentStatus::Active);
        });
    });

    it('computes full_name accessor correctly', function () {
        $employee = Employee::factory()->create([
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Cruz',
            'suffix' => 'Jr.',
        ]);

        expect($employee->full_name)->toBe('Maria Santos Cruz Jr.');

        // Test without middle name and suffix
        $employeeSimple = Employee::factory()->create([
            'first_name' => 'Juan',
            'middle_name' => null,
            'last_name' => 'Dela Cruz',
            'suffix' => null,
        ]);

        expect($employeeSimple->full_name)->toBe('Juan Dela Cruz');
    });

    it('computes age accessor from date_of_birth', function () {
        $employee = Employee::factory()->create([
            'date_of_birth' => now()->subYears(30)->subMonths(6),
        ]);

        expect($employee->age)->toBe(30);

        // Test null date of birth
        $employeeNoDob = Employee::factory()->create([
            'date_of_birth' => null,
        ]);

        expect($employeeNoDob->age)->toBeNull();
    });

    it('computes years_of_service accessor from hire_date', function () {
        $employee = Employee::factory()->create([
            'hire_date' => now()->subYears(5)->subMonths(3),
        ]);

        expect($employee->years_of_service)->toBe(5);

        // Test recent hire
        $recentHire = Employee::factory()->create([
            'hire_date' => now()->subMonths(6),
        ]);

        expect($recentHire->years_of_service)->toBe(0);
    });

    it('computes initials accessor from first and last name', function () {
        $employee = Employee::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Cruz',
        ]);

        expect($employee->initials)->toBe('MC');
    });
});

describe('EmployeeStatusHistory Model', function () {
    it('returns current status record using scopeCurrent', function () {
        $employee = Employee::factory()->create();

        // Create past status record (ended)
        EmployeeStatusHistory::factory()->create([
            'employee_id' => $employee->id,
            'previous_status' => null,
            'new_status' => EmploymentStatus::Active,
            'effective_date' => now()->subYear(),
            'ended_at' => now()->subMonth(),
        ]);

        // Create current status record (not ended)
        $currentHistory = EmployeeStatusHistory::factory()->create([
            'employee_id' => $employee->id,
            'previous_status' => EmploymentStatus::Active,
            'new_status' => EmploymentStatus::Resigned,
            'effective_date' => now()->subMonth(),
            'ended_at' => null,
        ]);

        $currentRecords = EmployeeStatusHistory::current()->get();

        expect($currentRecords)->toHaveCount(1);
        expect($currentRecords->first()->id)->toBe($currentHistory->id);
    });

    it('has employee relationship', function () {
        $employee = Employee::factory()->create();

        $history = EmployeeStatusHistory::factory()->create([
            'employee_id' => $employee->id,
            'new_status' => EmploymentStatus::Active,
        ]);

        expect($history->employee)->toBeInstanceOf(Employee::class);
        expect($history->employee->id)->toBe($employee->id);
    });
});
