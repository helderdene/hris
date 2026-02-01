<?php

namespace Database\Factories;

use App\Enums\KeyResultMetricType;
use App\Models\Goal;
use App\Models\GoalKeyResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoalKeyResult>
 */
class GoalKeyResultFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = GoalKeyResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metricType = fake()->randomElement(KeyResultMetricType::cases());
        $targetValue = match ($metricType) {
            KeyResultMetricType::Boolean => 1,
            KeyResultMetricType::Percentage => fake()->randomFloat(0, 50, 100),
            KeyResultMetricType::Currency => fake()->randomFloat(2, 10000, 100000),
            KeyResultMetricType::Number => fake()->randomFloat(0, 100, 10000),
        };

        return [
            'goal_id' => Goal::factory()->okr(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'metric_type' => $metricType,
            'metric_unit' => $metricType === KeyResultMetricType::Number ? fake()->randomElement(['units', 'items', 'customers', null]) : null,
            'target_value' => $targetValue,
            'starting_value' => 0,
            'current_value' => null,
            'achievement_percentage' => null,
            'weight' => 1.00,
            'status' => 'pending',
            'completed_at' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the key result uses number metric.
     */
    public function number(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => KeyResultMetricType::Number,
            'target_value' => fake()->randomFloat(0, 100, 10000),
            'metric_unit' => fake()->randomElement(['units', 'items', 'customers']),
        ]);
    }

    /**
     * Indicate that the key result uses percentage metric.
     */
    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => KeyResultMetricType::Percentage,
            'target_value' => fake()->randomFloat(0, 50, 100),
            'metric_unit' => null,
        ]);
    }

    /**
     * Indicate that the key result uses currency metric.
     */
    public function currency(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => KeyResultMetricType::Currency,
            'target_value' => fake()->randomFloat(2, 10000, 100000),
            'metric_unit' => '$',
        ]);
    }

    /**
     * Indicate that the key result uses boolean metric.
     */
    public function boolean(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => KeyResultMetricType::Boolean,
            'target_value' => 1,
            'starting_value' => 0,
            'metric_unit' => null,
        ]);
    }

    /**
     * Indicate that the key result is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_value'] ?? 100;
            $start = $attributes['starting_value'] ?? 0;
            $current = fake()->randomFloat(2, $start, $target);
            $range = $target - $start;
            $achievement = $range > 0 ? (($current - $start) / $range) * 100 : 0;

            return [
                'current_value' => $current,
                'achievement_percentage' => $achievement,
                'status' => 'in_progress',
            ];
        });
    }

    /**
     * Indicate that the key result is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_value'] ?? 100;

            return [
                'current_value' => $target,
                'achievement_percentage' => 100,
                'status' => 'completed',
                'completed_at' => now(),
            ];
        });
    }

    /**
     * Set specific target value.
     */
    public function withTarget(float $target, float $starting = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'target_value' => $target,
            'starting_value' => $starting,
        ]);
    }

    /**
     * Set specific current value with calculated achievement.
     */
    public function withCurrentValue(float $current): static
    {
        return $this->state(function (array $attributes) use ($current) {
            $target = $attributes['target_value'] ?? 100;
            $start = $attributes['starting_value'] ?? 0;
            $range = $target - $start;
            $achievement = $range > 0 ? min((($current - $start) / $range) * 100, 100) : 0;

            return [
                'current_value' => $current,
                'achievement_percentage' => $achievement,
                'status' => 'in_progress',
            ];
        });
    }
}
