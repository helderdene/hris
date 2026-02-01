<?php

use App\Enums\EnrollmentStatus;
use App\Enums\WaitlistStatus;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\TrainingWaitlist;
use App\Notifications\TrainingEnrollmentConfirmed;
use App\Notifications\TrainingWaitlistJoined;
use App\Notifications\TrainingWaitlistPromoted;
use App\Services\Training\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function bindTenantContextForEnrollment(Tenant $tenant): void
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

describe('Enrollment Service - Basic Enrollment', function () {
    it('enrolls an employee when capacity is available', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(10)
            ->create();
        $employee = Employee::factory()->withUser()->create();

        $service = app(EnrollmentService::class);
        $result = $service->enroll($session, $employee);

        expect($result)->toBeInstanceOf(TrainingEnrollment::class)
            ->and($result->status)->toBe(EnrollmentStatus::Confirmed);

        $this->assertDatabaseHas('training_enrollments', [
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'status' => EnrollmentStatus::Confirmed->value,
        ]);

        Notification::assertSentTo($employee->user, TrainingEnrollmentConfirmed::class);
    });

    it('enrolls when session has unlimited capacity', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->unlimitedCapacity()
            ->create();

        // Enroll many employees
        $employees = Employee::factory()->count(10)->create();
        $service = app(EnrollmentService::class);

        foreach ($employees as $employee) {
            $result = $service->enroll($session, $employee);
            expect($result)->toBeInstanceOf(TrainingEnrollment::class);
        }

        expect($session->fresh()->enrolled_count)->toBe(10);
    });

    it('records enrolled_at timestamp', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        Notification::fake();

        $session = TrainingSession::factory()->scheduled()->create();
        $employee = Employee::factory()->create();

        $service = app(EnrollmentService::class);
        $enrollment = $service->enroll($session, $employee);

        expect($enrollment->enrolled_at)->not->toBeNull();
    });

    it('records enrolled_by when provided', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        Notification::fake();

        $session = TrainingSession::factory()->scheduled()->create();
        $employee = Employee::factory()->create();
        $enrolledBy = Employee::factory()->create();

        $service = app(EnrollmentService::class);
        $enrollment = $service->enroll($session, $employee, $enrolledBy);

        expect($enrollment->enrolled_by)->toBe($enrolledBy->id);
    });
});

describe('Enrollment Service - Waitlist', function () {
    it('adds employee to waitlist when session is full', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(1)
            ->create();

        // Fill the session
        $employee1 = Employee::factory()->create();
        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee1)
            ->confirmed()
            ->create();

        // Try to enroll second employee (with user for notification)
        $employee2 = Employee::factory()->withUser()->create();
        $service = app(EnrollmentService::class);
        $result = $service->enroll($session, $employee2);

        expect($result)->toBeInstanceOf(TrainingWaitlist::class)
            ->and($result->status)->toBe(WaitlistStatus::Waiting);

        $this->assertDatabaseHas('training_waitlists', [
            'training_session_id' => $session->id,
            'employee_id' => $employee2->id,
            'status' => WaitlistStatus::Waiting->value,
            'position' => 1,
        ]);

        Notification::assertSentTo($employee2->user, TrainingWaitlistJoined::class);
    });

    it('assigns sequential positions on waitlist', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(1)
            ->create();

        // Fill the session
        TrainingEnrollment::factory()
            ->forSession($session)
            ->confirmed()
            ->create();

        $service = app(EnrollmentService::class);
        $employees = Employee::factory()->count(3)->create();

        foreach ($employees as $employee) {
            $service->enroll($session, $employee);
        }

        foreach ($employees as $index => $employee) {
            $this->assertDatabaseHas('training_waitlists', [
                'employee_id' => $employee->id,
                'position' => $index + 1,
            ]);
        }
    });
});

describe('Enrollment Service - Validation', function () {
    it('prevents duplicate enrollments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(10)
            ->create();
        $employee = Employee::factory()->create();

        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->confirmed()
            ->create();

        $service = app(EnrollmentService::class);

        expect(fn () => $service->enroll($session, $employee))
            ->toThrow(ValidationException::class);
    });

    it('prevents enrollment in draft sessions', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $session = TrainingSession::factory()->draft()->create();
        $employee = Employee::factory()->create();

        $service = app(EnrollmentService::class);

        expect(fn () => $service->enroll($session, $employee))
            ->toThrow(ValidationException::class);
    });

    it('prevents enrollment in cancelled sessions', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $session = TrainingSession::factory()->cancelled()->create();
        $employee = Employee::factory()->create();

        $service = app(EnrollmentService::class);

        expect(fn () => $service->enroll($session, $employee))
            ->toThrow(ValidationException::class);
    });

    it('prevents duplicate waitlist entries', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(1)
            ->create();

        // Fill the session
        TrainingEnrollment::factory()
            ->forSession($session)
            ->confirmed()
            ->create();

        $employee = Employee::factory()->create();

        // Add to waitlist
        TrainingWaitlist::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->waiting()
            ->create();

        $service = app(EnrollmentService::class);

        expect(fn () => $service->enroll($session, $employee))
            ->toThrow(ValidationException::class);
    });
});

describe('Enrollment Service - Cancellation', function () {
    it('cancels an enrollment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $enrollment = TrainingEnrollment::factory()->confirmed()->create();

        $service = app(EnrollmentService::class);
        $result = $service->cancelEnrollment($enrollment);

        expect($result->status)->toBe(EnrollmentStatus::Cancelled);

        $this->assertDatabaseHas('training_enrollments', [
            'id' => $enrollment->id,
            'status' => EnrollmentStatus::Cancelled->value,
        ]);
    });

    it('records cancellation details', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $enrollment = TrainingEnrollment::factory()->confirmed()->create();
        $cancelledBy = Employee::factory()->create();

        $service = app(EnrollmentService::class);
        $result = $service->cancelEnrollment($enrollment, $cancelledBy, 'Schedule conflict');

        expect($result->cancelled_by)->toBe($cancelledBy->id)
            ->and($result->cancellation_reason)->toBe('Schedule conflict')
            ->and($result->cancelled_at)->not->toBeNull();
    });

    it('promotes from waitlist when enrollment is cancelled', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(1)
            ->create();

        // Create enrollment
        $employee1 = Employee::factory()->create();
        $enrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee1)
            ->confirmed()
            ->create();

        // Add to waitlist (with user for notification)
        $employee2 = Employee::factory()->withUser()->create();
        TrainingWaitlist::factory()
            ->forSession($session)
            ->forEmployee($employee2)
            ->waiting()
            ->atPosition(1)
            ->create();

        $service = app(EnrollmentService::class);
        $service->cancelEnrollment($enrollment);

        // Original enrollment should be cancelled
        $this->assertDatabaseHas('training_enrollments', [
            'id' => $enrollment->id,
            'status' => EnrollmentStatus::Cancelled->value,
        ]);

        // Waitlisted employee should be enrolled
        $this->assertDatabaseHas('training_enrollments', [
            'training_session_id' => $session->id,
            'employee_id' => $employee2->id,
            'status' => EnrollmentStatus::Confirmed->value,
        ]);

        // Waitlist entry should be promoted
        $this->assertDatabaseHas('training_waitlists', [
            'training_session_id' => $session->id,
            'employee_id' => $employee2->id,
            'status' => WaitlistStatus::Promoted->value,
        ]);

        Notification::assertSentTo($employee2->user, TrainingWaitlistPromoted::class);
    });
});

describe('Enrollment Service - Attendance', function () {
    it('marks enrollment as attended', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $enrollment = TrainingEnrollment::factory()->confirmed()->create();

        $service = app(EnrollmentService::class);
        $result = $service->markAsAttended($enrollment);

        expect($result->status)->toBe(EnrollmentStatus::Attended)
            ->and($result->attended_at)->not->toBeNull();
    });

    it('marks enrollment as no-show', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $enrollment = TrainingEnrollment::factory()->confirmed()->create();

        $service = app(EnrollmentService::class);
        $result = $service->markAsNoShow($enrollment);

        expect($result->status)->toBe(EnrollmentStatus::NoShow);
    });
});

describe('Enrollment Model', function () {
    it('has session relationship', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $session = TrainingSession::factory()->create();
        $enrollment = TrainingEnrollment::factory()->forSession($session)->create();

        expect($enrollment->session->id)->toBe($session->id);
    });

    it('has employee relationship', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $employee = Employee::factory()->create();
        $enrollment = TrainingEnrollment::factory()->forEmployee($employee)->create();

        expect($enrollment->employee->id)->toBe($employee->id);
    });

    it('returns correct status label from enum', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollment($tenant);

        $confirmed = TrainingEnrollment::factory()->confirmed()->create();
        $attended = TrainingEnrollment::factory()->attended()->create();
        $noShow = TrainingEnrollment::factory()->noShow()->create();
        $cancelled = TrainingEnrollment::factory()->cancelled()->create();

        expect($confirmed->status->label())->toBe('Confirmed')
            ->and($attended->status->label())->toBe('Attended')
            ->and($noShow->status->label())->toBe('No Show')
            ->and($cancelled->status->label())->toBe('Cancelled');
    });
});
