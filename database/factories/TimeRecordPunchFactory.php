<?php

namespace Database\Factories;

use App\Enums\PunchType;
use App\Models\AttendanceLog;
use App\Models\DailyTimeRecord;
use App\Models\TimeRecordPunch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeRecordPunch>
 */
class TimeRecordPunchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TimeRecordPunch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_time_record_id' => DailyTimeRecord::factory(),
            'attendance_log_id' => AttendanceLog::factory(),
            'punch_type' => PunchType::In,
            'punched_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'is_valid' => true,
            'invalidation_reason' => null,
        ];
    }

    /**
     * Create a time-in punch.
     */
    public function timeIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'punch_type' => PunchType::In,
        ]);
    }

    /**
     * Create a time-out punch.
     */
    public function timeOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'punch_type' => PunchType::Out,
        ]);
    }

    /**
     * Mark the punch as invalid.
     */
    public function invalid(string $reason = 'Manually invalidated'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_valid' => false,
            'invalidation_reason' => $reason,
        ]);
    }
}
