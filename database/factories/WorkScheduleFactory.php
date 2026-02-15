<?php

namespace Database\Factories;

use App\Enums\ScheduleType;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = WorkSchedule::class;

    /**
     * Define the model's default state.
     *
     * Default state is a Fixed schedule.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Regular Office Hours',
            'code' => fake()->unique()->regexify('[A-Z]{2,3}-[0-9]{3}'),
            'schedule_type' => ScheduleType::Fixed,
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
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
    }

    /**
     * Indicate that the schedule is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the schedule is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Create a Flexible schedule.
     */
    public function flexible(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Flexible with Core Hours',
            'schedule_type' => ScheduleType::Flexible,
            'time_configuration' => [
                'required_hours_per_day' => 8,
                'required_hours_per_week' => 40,
                'core_hours' => [
                    'start_time' => '10:00',
                    'end_time' => '15:00',
                ],
                'flexible_start_window' => [
                    'earliest' => '06:00',
                    'latest' => '10:00',
                ],
                'break' => [
                    'start_time' => null,
                    'duration_minutes' => 60,
                ],
            ],
        ]);
    }

    /**
     * Create a Shifting schedule.
     */
    public function shifting(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Shift Schedule',
            'schedule_type' => ScheduleType::Shifting,
            'time_configuration' => [
                'shifts' => [
                    [
                        'name' => 'Morning Shift',
                        'start_time' => '06:00',
                        'end_time' => '14:00',
                        'break' => [
                            'start_time' => '10:00',
                            'duration_minutes' => 30,
                        ],
                    ],
                    [
                        'name' => 'Afternoon Shift',
                        'start_time' => '14:00',
                        'end_time' => '22:00',
                        'break' => [
                            'start_time' => '18:00',
                            'duration_minutes' => 30,
                        ],
                    ],
                    [
                        'name' => 'Night Shift',
                        'start_time' => '22:00',
                        'end_time' => '06:00',
                        'break' => [
                            'start_time' => '02:00',
                            'duration_minutes' => 30,
                        ],
                    ],
                ],
            ],
            'night_differential' => [
                'enabled' => true,
                'start_time' => '22:00',
                'end_time' => '06:00',
                'rate_multiplier' => 1.10,
            ],
        ]);
    }

    /**
     * Create a Compressed (4x10) schedule.
     */
    public function compressed(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Compressed 4x10',
            'schedule_type' => ScheduleType::Compressed,
            'time_configuration' => [
                'pattern' => '4x10',
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday'],
                'daily_hours' => 10,
                'half_day' => [
                    'enabled' => false,
                    'day' => null,
                    'hours' => null,
                ],
            ],
        ]);
    }

    /**
     * Create a Compressed 4.5-day schedule variant.
     */
    public function compressedHalfDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Compressed 4.5-Day',
            'schedule_type' => ScheduleType::Compressed,
            'time_configuration' => [
                'pattern' => '4.5-day',
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'daily_hours' => 9,
                'half_day' => [
                    'enabled' => true,
                    'day' => 'friday',
                    'hours' => 4,
                ],
            ],
        ]);
    }

    /**
     * Configure schedule with night differential enabled.
     */
    public function withNightDifferential(): static
    {
        return $this->state(fn (array $attributes) => [
            'night_differential' => [
                'enabled' => true,
                'start_time' => '22:00',
                'end_time' => '06:00',
                'rate_multiplier' => 1.10,
            ],
        ]);
    }

    /**
     * Configure schedule without any break period.
     */
    public function withoutBreaks(): static
    {
        return $this->state(function (array $attributes) {
            $timeConfig = $attributes['time_configuration'] ?? [];
            unset($timeConfig['break']);

            return [
                'time_configuration' => $timeConfig,
            ];
        });
    }

    /**
     * Configure schedule with half-day Saturday.
     */
    public function withHalfDaySaturday(): static
    {
        return $this->state(function (array $attributes) {
            $timeConfig = $attributes['time_configuration'] ?? [];
            $timeConfig['work_days'] = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $timeConfig['half_day_saturday'] = true;
            $timeConfig['saturday_end_time'] = '12:00';

            return [
                'time_configuration' => $timeConfig,
            ];
        });
    }
}
