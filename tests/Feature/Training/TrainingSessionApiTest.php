<?php

use App\Enums\TenantUserRole;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForTraining(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForTraining(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('TrainingSession Model', function () {
    it('calculates enrolled count correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $session = TrainingSession::factory()->scheduled()->create();

        expect($session->enrolled_count)->toBe(0);

        // Add enrollments
        TrainingEnrollment::factory()
            ->count(3)
            ->forSession($session)
            ->confirmed()
            ->create();

        $session->refresh();
        expect($session->enrolled_count)->toBe(3);
    });

    it('calculates available slots correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(10)
            ->create();

        expect($session->available_slots)->toBe(10)
            ->and($session->is_full)->toBeFalse();

        // Add some enrollments
        TrainingEnrollment::factory()
            ->count(5)
            ->forSession($session)
            ->confirmed()
            ->create();

        $session->refresh();
        expect($session->available_slots)->toBe(5);
    });

    it('marks session as full when at capacity', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $session = TrainingSession::factory()
            ->scheduled()
            ->withCapacity(2)
            ->create();

        TrainingEnrollment::factory()
            ->count(2)
            ->forSession($session)
            ->confirmed()
            ->create();

        $session->refresh();
        expect($session->is_full)->toBeTrue()
            ->and($session->available_slots)->toBe(0);
    });

    it('returns null for available slots when no capacity limit', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        // Create course without max_participants
        $course = Course::factory()->create(['max_participants' => null]);
        $session = TrainingSession::factory()
            ->forCourse($course)
            ->unlimitedCapacity()
            ->create();

        expect($session->available_slots)->toBeNull()
            ->and($session->is_full)->toBeFalse();
    });

    it('generates display title from course when title is null', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $course = Course::factory()->create(['title' => 'Test Course']);
        $session = TrainingSession::factory()
            ->forCourse($course)
            ->state(['title' => null])
            ->create();

        expect($session->display_title)->toBe('Test Course');
    });

    it('uses session title when provided', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $course = Course::factory()->create(['title' => 'Test Course']);
        $session = TrainingSession::factory()
            ->forCourse($course)
            ->state(['title' => 'Custom Session Title'])
            ->create();

        expect($session->display_title)->toBe('Custom Session Title');
    });

    it('formats date range correctly for same day', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $session = TrainingSession::factory()
            ->state([
                'start_date' => '2025-03-15',
                'end_date' => '2025-03-15',
            ])
            ->create();

        expect($session->date_range)->toContain('Mar 15');
    });

    it('formats date range correctly for multi-day', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $session = TrainingSession::factory()
            ->state([
                'start_date' => '2025-03-15',
                'end_date' => '2025-03-17',
            ])
            ->create();

        expect($session->date_range)->toContain('Mar 15')
            ->and($session->date_range)->toContain('Mar 17');
    });

    it('formats time range correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $session = TrainingSession::factory()
            ->state([
                'start_time' => '09:00',
                'end_time' => '17:00',
            ])
            ->create();

        expect($session->time_range)->toContain('9:00')
            ->and($session->time_range)->toContain('5:00');
    });

    it('returns null for time range when no times set', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $session = TrainingSession::factory()
            ->state([
                'start_time' => null,
                'end_time' => null,
            ])
            ->create();

        expect($session->time_range)->toBeNull();
    });
});

describe('TrainingSession Scopes', function () {
    it('filters upcoming sessions correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        TrainingSession::factory()
            ->scheduled()
            ->upcoming()
            ->create();

        TrainingSession::factory()
            ->completed()
            ->past()
            ->create();

        $upcoming = TrainingSession::upcoming()->get();

        expect($upcoming)->toHaveCount(1);
    });

    it('filters scheduled sessions', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        TrainingSession::factory()->draft()->create();
        TrainingSession::factory()->scheduled()->create();
        TrainingSession::factory()->completed()->create();

        $scheduled = TrainingSession::scheduled()->get();

        expect($scheduled)->toHaveCount(1);
    });

    it('filters sessions in a month', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        // Session in March
        TrainingSession::factory()
            ->scheduled()
            ->state([
                'start_date' => '2025-03-15',
                'end_date' => '2025-03-16',
            ])
            ->create();

        // Session in April
        TrainingSession::factory()
            ->scheduled()
            ->state([
                'start_date' => '2025-04-15',
                'end_date' => '2025-04-16',
            ])
            ->create();

        $marchSessions = TrainingSession::inMonth(2025, 3)->get();

        expect($marchSessions)->toHaveCount(1);
    });

    it('includes multi-day sessions spanning months', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        // Session starting in Feb ending in March
        TrainingSession::factory()
            ->scheduled()
            ->state([
                'start_date' => '2025-02-28',
                'end_date' => '2025-03-05',
            ])
            ->create();

        $marchSessions = TrainingSession::inMonth(2025, 3)->get();

        expect($marchSessions)->toHaveCount(1);
    });
});

describe('TrainingSession Relationships', function () {
    it('belongs to a course', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $course = Course::factory()->create();
        $session = TrainingSession::factory()->forCourse($course)->create();

        expect($session->course->id)->toBe($course->id);
    });

    it('has many enrollments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $session = TrainingSession::factory()->scheduled()->create();
        TrainingEnrollment::factory()
            ->count(3)
            ->forSession($session)
            ->create();

        expect($session->enrollments)->toHaveCount(3);
    });

    it('has instructor relationship', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $instructor = Employee::factory()->create();
        $session = TrainingSession::factory()
            ->withInstructor($instructor)
            ->create();

        expect($session->instructor->id)->toBe($instructor->id);
    });

    it('has creator relationship', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $creator = Employee::factory()->create();
        $session = TrainingSession::factory()
            ->createdBy($creator)
            ->create();

        expect($session->creator->id)->toBe($creator->id);
    });
});

describe('TrainingSession Status Methods', function () {
    it('returns correct status label from enum', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $draftSession = TrainingSession::factory()->draft()->create();
        $scheduledSession = TrainingSession::factory()->scheduled()->create();
        $completedSession = TrainingSession::factory()->completed()->create();
        $cancelledSession = TrainingSession::factory()->cancelled()->create();

        expect($draftSession->status->label())->toBe('Draft')
            ->and($scheduledSession->status->label())->toBe('Scheduled')
            ->and($completedSession->status->label())->toBe('Completed')
            ->and($cancelledSession->status->label())->toBe('Cancelled');
    });

    it('uses effective max participants from session when set', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $course = Course::factory()->create(['max_participants' => 30]);
        $session = TrainingSession::factory()
            ->forCourse($course)
            ->withCapacity(15)
            ->create();

        expect($session->effective_max_participants)->toBe(15);
    });

    it('falls back to course max participants when session has none', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTraining($tenant);

        $course = Course::factory()->create(['max_participants' => 30]);
        $session = TrainingSession::factory()
            ->forCourse($course)
            ->unlimitedCapacity()
            ->create();

        expect($session->effective_max_participants)->toBe(30);
    });
});
