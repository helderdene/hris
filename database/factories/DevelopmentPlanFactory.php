<?php

namespace Database\Factories;

use App\Enums\DevelopmentPlanStatus;
use App\Models\DevelopmentPlan;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DevelopmentPlan>
 */
class DevelopmentPlanFactory extends Factory
{
    protected $model = DevelopmentPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => DevelopmentPlanStatus::Draft,
            'start_date' => null,
            'target_completion_date' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'career_path_notes' => fake()->optional()->paragraph(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DevelopmentPlanStatus::Draft,
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DevelopmentPlanStatus::PendingApproval,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DevelopmentPlanStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DevelopmentPlanStatus::InProgress,
            'start_date' => now(),
            'approved_at' => now()->subDay(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DevelopmentPlanStatus::Completed,
            'start_date' => now()->subMonth(),
            'completed_at' => now(),
        ]);
    }
}
