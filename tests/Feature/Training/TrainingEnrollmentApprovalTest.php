<?php

use App\Enums\EnrollmentStatus;
use App\Enums\WaitlistStatus;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Notifications\TrainingEnrollmentApproved;
use App\Notifications\TrainingEnrollmentRejected;
use App\Notifications\TrainingEnrollmentRequestSubmitted;
use App\Services\Training\TrainingEnrollmentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function bindTenantContextForEnrollmentApproval(Tenant $tenant): void
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

describe('Enrollment Request Service - Submit Request', function () {
    it('submits an enrollment request for approval', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(10)
            ->create();

        $supervisor = Employee::factory()->withUser()->active()->create();
        $employee = Employee::factory()->withUser()->active()
            ->create(['supervisor_id' => $supervisor->id]);

        $service = app(TrainingEnrollmentRequestService::class);
        $enrollment = $service->submit($session, $employee, 'Career development');

        expect($enrollment)->toBeInstanceOf(TrainingEnrollment::class)
            ->and($enrollment->status)->toBe(EnrollmentStatus::Pending)
            ->and($enrollment->reference_number)->not->toBeNull()
            ->and($enrollment->submitted_at)->not->toBeNull()
            ->and($enrollment->request_reason)->toBe('Career development')
            ->and($enrollment->approver_employee_id)->toBe($supervisor->id)
            ->and($enrollment->approver_name)->toBe($supervisor->full_name);

        $this->assertDatabaseHas('training_enrollments', [
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'status' => EnrollmentStatus::Pending->value,
            'approver_employee_id' => $supervisor->id,
        ]);

        Notification::assertSentTo($supervisor->user, TrainingEnrollmentRequestSubmitted::class);
    });

    it('generates unique reference number', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        Notification::fake();

        $session = TrainingSession::factory()->scheduled()->create();
        $supervisor = Employee::factory()->active()->create();

        $employees = Employee::factory()->count(3)->active()
            ->create(['supervisor_id' => $supervisor->id]);

        $service = app(TrainingEnrollmentRequestService::class);
        $references = [];

        foreach ($employees as $employee) {
            $enrollment = $service->submit($session, $employee);
            $references[] = $enrollment->reference_number;
        }

        expect(array_unique($references))->toHaveCount(3);
    });

    it('fails when no approver is found', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        $session = TrainingSession::factory()->scheduled()->create();
        $employee = Employee::factory()->active()->create(['supervisor_id' => null]);

        $service = app(TrainingEnrollmentRequestService::class);

        expect(fn () => $service->submit($session, $employee))
            ->toThrow(ValidationException::class, 'No approver found');
    });

    it('prevents duplicate pending requests', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        Notification::fake();

        $session = TrainingSession::factory()->scheduled()->create();
        $supervisor = Employee::factory()->active()->create();
        $employee = Employee::factory()->active()
            ->create(['supervisor_id' => $supervisor->id]);

        $service = app(TrainingEnrollmentRequestService::class);
        $service->submit($session, $employee);

        expect(fn () => $service->submit($session, $employee))
            ->toThrow(ValidationException::class);
    });

    it('prevents request when already enrolled', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        $session = TrainingSession::factory()->scheduled()->create();
        $supervisor = Employee::factory()->active()->create();
        $employee = Employee::factory()->active()
            ->create(['supervisor_id' => $supervisor->id]);

        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->confirmed()
            ->create();

        $service = app(TrainingEnrollmentRequestService::class);

        expect(fn () => $service->submit($session, $employee))
            ->toThrow(ValidationException::class, 'already enrolled');
    });

    it('prevents request for non-scheduled sessions', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        $session = TrainingSession::factory()->draft()->create();
        $supervisor = Employee::factory()->active()->create();
        $employee = Employee::factory()->active()
            ->create(['supervisor_id' => $supervisor->id]);

        $service = app(TrainingEnrollmentRequestService::class);

        expect(fn () => $service->submit($session, $employee))
            ->toThrow(ValidationException::class, 'not open for enrollment');
    });
});

describe('Enrollment Request Service - Approve Request', function () {
    it('approves an enrollment request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(10)
            ->create();

        $supervisor = Employee::factory()->withUser()->active()->create();
        $employee = Employee::factory()->withUser()->active()
            ->create(['supervisor_id' => $supervisor->id]);

        $service = app(TrainingEnrollmentRequestService::class);
        $enrollment = $service->submit($session, $employee);

        Notification::fake();

        $result = $service->approve($enrollment, $supervisor, 'Good choice!');

        expect($result->status)->toBe(EnrollmentStatus::Confirmed)
            ->and($result->enrolled_at)->not->toBeNull()
            ->and($result->approved_at)->not->toBeNull()
            ->and($result->approver_remarks)->toBe('Good choice!');

        Notification::assertSentTo($employee->user, TrainingEnrollmentApproved::class);
    });

    it('adds to waitlist when session is full after approval', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

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

        $supervisor = Employee::factory()->withUser()->active()->create();
        $employee = Employee::factory()->withUser()->active()
            ->create(['supervisor_id' => $supervisor->id]);

        $service = app(TrainingEnrollmentRequestService::class);

        // Submit request when session was not full
        $enrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->create([
                'status' => EnrollmentStatus::Pending,
                'reference_number' => TrainingEnrollment::generateReferenceNumber(),
                'submitted_at' => now(),
                'approver_employee_id' => $supervisor->id,
                'approver_name' => $supervisor->full_name,
            ]);

        Notification::fake();

        $result = $service->approve($enrollment, $supervisor);

        expect($result->status)->toBe(EnrollmentStatus::Cancelled);

        $this->assertDatabaseHas('training_waitlists', [
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'status' => WaitlistStatus::Waiting->value,
        ]);

        Notification::assertSentTo($employee->user, TrainingEnrollmentApproved::class);
    });

    it('fails when approver is not authorized', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        Notification::fake();

        $session = TrainingSession::factory()->scheduled()->create();
        $supervisor = Employee::factory()->active()->create();
        $employee = Employee::factory()->active()
            ->create(['supervisor_id' => $supervisor->id]);
        $otherEmployee = Employee::factory()->active()->create();

        $service = app(TrainingEnrollmentRequestService::class);
        $enrollment = $service->submit($session, $employee);

        expect(fn () => $service->approve($enrollment, $otherEmployee))
            ->toThrow(ValidationException::class, 'not authorized');
    });
});

describe('Enrollment Request Service - Reject Request', function () {
    it('rejects an enrollment request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        Notification::fake();

        $session = TrainingSession::factory()->scheduled()->create();
        $supervisor = Employee::factory()->withUser()->active()->create();
        $employee = Employee::factory()->withUser()->active()
            ->create(['supervisor_id' => $supervisor->id]);

        $service = app(TrainingEnrollmentRequestService::class);
        $enrollment = $service->submit($session, $employee);

        Notification::fake();

        $result = $service->reject($enrollment, $supervisor, 'Workload too high');

        expect($result->status)->toBe(EnrollmentStatus::Rejected)
            ->and($result->rejected_at)->not->toBeNull()
            ->and($result->rejection_reason)->toBe('Workload too high');

        Notification::assertSentTo($employee->user, TrainingEnrollmentRejected::class);
    });

    it('fails when approver is not authorized', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        Notification::fake();

        $session = TrainingSession::factory()->scheduled()->create();
        $supervisor = Employee::factory()->active()->create();
        $employee = Employee::factory()->active()
            ->create(['supervisor_id' => $supervisor->id]);
        $otherEmployee = Employee::factory()->active()->create();

        $service = app(TrainingEnrollmentRequestService::class);
        $enrollment = $service->submit($session, $employee);

        expect(fn () => $service->reject($enrollment, $otherEmployee, 'No reason'))
            ->toThrow(ValidationException::class, 'not authorized');
    });
});

describe('Enrollment Request Service - Cancel Request', function () {
    it('allows employee to cancel own pending request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        Notification::fake();

        $session = TrainingSession::factory()->scheduled()->create();
        $supervisor = Employee::factory()->active()->create();
        $employee = Employee::factory()->active()
            ->create(['supervisor_id' => $supervisor->id]);

        $service = app(TrainingEnrollmentRequestService::class);
        $enrollment = $service->submit($session, $employee);

        $result = $service->cancel($enrollment, $employee, 'Changed my mind');

        expect($result->status)->toBe(EnrollmentStatus::Cancelled)
            ->and($result->cancelled_at)->not->toBeNull()
            ->and($result->cancellation_reason)->toBe('Changed my mind');
    });

    it('prevents other employees from cancelling', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        Notification::fake();

        $session = TrainingSession::factory()->scheduled()->create();
        $supervisor = Employee::factory()->active()->create();
        $employee = Employee::factory()->active()
            ->create(['supervisor_id' => $supervisor->id]);
        $otherEmployee = Employee::factory()->active()->create();

        $service = app(TrainingEnrollmentRequestService::class);
        $enrollment = $service->submit($session, $employee);

        expect(fn () => $service->cancel($enrollment, $otherEmployee))
            ->toThrow(ValidationException::class, 'only cancel your own');
    });
});

describe('EnrollmentStatus Enum', function () {
    it('correctly identifies pending status', function () {
        expect(EnrollmentStatus::Pending->isPending())->toBeTrue()
            ->and(EnrollmentStatus::Confirmed->isPending())->toBeFalse();
    });

    it('correctly identifies final statuses', function () {
        expect(EnrollmentStatus::Pending->isFinal())->toBeFalse()
            ->and(EnrollmentStatus::Confirmed->isFinal())->toBeFalse()
            ->and(EnrollmentStatus::Attended->isFinal())->toBeTrue()
            ->and(EnrollmentStatus::Rejected->isFinal())->toBeTrue()
            ->and(EnrollmentStatus::Cancelled->isFinal())->toBeTrue();
    });

    it('correctly identifies approvable statuses', function () {
        expect(EnrollmentStatus::Pending->canBeApproved())->toBeTrue()
            ->and(EnrollmentStatus::Confirmed->canBeApproved())->toBeFalse()
            ->and(EnrollmentStatus::Rejected->canBeApproved())->toBeFalse();
    });

    it('correctly identifies cancellable statuses', function () {
        expect(EnrollmentStatus::Pending->canBeCancelled())->toBeTrue()
            ->and(EnrollmentStatus::Confirmed->canBeCancelled())->toBeTrue()
            ->and(EnrollmentStatus::Attended->canBeCancelled())->toBeFalse()
            ->and(EnrollmentStatus::Rejected->canBeCancelled())->toBeFalse();
    });
});

describe('Training Enrollment Model - Approval Fields', function () {
    it('has approver relationship', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        $supervisor = Employee::factory()->create();
        $enrollment = TrainingEnrollment::factory()->create([
            'approver_employee_id' => $supervisor->id,
        ]);

        expect($enrollment->approver->id)->toBe($supervisor->id);
    });

    it('generates unique reference numbers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        $reference1 = TrainingEnrollment::generateReferenceNumber();
        $reference2 = TrainingEnrollment::generateReferenceNumber();

        expect($reference1)->not->toBe($reference2)
            ->and($reference1)->toStartWith('TRN-');
    });

    it('scopes pending for approver', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEnrollmentApproval($tenant);

        $supervisor = Employee::factory()->create();
        $otherSupervisor = Employee::factory()->create();

        TrainingEnrollment::factory()->count(2)->create([
            'status' => EnrollmentStatus::Pending,
            'approver_employee_id' => $supervisor->id,
        ]);

        TrainingEnrollment::factory()->create([
            'status' => EnrollmentStatus::Pending,
            'approver_employee_id' => $otherSupervisor->id,
        ]);

        TrainingEnrollment::factory()->create([
            'status' => EnrollmentStatus::Confirmed,
            'approver_employee_id' => $supervisor->id,
        ]);

        $pending = TrainingEnrollment::pendingForApprover($supervisor)->get();

        expect($pending)->toHaveCount(2);
    });
});
