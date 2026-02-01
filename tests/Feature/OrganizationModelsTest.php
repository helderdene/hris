<?php

use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use App\Enums\LocationType;
use App\Models\Department;
use App\Models\DepartmentHeadHistory;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\SalaryStep;
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

describe('Department Model', function () {
    it('supports parent/child hierarchy relationships', function () {
        $parentDepartment = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        $childDepartment = Department::factory()->create([
            'name' => 'Frontend Development',
            'code' => 'FE',
            'parent_id' => $parentDepartment->id,
        ]);

        $grandchildDepartment = Department::factory()->create([
            'name' => 'React Team',
            'code' => 'REACT',
            'parent_id' => $childDepartment->id,
        ]);

        // Test parent relationship
        expect($childDepartment->parent)->toBeInstanceOf(Department::class);
        expect($childDepartment->parent->id)->toBe($parentDepartment->id);

        // Test children relationship
        expect($parentDepartment->children)->toHaveCount(1);
        expect($parentDepartment->children->first()->id)->toBe($childDepartment->id);

        // Test nested hierarchy
        expect($childDepartment->children)->toHaveCount(1);
        expect($childDepartment->children->first()->id)->toBe($grandchildDepartment->id);

        // Test root scope
        $rootDepartments = Department::root()->get();
        expect($rootDepartments)->toHaveCount(1);
        expect($rootDepartments->first()->id)->toBe($parentDepartment->id);
    });

    it('validates circular reference and prevents self-referencing', function () {
        $department = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        // Test self-reference validation
        $result = $department->validateNotCircularReference($department->id);
        expect($result)->toBeFalse();

        // Create a child department
        $childDepartment = Department::factory()->create([
            'name' => 'Frontend',
            'code' => 'FE',
            'parent_id' => $department->id,
        ]);

        // Test circular reference: parent cannot be set to its own descendant
        $result = $department->validateNotCircularReference($childDepartment->id);
        expect($result)->toBeFalse();

        // Create grandchild
        $grandchildDepartment = Department::factory()->create([
            'name' => 'React Team',
            'code' => 'REACT',
            'parent_id' => $childDepartment->id,
        ]);

        // Test deeper circular reference
        $result = $department->validateNotCircularReference($grandchildDepartment->id);
        expect($result)->toBeFalse();

        // Test valid parent assignment (unrelated department)
        $unrelatedDepartment = Department::factory()->create([
            'name' => 'HR',
            'code' => 'HR',
        ]);

        $result = $department->validateNotCircularReference($unrelatedDepartment->id);
        expect($result)->toBeTrue();

        // Test null parent is valid
        $result = $department->validateNotCircularReference(null);
        expect($result)->toBeTrue();
    });
});

describe('SalaryGrade Model', function () {
    it('validates minimum is less than or equal to midpoint and maximum', function () {
        // Valid salary grade: min <= midpoint <= max
        $validGrade = SalaryGrade::factory()->create([
            'name' => 'Grade A',
            'minimum_salary' => 50000.00,
            'midpoint_salary' => 75000.00,
            'maximum_salary' => 100000.00,
        ]);

        expect($validGrade->isValidSalaryRange())->toBeTrue();

        // Test edge case: all equal values (valid)
        $equalGrade = SalaryGrade::factory()->create([
            'name' => 'Grade B',
            'minimum_salary' => 50000.00,
            'midpoint_salary' => 50000.00,
            'maximum_salary' => 50000.00,
        ]);

        expect($equalGrade->isValidSalaryRange())->toBeTrue();

        // Invalid: minimum > midpoint
        $invalidGrade1 = SalaryGrade::factory()->make([
            'name' => 'Grade C',
            'minimum_salary' => 80000.00,
            'midpoint_salary' => 75000.00,
            'maximum_salary' => 100000.00,
        ]);

        expect($invalidGrade1->isValidSalaryRange())->toBeFalse();

        // Invalid: midpoint > maximum
        $invalidGrade2 = SalaryGrade::factory()->make([
            'name' => 'Grade D',
            'minimum_salary' => 50000.00,
            'midpoint_salary' => 110000.00,
            'maximum_salary' => 100000.00,
        ]);

        expect($invalidGrade2->isValidSalaryRange())->toBeFalse();

        // Invalid: minimum > maximum
        $invalidGrade3 = SalaryGrade::factory()->make([
            'name' => 'Grade E',
            'minimum_salary' => 150000.00,
            'midpoint_salary' => 110000.00,
            'maximum_salary' => 100000.00,
        ]);

        expect($invalidGrade3->isValidSalaryRange())->toBeFalse();
    });
});

describe('SalaryStep Model', function () {
    it('orders steps by step_number within a salary grade', function () {
        $salaryGrade = SalaryGrade::factory()->create([
            'name' => 'Grade A',
            'minimum_salary' => 50000.00,
            'midpoint_salary' => 75000.00,
            'maximum_salary' => 100000.00,
        ]);

        // Create steps out of order
        SalaryStep::factory()->create([
            'salary_grade_id' => $salaryGrade->id,
            'step_number' => 3,
            'amount' => 65000.00,
        ]);

        SalaryStep::factory()->create([
            'salary_grade_id' => $salaryGrade->id,
            'step_number' => 1,
            'amount' => 55000.00,
        ]);

        SalaryStep::factory()->create([
            'salary_grade_id' => $salaryGrade->id,
            'step_number' => 2,
            'amount' => 60000.00,
        ]);

        // Refresh to get ordered steps via relationship
        $salaryGrade->refresh();

        $steps = $salaryGrade->steps;

        expect($steps)->toHaveCount(3);
        expect($steps[0]->step_number)->toBe(1);
        expect($steps[1]->step_number)->toBe(2);
        expect($steps[2]->step_number)->toBe(3);

        // Verify amounts are in correct order
        expect($steps[0]->amount)->toBe('55000.00');
        expect($steps[1]->amount)->toBe('60000.00');
        expect($steps[2]->amount)->toBe('65000.00');
    });
});

describe('Position Model', function () {
    it('relates to a salary grade and casts enums correctly', function () {
        $salaryGrade = SalaryGrade::factory()->create([
            'name' => 'Senior Grade',
            'minimum_salary' => 80000.00,
            'midpoint_salary' => 100000.00,
            'maximum_salary' => 120000.00,
        ]);

        $position = Position::factory()->create([
            'title' => 'Senior Software Engineer',
            'code' => 'SSE',
            'description' => 'Senior development role',
            'salary_grade_id' => $salaryGrade->id,
            'job_level' => JobLevel::Senior,
            'employment_type' => EmploymentType::Regular,
            'status' => 'active',
        ]);

        // Test relationship
        expect($position->salaryGrade)->toBeInstanceOf(SalaryGrade::class);
        expect($position->salaryGrade->id)->toBe($salaryGrade->id);

        // Test enum casts
        expect($position->job_level)->toBeInstanceOf(JobLevel::class);
        expect($position->job_level)->toBe(JobLevel::Senior);
        expect($position->employment_type)->toBeInstanceOf(EmploymentType::class);
        expect($position->employment_type)->toBe(EmploymentType::Regular);

        // Test salary grade has positions
        expect($salaryGrade->positions)->toHaveCount(1);
        expect($salaryGrade->positions->first()->id)->toBe($position->id);

        // Test position without salary grade
        $positionWithoutGrade = Position::factory()->create([
            'title' => 'Intern Developer',
            'code' => 'INTD',
            'salary_grade_id' => null,
            'job_level' => JobLevel::Junior,
            'employment_type' => EmploymentType::Intern,
        ]);

        expect($positionWithoutGrade->salaryGrade)->toBeNull();
    });
});

describe('WorkLocation Model', function () {
    it('handles JSON metadata properly with array casting', function () {
        $metadata = [
            'phone' => '+1-555-123-4567',
            'email' => 'office@example.com',
            'capacity' => 150,
            'amenities' => ['parking', 'cafeteria', 'gym'],
            'manager' => [
                'name' => 'John Doe',
                'contact' => 'john@example.com',
            ],
        ];

        $location = WorkLocation::factory()->create([
            'name' => 'Main Office',
            'code' => 'MAIN',
            'address' => '123 Business Ave',
            'city' => 'Metro City',
            'region' => 'Central Region',
            'country' => 'PH',
            'postal_code' => '12345',
            'location_type' => LocationType::Headquarters,
            'timezone' => 'Asia/Manila',
            'metadata' => $metadata,
            'status' => 'active',
        ]);

        // Refresh from database
        $location->refresh();

        // Test metadata is cast to array
        expect($location->metadata)->toBeArray();
        expect($location->metadata['phone'])->toBe('+1-555-123-4567');
        expect($location->metadata['email'])->toBe('office@example.com');
        expect($location->metadata['capacity'])->toBe(150);
        expect($location->metadata['amenities'])->toBeArray();
        expect($location->metadata['amenities'])->toContain('parking');
        expect($location->metadata['amenities'])->toContain('cafeteria');
        expect($location->metadata['amenities'])->toContain('gym');

        // Test nested array
        expect($location->metadata['manager'])->toBeArray();
        expect($location->metadata['manager']['name'])->toBe('John Doe');

        // Test location type enum cast
        expect($location->location_type)->toBeInstanceOf(LocationType::class);
        expect($location->location_type)->toBe(LocationType::Headquarters);

        // Test null metadata
        $locationWithoutMetadata = WorkLocation::factory()->create([
            'name' => 'Branch Office',
            'code' => 'BRANCH1',
            'location_type' => LocationType::Branch,
            'metadata' => null,
        ]);

        expect($locationWithoutMetadata->metadata)->toBeNull();

        // Test empty metadata
        $locationWithEmptyMetadata = WorkLocation::factory()->create([
            'name' => 'Remote Hub',
            'code' => 'REMOTE1',
            'location_type' => LocationType::RemoteHub,
            'metadata' => [],
        ]);

        expect($locationWithEmptyMetadata->metadata)->toBeArray();
        expect($locationWithEmptyMetadata->metadata)->toBeEmpty();
    });
});

describe('DepartmentHeadHistory Model', function () {
    it('tracks department head changes with current scope', function () {
        $department = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        // Create historical head record (ended)
        $pastHead = DepartmentHeadHistory::factory()->create([
            'department_id' => $department->id,
            'employee_id' => 1,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subMonth(),
        ]);

        // Create current head record (not ended)
        $currentHead = DepartmentHeadHistory::factory()->create([
            'department_id' => $department->id,
            'employee_id' => 2,
            'started_at' => now()->subMonth(),
            'ended_at' => null,
        ]);

        // Test current scope only returns active heads
        $currentHeads = DepartmentHeadHistory::current()->get();
        expect($currentHeads)->toHaveCount(1);
        expect($currentHeads->first()->id)->toBe($currentHead->id);
        expect($currentHeads->first()->employee_id)->toBe(2);

        // Test department relationship
        expect($currentHead->department)->toBeInstanceOf(Department::class);
        expect($currentHead->department->id)->toBe($department->id);

        // Test department's headHistory relationship
        expect($department->headHistory)->toHaveCount(2);
    });
});
