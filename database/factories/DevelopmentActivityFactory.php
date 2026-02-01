<?php

namespace Database\Factories;

use App\Enums\DevelopmentActivityType;
use App\Models\DevelopmentActivity;
use App\Models\DevelopmentPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DevelopmentActivity>
 */
class DevelopmentActivityFactory extends Factory
{
    protected $model = DevelopmentActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'development_plan_item_id' => DevelopmentPlanItem::factory(),
            'activity_type' => fake()->randomElement(DevelopmentActivityType::cases()),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'resource_url' => fake()->optional()->url(),
            'due_date' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
            'is_completed' => false,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => now(),
            'completion_notes' => fake()->optional()->sentence(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays(fake()->numberBetween(1, 14)),
            'is_completed' => false,
        ]);
    }

    public function training(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => DevelopmentActivityType::Training,
        ]);
    }

    public function mentoring(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => DevelopmentActivityType::Mentoring,
        ]);
    }
}
