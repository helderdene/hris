<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalMilestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoalMilestone>
 */
class GoalMilestoneFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = GoalMilestone::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory()->smart(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+3 months'),
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the milestone is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ]);
    }

    /**
     * Set a specific due date.
     */
    public function dueOn(\DateTime|string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $date,
        ]);
    }

    /**
     * Indicate that the milestone is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'is_completed' => false,
        ]);
    }

    /**
     * Set a specific sort order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}
