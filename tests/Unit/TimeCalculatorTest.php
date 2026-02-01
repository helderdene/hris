<?php

use App\Enums\ScheduleType;
use App\Models\WorkSchedule;
use App\Services\Dtr\ScheduleResolver;
use App\Services\Dtr\TimeCalculator;
use Carbon\Carbon;

beforeEach(function () {
    $this->scheduleResolver = new ScheduleResolver;
    $this->calculator = new TimeCalculator($this->scheduleResolver);
});

describe('TimeCalculator Late Calculations', function () {
    it('calculates late minutes for fixed schedule', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'start_time' => '08:00',
                'end_time' => '17:00',
            ],
            'overtime_rules' => [],
            'night_differential' => ['enabled' => false],
        ]);

        $date = Carbon::parse('2025-01-15');
        $firstIn = Carbon::parse('2025-01-15 08:30:00'); // 30 minutes late

        $lateMinutes = $this->calculator->calculateLate($firstIn, $schedule, $date);

        expect($lateMinutes)->toBe(30);
    });

    it('returns zero late minutes when on time', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'start_time' => '08:00',
                'end_time' => '17:00',
            ],
            'overtime_rules' => [],
            'night_differential' => ['enabled' => false],
        ]);

        $date = Carbon::parse('2025-01-15');
        $firstIn = Carbon::parse('2025-01-15 08:00:00'); // On time

        $lateMinutes = $this->calculator->calculateLate($firstIn, $schedule, $date);

        expect($lateMinutes)->toBe(0);
    });

    it('returns zero late minutes when early', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'start_time' => '08:00',
                'end_time' => '17:00',
            ],
            'overtime_rules' => [],
            'night_differential' => ['enabled' => false],
        ]);

        $date = Carbon::parse('2025-01-15');
        $firstIn = Carbon::parse('2025-01-15 07:45:00'); // 15 minutes early

        $lateMinutes = $this->calculator->calculateLate($firstIn, $schedule, $date);

        expect($lateMinutes)->toBe(0);
    });

    it('calculates late using core hours for flexible schedule', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Flexible,
            'time_configuration' => [
                'required_hours_per_day' => 8,
                'core_hours' => [
                    'start_time' => '10:00',
                    'end_time' => '15:00',
                ],
                'flexible_start_window' => [
                    'earliest' => '06:00',
                    'latest' => '10:00',
                ],
            ],
            'overtime_rules' => [],
            'night_differential' => ['enabled' => false],
        ]);

        $date = Carbon::parse('2025-01-15');
        $firstIn = Carbon::parse('2025-01-15 10:15:00'); // 15 minutes after core hours start

        $lateMinutes = $this->calculator->calculateLate($firstIn, $schedule, $date);

        expect($lateMinutes)->toBe(15);
    });
});

describe('TimeCalculator Undertime Calculations', function () {
    it('calculates undertime minutes for fixed schedule', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'start_time' => '08:00',
                'end_time' => '17:00',
            ],
            'overtime_rules' => [],
            'night_differential' => ['enabled' => false],
        ]);

        $date = Carbon::parse('2025-01-15');
        $lastOut = Carbon::parse('2025-01-15 16:30:00'); // 30 minutes early

        $undertimeMinutes = $this->calculator->calculateUndertime($lastOut, $schedule, $date);

        expect($undertimeMinutes)->toBe(30);
    });

    it('returns zero undertime when worked full shift', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'start_time' => '08:00',
                'end_time' => '17:00',
            ],
            'overtime_rules' => [],
            'night_differential' => ['enabled' => false],
        ]);

        $date = Carbon::parse('2025-01-15');
        $lastOut = Carbon::parse('2025-01-15 17:00:00'); // Exactly on time

        $undertimeMinutes = $this->calculator->calculateUndertime($lastOut, $schedule, $date);

        expect($undertimeMinutes)->toBe(0);
    });

    it('calculates undertime using core hours for flexible schedule', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Flexible,
            'time_configuration' => [
                'required_hours_per_day' => 8,
                'core_hours' => [
                    'start_time' => '10:00',
                    'end_time' => '15:00',
                ],
            ],
            'overtime_rules' => [],
            'night_differential' => ['enabled' => false],
        ]);

        $date = Carbon::parse('2025-01-15');
        $lastOut = Carbon::parse('2025-01-15 14:45:00'); // 15 minutes before core hours end

        $undertimeMinutes = $this->calculator->calculateUndertime($lastOut, $schedule, $date);

        expect($undertimeMinutes)->toBe(15);
    });
});

describe('TimeCalculator Overtime Calculations', function () {
    it('calculates overtime for work after scheduled end', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'start_time' => '08:00',
                'end_time' => '17:00',
            ],
            'overtime_rules' => [
                'daily_threshold_hours' => 8,
            ],
            'night_differential' => ['enabled' => false],
        ]);

        $date = Carbon::parse('2025-01-15');
        $lastOut = Carbon::parse('2025-01-15 19:00:00'); // 2 hours after end
        $totalWorkMinutes = 600; // 10 hours

        $overtimeMinutes = $this->calculator->calculateOvertime($lastOut, $totalWorkMinutes, $schedule, $date);

        expect($overtimeMinutes)->toBe(120); // 2 hours overtime
    });

    it('returns zero overtime when leaving on time', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'start_time' => '08:00',
                'end_time' => '17:00',
            ],
            'overtime_rules' => [
                'daily_threshold_hours' => 8,
            ],
            'night_differential' => ['enabled' => false],
        ]);

        $date = Carbon::parse('2025-01-15');
        $lastOut = Carbon::parse('2025-01-15 17:00:00');
        $totalWorkMinutes = 480; // 8 hours

        $overtimeMinutes = $this->calculator->calculateOvertime($lastOut, $totalWorkMinutes, $schedule, $date);

        expect($overtimeMinutes)->toBe(0);
    });
});

describe('TimeCalculator Night Differential Calculations', function () {
    it('calculates night differential for work during ND hours', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'start_time' => '22:00',
                'end_time' => '06:00',
            ],
            'overtime_rules' => [],
            'night_differential' => [
                'enabled' => true,
                'start_time' => '22:00',
                'end_time' => '06:00',
                'rate_multiplier' => 1.10,
            ],
        ]);

        $workPeriods = [
            [
                'in' => Carbon::parse('2025-01-15 22:00:00'),
                'out' => Carbon::parse('2025-01-16 06:00:00'),
            ],
        ];

        $ndMinutes = $this->calculator->calculateNightDifferential($workPeriods, $schedule);

        expect($ndMinutes)->toBe(480); // 8 hours of night differential
    });

    it('returns zero when night differential is disabled', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'start_time' => '22:00',
                'end_time' => '06:00',
            ],
            'overtime_rules' => [],
            'night_differential' => [
                'enabled' => false,
            ],
        ]);

        $workPeriods = [
            [
                'in' => Carbon::parse('2025-01-15 22:00:00'),
                'out' => Carbon::parse('2025-01-16 06:00:00'),
            ],
        ];

        $ndMinutes = $this->calculator->calculateNightDifferential($workPeriods, $schedule);

        expect($ndMinutes)->toBe(0);
    });

    it('calculates partial night differential', function () {
        $schedule = new WorkSchedule([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'start_time' => '18:00',
                'end_time' => '02:00',
            ],
            'overtime_rules' => [],
            'night_differential' => [
                'enabled' => true,
                'start_time' => '22:00',
                'end_time' => '06:00',
            ],
        ]);

        // Work from 18:00 to 02:00 (8 hours)
        // ND applies from 22:00 to 02:00 (4 hours)
        $workPeriods = [
            [
                'in' => Carbon::parse('2025-01-15 18:00:00'),
                'out' => Carbon::parse('2025-01-16 02:00:00'),
            ],
        ];

        $ndMinutes = $this->calculator->calculateNightDifferential($workPeriods, $schedule);

        expect($ndMinutes)->toBe(240); // 4 hours of night differential
    });
});
