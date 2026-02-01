<?php

use App\Enums\EnrollmentStatus;
use App\Enums\TenantUserRole;
use App\Enums\WaitlistStatus;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\TrainingWaitlist;
use App\Models\User;
use App\Services\Training\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function bindTenantContextForWaitlist(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForWaitlist(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Waitlist Position Ordering', function () {
    it('assigns correct positions in FIFO order', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(1)
            ->create();

        // Fill the session
        $enrolled = Employee::factory()->create();
        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($enrolled)
            ->confirmed()
            ->create();

        $service = app(EnrollmentService::class);

        // Add multiple employees to waitlist
        $waitlistEmployees = Employee::factory()->count(3)->create();

        foreach ($waitlistEmployees as $employee) {
            $service->enroll($session, $employee);
        }

        // Verify positions are sequential
        foreach ($waitlistEmployees as $index => $employee) {
            $this->assertDatabaseHas('training_waitlists', [
                'training_session_id' => $session->id,
                'employee_id' => $employee->id,
                'position' => $index + 1,
            ]);
        }
    });

    it('promotes first in queue (FIFO)', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(1)
            ->create();

        // Fill the session
        $enrolled = Employee::factory()->create();
        $enrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($enrolled)
            ->confirmed()
            ->create();

        // Add employees to waitlist
        $firstInQueue = Employee::factory()->create();
        $secondInQueue = Employee::factory()->create();

        TrainingWaitlist::factory()
            ->forSession($session)
            ->forEmployee($firstInQueue)
            ->waiting()
            ->atPosition(1)
            ->create();

        TrainingWaitlist::factory()
            ->forSession($session)
            ->forEmployee($secondInQueue)
            ->waiting()
            ->atPosition(2)
            ->create();

        // Cancel original enrollment to trigger promotion
        $service = app(EnrollmentService::class);
        $service->cancelEnrollment($enrollment);

        // First in queue should be promoted
        $this->assertDatabaseHas('training_waitlists', [
            'training_session_id' => $session->id,
            'employee_id' => $firstInQueue->id,
            'status' => WaitlistStatus::Promoted->value,
        ]);

        // Second in queue should still be waiting
        $this->assertDatabaseHas('training_waitlists', [
            'training_session_id' => $session->id,
            'employee_id' => $secondInQueue->id,
            'status' => WaitlistStatus::Waiting->value,
        ]);

        // First should now be enrolled
        $this->assertDatabaseHas('training_enrollments', [
            'training_session_id' => $session->id,
            'employee_id' => $firstInQueue->id,
        ]);
    });
});

describe('Waitlist Query', function () {
    it('returns waitlist for a session ordered by position', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        $session = TrainingSession::factory()->scheduled()->create();
        TrainingWaitlist::factory()
            ->count(3)
            ->forSession($session)
            ->waiting()
            ->sequence(
                ['position' => 1],
                ['position' => 2],
                ['position' => 3],
            )
            ->create();

        $waitlist = $session->waitlist()->ordered()->get();

        expect($waitlist)->toHaveCount(3);

        // Verify ordering by position
        expect($waitlist[0]->position)->toBe(1)
            ->and($waitlist[1]->position)->toBe(2)
            ->and($waitlist[2]->position)->toBe(3);
    });

    it('cancels a waitlist entry', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        $waitlist = TrainingWaitlist::factory()->waiting()->create();

        $waitlist->cancel();

        $this->assertDatabaseHas('training_waitlists', [
            'id' => $waitlist->id,
            'status' => WaitlistStatus::Cancelled->value,
        ]);
    });
});

describe('Waitlist Edge Cases', function () {
    it('handles concurrent promotions correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(2)
            ->create();

        // Fill both slots
        $enrollments = [];
        for ($i = 0; $i < 2; $i++) {
            $employee = Employee::factory()->create();
            $enrollments[] = TrainingEnrollment::factory()
                ->forSession($session)
                ->forEmployee($employee)
                ->confirmed()
                ->create();
        }

        // Add 3 employees to waitlist
        $waitlistEmployees = Employee::factory()->count(3)->create();
        foreach ($waitlistEmployees as $index => $employee) {
            TrainingWaitlist::factory()
                ->forSession($session)
                ->forEmployee($employee)
                ->waiting()
                ->atPosition($index + 1)
                ->create();
        }

        $service = app(EnrollmentService::class);

        // Cancel both enrollments - should promote first two from waitlist
        $service->cancelEnrollment($enrollments[0]);
        $service->cancelEnrollment($enrollments[1]);

        // First two waitlist should be promoted
        expect(TrainingWaitlist::where('training_session_id', $session->id)
            ->where('status', WaitlistStatus::Promoted->value)
            ->count())->toBe(2);

        // Third should still be waiting
        $this->assertDatabaseHas('training_waitlists', [
            'employee_id' => $waitlistEmployees[2]->id,
            'status' => WaitlistStatus::Waiting->value,
        ]);
    });

    it('does not promote when no waitlist entries exist', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(5)
            ->create();

        $employee = Employee::factory()->create();
        $enrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->confirmed()
            ->create();

        $service = app(EnrollmentService::class);
        $result = $service->cancelEnrollment($enrollment);

        expect($result)->toBeInstanceOf(TrainingEnrollment::class)
            ->and($result->status)->toBe(EnrollmentStatus::Cancelled);

        // No new enrollments should be created (only the cancelled one exists)
        expect(TrainingEnrollment::where('training_session_id', $session->id)->count())->toBe(1);
    });

    it('skips cancelled waitlist entries during promotion', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        Notification::fake();

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(1)
            ->create();

        $enrolled = Employee::factory()->create();
        $enrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($enrolled)
            ->confirmed()
            ->create();

        // First waitlist entry is cancelled
        $cancelledWaitlist = Employee::factory()->create();
        TrainingWaitlist::factory()
            ->forSession($session)
            ->forEmployee($cancelledWaitlist)
            ->cancelled()
            ->atPosition(1)
            ->create();

        // Second waitlist entry is waiting
        $waitingEmployee = Employee::factory()->create();
        TrainingWaitlist::factory()
            ->forSession($session)
            ->forEmployee($waitingEmployee)
            ->waiting()
            ->atPosition(2)
            ->create();

        $service = app(EnrollmentService::class);
        $service->cancelEnrollment($enrollment);

        // The waiting employee should be promoted, not the cancelled one
        $this->assertDatabaseHas('training_enrollments', [
            'training_session_id' => $session->id,
            'employee_id' => $waitingEmployee->id,
        ]);

        $this->assertDatabaseMissing('training_enrollments', [
            'training_session_id' => $session->id,
            'employee_id' => $cancelledWaitlist->id,
        ]);
    });
});

describe('Waitlist Model Tests', function () {
    it('calculates next position correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        $session = TrainingSession::factory()->scheduled()->create();

        // No waitlist entries yet
        expect(TrainingWaitlist::getNextPosition($session->id))->toBe(1);

        // Add first entry
        TrainingWaitlist::factory()
            ->forSession($session)
            ->waiting()
            ->atPosition(1)
            ->create();

        expect(TrainingWaitlist::getNextPosition($session->id))->toBe(2);

        // Add more entries
        TrainingWaitlist::factory()
            ->forSession($session)
            ->waiting()
            ->atPosition(2)
            ->create();

        expect(TrainingWaitlist::getNextPosition($session->id))->toBe(3);
    });

    it('orders waitlist by position ascending', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        $session = TrainingSession::factory()->scheduled()->create();

        // Create entries out of order
        TrainingWaitlist::factory()
            ->forSession($session)
            ->waiting()
            ->atPosition(3)
            ->create();

        TrainingWaitlist::factory()
            ->forSession($session)
            ->waiting()
            ->atPosition(1)
            ->create();

        TrainingWaitlist::factory()
            ->forSession($session)
            ->waiting()
            ->atPosition(2)
            ->create();

        $ordered = TrainingWaitlist::where('training_session_id', $session->id)
            ->ordered()
            ->get();

        expect($ordered[0]->position)->toBe(1)
            ->and($ordered[1]->position)->toBe(2)
            ->and($ordered[2]->position)->toBe(3);
    });

    it('filters waiting entries with scope', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWaitlist($tenant);

        $session = TrainingSession::factory()->scheduled()->create();

        TrainingWaitlist::factory()
            ->forSession($session)
            ->waiting()
            ->create();

        TrainingWaitlist::factory()
            ->forSession($session)
            ->promoted()
            ->create();

        TrainingWaitlist::factory()
            ->forSession($session)
            ->cancelled()
            ->create();

        $waiting = TrainingWaitlist::where('training_session_id', $session->id)
            ->waiting()
            ->get();

        expect($waiting)->toHaveCount(1);
    });
});
