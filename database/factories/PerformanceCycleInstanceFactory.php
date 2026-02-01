<?php

namespace Database\Factories;

use App\Enums\PerformanceCycleInstanceStatus;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PerformanceCycleInstance>
 */
class PerformanceCycleInstanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PerformanceCycleInstance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2027);
        $instanceNumber = fake()->numberBetween(1, 2);

        return [
            'performance_cycle_id' => PerformanceCycle::factory(),
            'name' => "Performance Review {$year} - Instance {$instanceNumber}",
            'year' => $year,
            'instance_number' => $instanceNumber,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'status' => PerformanceCycleInstanceStatus::Draft,
            'employee_count' => 0,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that this is a draft instance.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PerformanceCycleInstanceStatus::Draft,
        ]);
    }

    /**
     * Indicate that this is an active instance.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PerformanceCycleInstanceStatus::Active,
            'activated_at' => now(),
        ]);
    }

    /**
     * Indicate that this instance is in evaluation.
     */
    public function inEvaluation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PerformanceCycleInstanceStatus::InEvaluation,
            'activated_at' => now()->subDays(30),
            'evaluation_started_at' => now(),
        ]);
    }

    /**
     * Indicate that this is a closed instance.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PerformanceCycleInstanceStatus::Closed,
            'activated_at' => now()->subDays(60),
            'evaluation_started_at' => now()->subDays(30),
            'closed_at' => now(),
        ]);
    }

    /**
     * Set a specific year for the instance.
     */
    public function forYear(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'year' => $year,
            'name' => "Performance Review {$year} - Instance ".($attributes['instance_number'] ?? 1),
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
        ]);
    }

    /**
     * Configure as first half of year (for mid-year cycles).
     */
    public function firstHalf(): static
    {
        return $this->state(function (array $attributes) {
            $year = $attributes['year'] ?? date('Y');

            return [
                'instance_number' => 1,
                'name' => "Performance Review {$year} - First Half",
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-06-30",
            ];
        });
    }

    /**
     * Configure as second half of year (for mid-year cycles).
     */
    public function secondHalf(): static
    {
        return $this->state(function (array $attributes) {
            $year = $attributes['year'] ?? date('Y');

            return [
                'instance_number' => 2,
                'name' => "Performance Review {$year} - Second Half",
                'start_date' => "{$year}-07-01",
                'end_date' => "{$year}-12-31",
            ];
        });
    }

    /**
     * Set a specific employee count.
     */
    public function withEmployeeCount(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_count' => $count,
        ]);
    }
}
