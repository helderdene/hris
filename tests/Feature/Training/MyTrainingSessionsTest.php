<?php

use App\Enums\EnrollmentStatus;
use App\Enums\WaitlistStatus;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\TrainingWaitlist;
use App\Services\Training\EnrollmentService;
use App\Services\Training\ICalExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function bindTenantContextForMyTraining(Tenant $tenant): void
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

describe('Employee Self-Service - Browse Sessions', function () {
    it('lists available sessions for employees', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $course = Course::factory()->published()->create();
        TrainingSession::factory()
            ->count(3)
            ->forCourse($course)
            ->scheduled()
            ->upcoming()
            ->create();

        $sessions = TrainingSession::query()
            ->scheduled()
            ->upcoming()
            ->orderBy('start_date')
            ->get();

        expect($sessions)->toHaveCount(3);
    });

    it('shows session details', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $session = TrainingSession::factory()
            ->scheduled()
            ->upcoming()
            ->create();

        expect($session->status->isVisibleToEmployees())->toBeTrue()
            ->and($session->display_title)->not->toBeEmpty();
    });

    it('shows enrollment status for employee on session', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $employee = Employee::factory()->create();

        $session = TrainingSession::factory()
            ->scheduled()
            ->upcoming()
            ->create();

        // Enroll the employee
        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->confirmed()
            ->create();

        expect($session->hasEmployee($employee))->toBeTrue();
    });
});

describe('Employee Self-Service - Enrollment Actions', function () {
    it('allows employee to self-enroll', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        Notification::fake();

        $employee = Employee::factory()->create();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(10)
            ->upcoming()
            ->create();

        $service = app(EnrollmentService::class);
        $result = $service->enroll($session, $employee);

        expect($result)->toBeInstanceOf(TrainingEnrollment::class)
            ->and($result->status)->toBe(EnrollmentStatus::Confirmed);

        $this->assertDatabaseHas('training_enrollments', [
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'status' => EnrollmentStatus::Confirmed->value,
        ]);
    });

    it('allows employee to cancel their own enrollment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $employee = Employee::factory()->create();

        $session = TrainingSession::factory()
            ->scheduled()
            ->upcoming()
            ->create();

        $enrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->confirmed()
            ->create();

        $service = app(EnrollmentService::class);
        $result = $service->cancelEnrollment($enrollment, $employee, 'Cancelled by employee');

        expect($result->status)->toBe(EnrollmentStatus::Cancelled);

        $this->assertDatabaseHas('training_enrollments', [
            'id' => $enrollment->id,
            'status' => EnrollmentStatus::Cancelled->value,
        ]);
    });

    it('verifies employee ownership of enrollment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $employee = Employee::factory()->create();
        $otherEmployee = Employee::factory()->create();

        $enrollment = TrainingEnrollment::factory()
            ->forEmployee($otherEmployee)
            ->confirmed()
            ->create();

        // Verify the enrollment belongs to the other employee
        expect($enrollment->employee_id)->toBe($otherEmployee->id)
            ->and($enrollment->employee_id)->not->toBe($employee->id);
    });
});

describe('Employee Self-Service - My Enrollments', function () {
    it('shows upcoming and past enrollments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $employee = Employee::factory()->create();

        // Create upcoming enrollment
        $upcomingSession = TrainingSession::factory()
            ->scheduled()
            ->upcoming()
            ->create();
        TrainingEnrollment::factory()
            ->forSession($upcomingSession)
            ->forEmployee($employee)
            ->confirmed()
            ->create();

        // Create past enrollment
        $pastSession = TrainingSession::factory()
            ->completed()
            ->past()
            ->create();
        TrainingEnrollment::factory()
            ->forSession($pastSession)
            ->forEmployee($employee)
            ->attended()
            ->create();

        $upcomingEnrollments = $employee->trainingEnrollments()
            ->with(['session.course'])
            ->active()
            ->upcoming()
            ->get();

        $pastEnrollments = $employee->trainingEnrollments()
            ->with(['session.course'])
            ->past()
            ->get();

        expect($upcomingEnrollments)->toHaveCount(1)
            ->and($pastEnrollments)->toHaveCount(1);
    });

    it('shows waitlist entries', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $employee = Employee::factory()->create();

        $session = TrainingSession::factory()
            ->scheduled()
            ->upcoming()
            ->create();

        TrainingWaitlist::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->waiting()
            ->atPosition(1)
            ->create();

        $waitlistEntries = $employee->activeTrainingWaitlists()
            ->with(['session.course'])
            ->ordered()
            ->get();

        expect($waitlistEntries)->toHaveCount(1)
            ->and($waitlistEntries->first()->position)->toBe(1);
    });
});

describe('iCal Export', function () {
    it('generates valid iCal file for employee enrollments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $employee = Employee::factory()->create();

        $session = TrainingSession::factory()
            ->scheduled()
            ->upcoming()
            ->state([
                'start_date' => '2025-03-15',
                'end_date' => '2025-03-15',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'location' => 'Training Room A',
            ])
            ->create();

        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->confirmed()
            ->create();

        $service = app(ICalExportService::class);
        $content = $service->generateForEmployee($employee);

        expect($content)->toContain('BEGIN:VCALENDAR')
            ->and($content)->toContain('BEGIN:VEVENT')
            ->and($content)->toContain('END:VCALENDAR');
    });

    it('returns empty calendar when no enrollments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $employee = Employee::factory()->create();

        $service = app(ICalExportService::class);
        $content = $service->generateForEmployee($employee);

        expect($content)->toContain('BEGIN:VCALENDAR')
            ->and($content)->toContain('END:VCALENDAR')
            ->and($content)->not->toContain('BEGIN:VEVENT');
    });

    it('service generates proper iCal format', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $employee = Employee::factory()->create();

        $session = TrainingSession::factory()
            ->scheduled()
            ->state([
                'title' => 'Test Training Session',
                'start_date' => '2025-06-15',
                'end_date' => '2025-06-15',
                'start_time' => '10:00',
                'end_time' => '16:00',
                'location' => 'Conference Room B',
            ])
            ->create();

        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->confirmed()
            ->create();

        $service = app(ICalExportService::class);
        $ical = $service->generateForEmployee($employee);

        expect($ical)->toContain('PRODID:-//KasamaHR//Training Calendar//EN')
            ->and($ical)->toContain('VERSION:2.0')
            ->and($ical)->toContain('LOCATION:Conference Room B');
    });
});

describe('Search and Filter Sessions', function () {
    it('filters sessions by search query', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        $course1 = Course::factory()->published()->state(['title' => 'Leadership Training'])->create();
        $course2 = Course::factory()->published()->state(['title' => 'Technical Workshop'])->create();

        TrainingSession::factory()->scheduled()->forCourse($course1)->upcoming()->create();
        TrainingSession::factory()->scheduled()->forCourse($course2)->upcoming()->create();

        $search = 'Leadership';
        $sessions = TrainingSession::query()
            ->scheduled()
            ->upcoming()
            ->whereHas('course', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->get();

        expect($sessions)->toHaveCount(1);
    });
});

describe('Session Capacity and Enrollment Logic', function () {
    it('adds to waitlist when session is full', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        Notification::fake();

        $employee = Employee::factory()->create();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(1)
            ->upcoming()
            ->create();

        // Fill the session
        $otherEmployee = Employee::factory()->create();
        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($otherEmployee)
            ->confirmed()
            ->create();

        $service = app(EnrollmentService::class);
        $result = $service->enroll($session, $employee);

        expect($result)->toBeInstanceOf(TrainingWaitlist::class)
            ->and($result->status)->toBe(WaitlistStatus::Waiting);

        $this->assertDatabaseHas('training_waitlists', [
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
        ]);
    });

    it('enrolls immediately when unlimited capacity', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMyTraining($tenant);

        Notification::fake();

        $employee = Employee::factory()->create();

        $session = TrainingSession::factory()
            ->scheduled()
            ->unlimitedCapacity()
            ->upcoming()
            ->create();

        $service = app(EnrollmentService::class);
        $result = $service->enroll($session, $employee);

        expect($result)->toBeInstanceOf(TrainingEnrollment::class)
            ->and($result->status)->toBe(EnrollmentStatus::Confirmed);

        $this->assertDatabaseHas('training_enrollments', [
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
        ]);
    });
});
