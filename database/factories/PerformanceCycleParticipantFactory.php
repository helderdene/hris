<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PerformanceCycleParticipant>
 */
class PerformanceCycleParticipantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PerformanceCycleParticipant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_cycle_instance_id' => PerformanceCycleInstance::factory(),
            'employee_id' => Employee::factory(),
            'manager_id' => Employee::factory(),
            'is_excluded' => false,
            'status' => 'pending',
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that this participant is excluded.
     */
    public function excluded(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_excluded' => true,
        ]);
    }

    /**
     * Indicate that this participant has completed their evaluation.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that this participant is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'completed_at' => null,
        ]);
    }

    /**
     * Set without a manager.
     */
    public function withoutManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_id' => null,
        ]);
    }
}
