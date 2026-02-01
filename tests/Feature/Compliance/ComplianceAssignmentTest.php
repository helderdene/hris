<?php

use App\Enums\ComplianceAssignmentStatus;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceCertificate;
use App\Models\ComplianceCourse;
use App\Models\ComplianceModule;
use App\Models\ComplianceProgress;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Tenant;
use App\Services\ComplianceAssignmentService;
use App\Services\ComplianceCertificateService;
use App\Services\ComplianceProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function bindTenantContextForCompliance(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Compliance Assignment Service', function () {
    it('assigns a compliance course to an employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCompliance($tenant);

        Notification::fake();

        $course = Course::factory()->published()->create(['is_compliance' => true]);
        $complianceCourse = ComplianceCourse::factory()->create(['course_id' => $course->id]);
        $employee = Employee::factory()->withUser()->create();

        $service = app(ComplianceAssignmentService::class);
        $assignment = $service->assignToEmployee($complianceCourse, $employee);

        expect($assignment)->toBeInstanceOf(ComplianceAssignment::class)
            ->and($assignment->status)->toBe(ComplianceAssignmentStatus::Pending)
            ->and($assignment->employee_id)->toBe($employee->id)
            ->and($assignment->compliance_course_id)->toBe($complianceCourse->id);

        $this->assertDatabaseHas('compliance_assignments', [
            'compliance_course_id' => $complianceCourse->id,
            'employee_id' => $employee->id,
            'status' => ComplianceAssignmentStatus::Pending->value,
        ]);
    });

    it('creates progress records for each module when assigned', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCompliance($tenant);

        Notification::fake();

        $course = Course::factory()->published()->create(['is_compliance' => true]);
        $complianceCourse = ComplianceCourse::factory()->create(['course_id' => $course->id]);

        // Create modules
        ComplianceModule::factory()->text()->order(1)->create(['compliance_course_id' => $complianceCourse->id]);
        ComplianceModule::factory()->video()->order(2)->create(['compliance_course_id' => $complianceCourse->id]);
        ComplianceModule::factory()->assessment()->order(3)->create(['compliance_course_id' => $complianceCourse->id]);

        $employee = Employee::factory()->create();

        $service = app(ComplianceAssignmentService::class);
        $assignment = $service->assignToEmployee($complianceCourse, $employee);

        expect($assignment->progress)->toHaveCount(3);
    });

    it('calculates due date based on days to complete', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCompliance($tenant);

        Notification::fake();

        $course = Course::factory()->published()->create(['is_compliance' => true]);
        $complianceCourse = ComplianceCourse::factory()->create([
            'course_id' => $course->id,
            'days_to_complete' => 30,
        ]);
        $employee = Employee::factory()->create();

        $service = app(ComplianceAssignmentService::class);
        $assignment = $service->assignToEmployee($complianceCourse, $employee);

        expect($assignment->due_date)->not->toBeNull()
            ->and((int) $assignment->assigned_date->diffInDays($assignment->due_date))->toBe(30);
    });

    it('does not create duplicate assignments for the same course', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCompliance($tenant);

        Notification::fake();

        $course = Course::factory()->published()->create(['is_compliance' => true]);
        $complianceCourse = ComplianceCourse::factory()->create(['course_id' => $course->id]);
        $employee = Employee::factory()->create();

        $service = app(ComplianceAssignmentService::class);

        // First assignment
        $assignment1 = $service->assignToEmployee($complianceCourse, $employee);

        // Try to assign again
        $assignment2 = $service->assignToEmployee($complianceCourse, $employee);

        expect($assignment1->id)->toBe($assignment2->id);
        expect(ComplianceAssignment::where('employee_id', $employee->id)
            ->where('compliance_course_id', $complianceCourse->id)
            ->count())->toBe(1);
    });
});

describe('Compliance Progress Service', function () {
    it('starts a module and updates assignment status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCompliance($tenant);

        $course = Course::factory()->published()->create(['is_compliance' => true]);
        $complianceCourse = ComplianceCourse::factory()->create(['course_id' => $course->id]);
        $module = ComplianceModule::factory()->text()->create(['compliance_course_id' => $complianceCourse->id]);
        $employee = Employee::factory()->create();

        $assignmentService = app(ComplianceAssignmentService::class);
        $assignment = $assignmentService->assignToEmployee($complianceCourse, $employee);

        $progressService = app(ComplianceProgressService::class);
        $progress = $progressService->startModule($assignment, $module);

        $assignment->refresh();

        expect($progress->isInProgress())->toBeTrue()
            ->and($progress->started_at)->not->toBeNull()
            ->and($assignment->status)->toBe(ComplianceAssignmentStatus::InProgress)
            ->and($assignment->started_at)->not->toBeNull();
    });

    it('completes a module and updates progress percentage', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCompliance($tenant);

        // Mock the certificate service to avoid view errors
        $this->mock(ComplianceCertificateService::class, function ($mock) {
            $mock->shouldReceive('issueCertificate')
                ->andReturnUsing(fn ($assignment) => ComplianceCertificate::factory()->make([
                    'compliance_assignment_id' => $assignment->id,
                ]));
        });

        $course = Course::factory()->published()->create(['is_compliance' => true]);
        $complianceCourse = ComplianceCourse::factory()->create(['course_id' => $course->id]);
        $module = ComplianceModule::factory()->text()->create([
            'compliance_course_id' => $complianceCourse->id,
            'is_required' => true,
        ]);
        $employee = Employee::factory()->create();

        $assignmentService = app(ComplianceAssignmentService::class);
        $assignment = $assignmentService->assignToEmployee($complianceCourse, $employee);

        $progressService = app(ComplianceProgressService::class);
        $progress = $progressService->startModule($assignment, $module);
        $progressService->completeModule($progress);

        $progress->refresh();

        expect($progress->isCompleted())->toBeTrue()
            ->and($progress->completed_at)->not->toBeNull()
            ->and((int) $progress->progress_percentage)->toBe(100);
    });
});

describe('Compliance Assignment Status', function () {
    it('identifies overdue assignments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCompliance($tenant);

        $course = Course::factory()->published()->create(['is_compliance' => true]);
        $complianceCourse = ComplianceCourse::factory()->create(['course_id' => $course->id]);
        $employee = Employee::factory()->create();

        $assignment = ComplianceAssignment::factory()->create([
            'compliance_course_id' => $complianceCourse->id,
            'employee_id' => $employee->id,
            'status' => ComplianceAssignmentStatus::InProgress,
            'due_date' => now()->subDays(5),
        ]);

        expect($assignment->isOverdue())->toBeTrue()
            ->and($assignment->getDaysUntilDue())->toBeLessThan(0);
    });

    it('identifies assignments due soon', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCompliance($tenant);

        $course = Course::factory()->published()->create(['is_compliance' => true]);
        $complianceCourse = ComplianceCourse::factory()->create(['course_id' => $course->id]);
        $employee = Employee::factory()->create();

        $assignment = ComplianceAssignment::factory()->create([
            'compliance_course_id' => $complianceCourse->id,
            'employee_id' => $employee->id,
            'status' => ComplianceAssignmentStatus::InProgress,
            'due_date' => now()->addDays(3),
        ]);

        expect($assignment->isDueSoon())->toBeTrue()
            ->and($assignment->isOverdue())->toBeFalse();
    });

    it('calculates completion percentage correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCompliance($tenant);

        $course = Course::factory()->published()->create(['is_compliance' => true]);
        $complianceCourse = ComplianceCourse::factory()->create(['course_id' => $course->id]);

        $module1 = ComplianceModule::factory()->text()->order(1)->create(['compliance_course_id' => $complianceCourse->id]);
        $module2 = ComplianceModule::factory()->text()->order(2)->create(['compliance_course_id' => $complianceCourse->id]);

        $employee = Employee::factory()->create();

        $assignment = ComplianceAssignment::factory()->create([
            'compliance_course_id' => $complianceCourse->id,
            'employee_id' => $employee->id,
        ]);

        // Create progress - one completed, one not started
        ComplianceProgress::factory()->completed()->create([
            'compliance_assignment_id' => $assignment->id,
            'compliance_module_id' => $module1->id,
        ]);
        ComplianceProgress::factory()->notStarted()->create([
            'compliance_assignment_id' => $assignment->id,
            'compliance_module_id' => $module2->id,
        ]);

        $assignment->refresh();

        expect((int) $assignment->getCompletionPercentage())->toBe(50);
    });
});
