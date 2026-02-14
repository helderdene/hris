<?php

use App\Models\WorkSchedule;
use App\Services\Dtr\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('returns correct end time for normal fixed schedule', function () {
    $schedule = WorkSchedule::factory()->create([
        'time_configuration' => [
            'start_time' => '08:00',
            'end_time' => '17:00',
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'break' => ['start_time' => '12:00', 'duration_minutes' => 60],
        ],
    ]);

    $resolver = app(ScheduleResolver::class);
    $date = Carbon::parse('2025-02-13');
    $endTime = $resolver->getScheduledEndTime($schedule, $date);

    expect($endTime->toDateTimeString())->toBe('2025-02-13 17:00:00');
});

it('handles cross-midnight end time for fixed schedule', function () {
    $schedule = WorkSchedule::factory()->create([
        'time_configuration' => [
            'start_time' => '17:00',
            'end_time' => '00:00',
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'break' => ['start_time' => '20:00', 'duration_minutes' => 60],
        ],
    ]);

    $resolver = app(ScheduleResolver::class);
    $date = Carbon::parse('2025-02-13');
    $endTime = $resolver->getScheduledEndTime($schedule, $date);

    expect($endTime->toDateTimeString())->toBe('2025-02-14 00:00:00');
});

it('handles cross-midnight end time for evening shift', function () {
    $schedule = WorkSchedule::factory()->create([
        'time_configuration' => [
            'start_time' => '14:00',
            'end_time' => '02:00',
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'break' => ['start_time' => '18:00', 'duration_minutes' => 60],
        ],
    ]);

    $resolver = app(ScheduleResolver::class);
    $date = Carbon::parse('2025-02-13');
    $endTime = $resolver->getScheduledEndTime($schedule, $date);

    expect($endTime->toDateTimeString())->toBe('2025-02-14 02:00:00');
});

it('handles cross-midnight for shifting schedule', function () {
    $schedule = WorkSchedule::factory()->shifting()->create();

    $resolver = app(ScheduleResolver::class);
    $date = Carbon::parse('2025-02-13');
    $endTime = $resolver->getScheduledEndTime($schedule, $date, 'Night Shift');

    // Night Shift: 22:00 - 06:00 → end should be next day
    expect($endTime->toDateTimeString())->toBe('2025-02-14 06:00:00');
});

it('does not add day when end time equals start time for non-cross-midnight schedule', function () {
    $schedule = WorkSchedule::factory()->create([
        'time_configuration' => [
            'start_time' => '08:00',
            'end_time' => '08:00',
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'break' => ['start_time' => '12:00', 'duration_minutes' => 60],
        ],
    ]);

    $resolver = app(ScheduleResolver::class);
    $date = Carbon::parse('2025-02-13');
    $endTime = $resolver->getScheduledEndTime($schedule, $date);

    // 08:00 end == 08:00 start → treated as 24-hour shift (addDay)
    expect($endTime->toDateTimeString())->toBe('2025-02-14 08:00:00');
});
