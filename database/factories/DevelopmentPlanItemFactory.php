<?php

namespace Database\Factories;

use App\Enums\DevelopmentItemStatus;
use App\Enums\GoalPriority;
use App\Models\DevelopmentPlan;
use App\Models\DevelopmentPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DevelopmentPlanItem>
 */
class DevelopmentPlanItemFactory extends Factory
{
    protected $model = DevelopmentPlanItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentLevel = fake()->numberBetween(1, 3);

        return [
            'development_plan_id' => DevelopmentPlan::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'current_level' => $currentLevel,
            'target_level' => fake()->numberBetween($currentLevel + 1, 5),
            'priority' => fake()->randomElement(GoalPriority::cases()),
            'status' => DevelopmentItemStatus::NotStarted,
            'progress_percentage' => 0,
        ];
    }

    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DevelopmentItemStatus::NotStarted,
            'progress_percentage' => 0,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DevelopmentItemStatus::InProgress,
            'progress_percentage' => fake()->numberBetween(1, 99),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DevelopmentItemStatus::Completed,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => GoalPriority::High,
        ]);
    }
}
