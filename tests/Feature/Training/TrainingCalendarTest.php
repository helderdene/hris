<?php

use App\Enums\SessionStatus;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\TrainingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForTrainingCalendar(Tenant $tenant): void
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

describe('Training Calendar Query', function () {
    it('returns sessions for specified month', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTrainingCalendar($tenant);

        $course = Course::factory()->published()->create();

        // Create sessions in current month
        $currentMonth = now()->startOfMonth();
        TrainingSession::factory()
            ->count(2)
            ->forCourse($course)
            ->scheduled()
            ->state([
                'start_date' => $currentMonth->copy()->addDays(5)->format('Y-m-d'),
                'end_date' => $currentMonth->copy()->addDays(6)->format('Y-m-d'),
            ])
            ->create();

        // Create session in next month (should not appear)
        TrainingSession::factory()
            ->forCourse($course)
            ->scheduled()
            ->state([
                'start_date' => $currentMonth->copy()->addMonth()->addDays(5)->format('Y-m-d'),
                'end_date' => $currentMonth->copy()->addMonth()->addDays(6)->format('Y-m-d'),
            ])
            ->create();

        $sessions = TrainingSession::inMonth(now()->year, now()->month)->get();

        expect($sessions)->toHaveCount(2);
    });

    it('returns sessions spanning multiple days within the month', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTrainingCalendar($tenant);

        $course = Course::factory()->published()->create();

        // Create a session spanning several days
        $session = TrainingSession::factory()
            ->forCourse($course)
            ->scheduled()
            ->state([
                'start_date' => now()->startOfMonth()->addDays(10)->format('Y-m-d'),
                'end_date' => now()->startOfMonth()->addDays(15)->format('Y-m-d'),
            ])
            ->create();

        $sessions = TrainingSession::inMonth(now()->year, now()->month)->get();

        expect($sessions)->toHaveCount(1);
        expect($sessions->first()->start_date->format('Y-m-d'))->toBe($session->start_date->format('Y-m-d'))
            ->and($sessions->first()->end_date->format('Y-m-d'))->toBe($session->end_date->format('Y-m-d'));
    });

    it('filters by status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTrainingCalendar($tenant);

        $course = Course::factory()->published()->create();
        $currentMonth = now()->startOfMonth();

        // Create scheduled session
        TrainingSession::factory()
            ->forCourse($course)
            ->scheduled()
            ->state([
                'start_date' => $currentMonth->copy()->addDays(5)->format('Y-m-d'),
                'end_date' => $currentMonth->copy()->addDays(5)->format('Y-m-d'),
            ])
            ->create();

        // Create completed session
        TrainingSession::factory()
            ->forCourse($course)
            ->completed()
            ->state([
                'start_date' => $currentMonth->copy()->addDays(3)->format('Y-m-d'),
                'end_date' => $currentMonth->copy()->addDays(3)->format('Y-m-d'),
            ])
            ->create();

        $scheduledSessions = TrainingSession::inMonth(now()->year, now()->month)
            ->where('status', SessionStatus::Scheduled->value)
            ->get();

        expect($scheduledSessions)->toHaveCount(1);
    });

    it('returns enrollment count and capacity info', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTrainingCalendar($tenant);

        $course = Course::factory()->published()->create();

        $session = TrainingSession::factory()
            ->forCourse($course)
            ->scheduled()
            ->withCapacity(10)
            ->state([
                'start_date' => now()->startOfMonth()->addDays(5)->format('Y-m-d'),
                'end_date' => now()->startOfMonth()->addDays(5)->format('Y-m-d'),
            ])
            ->create();

        expect($session->enrolled_count)->toBe(0)
            ->and($session->effective_max_participants)->toBe(10)
            ->and($session->is_full)->toBeFalse();
    });
});

describe('Calendar Date Filtering', function () {
    it('includes sessions that start in the month', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTrainingCalendar($tenant);

        $course = Course::factory()->published()->create();

        // Session starts in March, ends in April
        TrainingSession::factory()
            ->forCourse($course)
            ->scheduled()
            ->state([
                'start_date' => '2025-03-28',
                'end_date' => '2025-04-02',
            ])
            ->create();

        $sessions = TrainingSession::inMonth(2025, 3)->get();

        expect($sessions)->toHaveCount(1);
    });

    it('includes sessions that end in the month', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTrainingCalendar($tenant);

        $course = Course::factory()->published()->create();

        // Session starts in February, ends in March
        TrainingSession::factory()
            ->forCourse($course)
            ->scheduled()
            ->state([
                'start_date' => '2025-02-25',
                'end_date' => '2025-03-05',
            ])
            ->create();

        $sessions = TrainingSession::inMonth(2025, 3)->get();

        expect($sessions)->toHaveCount(1);
    });

    it('includes sessions that span the entire month', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTrainingCalendar($tenant);

        $course = Course::factory()->published()->create();

        // Session spans Feb through April
        TrainingSession::factory()
            ->forCourse($course)
            ->scheduled()
            ->state([
                'start_date' => '2025-02-15',
                'end_date' => '2025-04-15',
            ])
            ->create();

        $sessions = TrainingSession::inMonth(2025, 3)->get();

        expect($sessions)->toHaveCount(1);
    });
});
