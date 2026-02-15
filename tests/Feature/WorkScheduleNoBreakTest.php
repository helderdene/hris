<?php

use App\Enums\ScheduleType;
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

it('creates a fixed schedule with no break', function () {
    $schedule = WorkSchedule::factory()->withoutBreaks()->create();

    expect($schedule->time_configuration)->not->toHaveKey('break');
    expect($schedule->schedule_type)->toBe(ScheduleType::Fixed);
});

it('returns zero break duration for schedule without break config', function () {
    $schedule = WorkSchedule::factory()->withoutBreaks()->create();

    $resolver = app(ScheduleResolver::class);
    $breakDuration = $resolver->getBreakDuration($schedule);

    expect($breakDuration)->toBe(0);
});

it('calculates correct required work minutes without break deduction', function () {
    $schedule = WorkSchedule::factory()->withoutBreaks()->create([
        'time_configuration' => [
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'half_day_saturday' => false,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'saturday_end_time' => null,
        ],
    ]);

    $resolver = app(ScheduleResolver::class);
    $date = Carbon::parse('2025-02-13'); // Thursday

    $requiredMinutes = $resolver->getRequiredWorkMinutes($schedule, $date);

    // 08:00 to 17:00 = 540 minutes, no break deducted
    expect($requiredMinutes)->toBe(540);
});

it('calculates correct required work minutes with break deduction', function () {
    $schedule = WorkSchedule::factory()->create([
        'time_configuration' => [
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'half_day_saturday' => false,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'saturday_end_time' => null,
            'break' => [
                'start_time' => '12:00',
                'duration_minutes' => 60,
            ],
        ],
    ]);

    $resolver = app(ScheduleResolver::class);
    $date = Carbon::parse('2025-02-13'); // Thursday

    $requiredMinutes = $resolver->getRequiredWorkMinutes($schedule, $date);

    // 08:00 to 17:00 = 540 minutes - 60 min break = 480 minutes
    expect($requiredMinutes)->toBe(480);
});

it('returns zero break duration for shifting schedule shift without break', function () {
    $schedule = WorkSchedule::factory()->create([
        'schedule_type' => ScheduleType::Shifting,
        'time_configuration' => [
            'shifts' => [
                [
                    'name' => 'Short Shift',
                    'start_time' => '08:00',
                    'end_time' => '12:00',
                ],
            ],
        ],
    ]);

    $resolver = app(ScheduleResolver::class);
    $breakDuration = $resolver->getBreakDuration($schedule, 'Short Shift');

    expect($breakDuration)->toBe(0);
});

it('stores schedule with null break via API validation', function () {
    $rules = (new \App\Http\Requests\StoreWorkScheduleRequest)->rules();

    $data = [
        'name' => 'No Break Schedule',
        'code' => 'NBS-001',
        'schedule_type' => 'fixed',
        'status' => 'active',
        'time_configuration' => [
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'half_day_saturday' => false,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'saturday_end_time' => null,
            'break' => null,
        ],
        'overtime_rules' => [
            'daily_threshold_hours' => 8,
            'weekly_threshold_hours' => 40,
            'regular_multiplier' => 1.25,
            'rest_day_multiplier' => 1.30,
            'holiday_multiplier' => 2.0,
        ],
        'night_differential' => [
            'enabled' => false,
            'start_time' => '22:00',
            'end_time' => '06:00',
            'rate_multiplier' => 1.10,
        ],
    ];

    $validator = \Illuminate\Support\Facades\Validator::make($data, $rules);

    expect($validator->fails())->toBeFalse();
});
