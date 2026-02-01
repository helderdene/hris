<?php

use App\Enums\AssignmentType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignmentHistory;
use App\Models\Position;
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

describe('EmployeeAssignmentHistory Model', function () {
    it('can be created with all required fields', function () {
        $employee = Employee::factory()->create();
        $department = Department::factory()->create();

        $history = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'previous_value_id' => null,
            'new_value_id' => $department->id,
            'effective_date' => now()->toDateString(),
            'remarks' => 'Initial department assignment',
        ]);

        expect($history)->toBeInstanceOf(EmployeeAssignmentHistory::class);
        expect($history->employee_id)->toBe($employee->id);
        expect($history->assignment_type)->toBe(AssignmentType::Department);
        expect($history->new_value_id)->toBe($department->id);
        expect($history->effective_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
        expect($history->remarks)->toBe('Initial department assignment');
    });

    it('returns only current records using scopeCurrent', function () {
        $employee = Employee::factory()->create();
        $position1 = Position::factory()->create();
        $position2 = Position::factory()->create();

        // Create past assignment record (ended)
        EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Position,
            'previous_value_id' => null,
            'new_value_id' => $position1->id,
            'effective_date' => now()->subYear(),
            'ended_at' => now()->subMonth(),
        ]);

        // Create current assignment record (not ended)
        $currentHistory = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Position,
            'previous_value_id' => $position1->id,
            'new_value_id' => $position2->id,
            'effective_date' => now()->subMonth(),
            'ended_at' => null,
        ]);

        $currentRecords = EmployeeAssignmentHistory::current()->get();

        expect($currentRecords)->toHaveCount(1);
        expect($currentRecords->first()->id)->toBe($currentHistory->id);
    });

    it('filters by assignment type using scopeForType', function () {
        $employee = Employee::factory()->create();
        $department = Department::factory()->create();
        $position = Position::factory()->create();

        // Create department assignment
        $deptHistory = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'new_value_id' => $department->id,
        ]);

        // Create position assignment
        $positionHistory = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Position,
            'new_value_id' => $position->id,
        ]);

        $departmentRecords = EmployeeAssignmentHistory::forType(AssignmentType::Department)->get();
        $positionRecords = EmployeeAssignmentHistory::forType(AssignmentType::Position)->get();

        expect($departmentRecords)->toHaveCount(1);
        expect($departmentRecords->first()->id)->toBe($deptHistory->id);

        expect($positionRecords)->toHaveCount(1);
        expect($positionRecords->first()->id)->toBe($positionHistory->id);
    });

    it('has employee relationship', function () {
        $employee = Employee::factory()->create();
        $position = Position::factory()->create();

        $history = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Position,
            'new_value_id' => $position->id,
        ]);

        expect($history->employee)->toBeInstanceOf(Employee::class);
        expect($history->employee->id)->toBe($employee->id);
    });

    it('casts assignment_type to AssignmentType enum', function () {
        $employee = Employee::factory()->create();
        $department = Department::factory()->create();

        $history = EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'new_value_id' => $department->id,
        ]);

        // Refresh from database to test cast
        $history->refresh();

        expect($history->assignment_type)->toBeInstanceOf(AssignmentType::class);
        expect($history->assignment_type)->toBe(AssignmentType::Department);
    });

    it('allows access to assignment history from employee model', function () {
        $employee = Employee::factory()->create();
        $department = Department::factory()->create();
        $position = Position::factory()->create();

        // Create multiple assignment history records
        EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Department,
            'new_value_id' => $department->id,
            'ended_at' => null,
        ]);

        EmployeeAssignmentHistory::factory()->create([
            'employee_id' => $employee->id,
            'assignment_type' => AssignmentType::Position,
            'new_value_id' => $position->id,
            'ended_at' => null,
        ]);

        expect($employee->assignmentHistory)->toHaveCount(2);
        expect($employee->currentAssignments())->toHaveCount(2);
    });
});

describe('AssignmentType Enum', function () {
    it('has all required cases', function () {
        expect(AssignmentType::Position->value)->toBe('position');
        expect(AssignmentType::Department->value)->toBe('department');
        expect(AssignmentType::Location->value)->toBe('location');
        expect(AssignmentType::Supervisor->value)->toBe('supervisor');
    });

    it('provides human-readable labels', function () {
        expect(AssignmentType::Position->label())->toBe('Position');
        expect(AssignmentType::Department->label())->toBe('Department');
        expect(AssignmentType::Location->label())->toBe('Work Location');
        expect(AssignmentType::Supervisor->label())->toBe('Supervisor');
    });
});
