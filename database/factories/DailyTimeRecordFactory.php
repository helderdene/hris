<?php

namespace Database\Factories;

use App\Enums\DtrStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DailyTimeRecord>
 */
class DailyTimeRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = DailyTimeRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 month', 'now');
        $firstIn = Carbon::parse($date)->setTime(8, fake()->numberBetween(0, 15), 0);
        $lastOut = Carbon::parse($date)->setTime(17, fake()->numberBetween(0, 30), 0);

        return [
            'employee_id' => Employee::factory(),
            'work_schedule_id' => WorkSchedule::factory(),
            'date' => $date,
            'shift_name' => null,
            'status' => DtrStatus::Present,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
            'total_work_minutes' => 480,
            'total_break_minutes' => 60,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'overtime_approved' => false,
            'overtime_denied' => false,
            'night_diff_minutes' => 0,
            'remarks' => null,
            'needs_review' => false,
            'review_reason' => null,
            'computed_at' => now(),
        ];
    }

    /**
     * Indicate that the employee was absent.
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DtrStatus::Absent,
            'first_in' => null,
            'last_out' => null,
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'night_diff_minutes' => 0,
        ]);
    }

    /**
     * Indicate that the record is for a holiday.
     */
    public function holiday(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DtrStatus::Holiday,
        ]);
    }

    /**
     * Indicate that the record is for a rest day.
     */
    public function restDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DtrStatus::RestDay,
            'first_in' => null,
            'last_out' => null,
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
        ]);
    }

    /**
     * Indicate that the employee has no schedule.
     */
    public function noSchedule(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DtrStatus::NoSchedule,
            'work_schedule_id' => null,
            'needs_review' => true,
            'review_reason' => 'No schedule assigned',
        ]);
    }

    /**
     * Indicate that the employee was late.
     */
    public function late(int $minutes = 30): static
    {
        return $this->state(function (array $attributes) use ($minutes) {
            $date = Carbon::parse($attributes['date']);

            return [
                'first_in' => $date->copy()->setTime(8, $minutes, 0),
                'late_minutes' => $minutes,
            ];
        });
    }

    /**
     * Indicate that the employee left early (undertime).
     */
    public function undertime(int $minutes = 30): static
    {
        return $this->state(function (array $attributes) use ($minutes) {
            $date = Carbon::parse($attributes['date']);
            $leaveHour = 17 - intdiv($minutes, 60);
            $leaveMinute = 60 - ($minutes % 60);

            if ($leaveMinute === 60) {
                $leaveMinute = 0;
            } else {
                $leaveHour--;
            }

            return [
                'last_out' => $date->copy()->setTime($leaveHour, $leaveMinute, 0),
                'undertime_minutes' => $minutes,
                'total_work_minutes' => 480 - $minutes,
            ];
        });
    }

    /**
     * Indicate that the employee worked overtime.
     */
    public function withOvertime(int $minutes = 60, bool $approved = false): static
    {
        return $this->state(function (array $attributes) use ($minutes, $approved) {
            $date = Carbon::parse($attributes['date']);
            $extraHours = intdiv($minutes, 60);
            $extraMinutes = $minutes % 60;

            return [
                'last_out' => $date->copy()->setTime(17 + $extraHours, $extraMinutes, 0),
                'overtime_minutes' => $minutes,
                'overtime_approved' => $approved,
                'total_work_minutes' => 480 + $minutes,
            ];
        });
    }

    /**
     * Indicate that the employee worked night differential hours.
     */
    public function withNightDiff(int $minutes = 120): static
    {
        return $this->state(fn (array $attributes) => [
            'night_diff_minutes' => $minutes,
        ]);
    }

    /**
     * Indicate that the record needs HR review.
     */
    public function needsReview(string $reason = 'Missing time-out'): static
    {
        return $this->state(fn (array $attributes) => [
            'needs_review' => true,
            'review_reason' => $reason,
        ]);
    }

    /**
     * Create a record for a specific date.
     */
    public function forDate(Carbon|string $date): static
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $this->state(function (array $attributes) use ($date) {
            $firstIn = $date->copy()->setTime(8, fake()->numberBetween(0, 15), 0);
            $lastOut = $date->copy()->setTime(17, fake()->numberBetween(0, 30), 0);

            return [
                'date' => $date->toDateString(),
                'first_in' => $firstIn,
                'last_out' => $lastOut,
            ];
        });
    }

    /**
     * Create a record for a shifting schedule.
     */
    public function forShift(string $shiftName): static
    {
        return $this->state(fn (array $attributes) => [
            'shift_name' => $shiftName,
        ]);
    }
}
