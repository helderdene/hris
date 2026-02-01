<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeStatusHistory>
 */
class EmployeeStatusHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EmployeeStatusHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'previous_status' => null,
            'new_status' => EmploymentStatus::Active,
            'effective_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'remarks' => fake()->optional()->sentence(),
            'changed_by' => fake()->optional()->numberBetween(1, 100),
            'ended_at' => null,
        ];
    }

    /**
     * Indicate that this is a past status record (ended).
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => fake()->dateTimeBetween($attributes['effective_date'] ?? '-6 months', 'now'),
        ]);
    }

    /**
     * Indicate that this is a current status record.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
        ]);
    }

    /**
     * Create a status change to Active.
     */
    public function toActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'new_status' => EmploymentStatus::Active,
        ]);
    }

    /**
     * Create a status change to Resigned.
     */
    public function toResigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => EmploymentStatus::Active,
            'new_status' => EmploymentStatus::Resigned,
            'remarks' => fake()->optional()->sentence(),
        ]);
    }

    /**
     * Create a status change to Terminated.
     */
    public function toTerminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => EmploymentStatus::Active,
            'new_status' => EmploymentStatus::Terminated,
            'remarks' => fake()->optional()->sentence(),
        ]);
    }

    /**
     * Create a status change to Retired.
     */
    public function toRetired(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => EmploymentStatus::Active,
            'new_status' => EmploymentStatus::Retired,
            'remarks' => 'Reached retirement age',
        ]);
    }

    /**
     * Create a status change to EndOfContract.
     */
    public function toEndOfContract(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => EmploymentStatus::Active,
            'new_status' => EmploymentStatus::EndOfContract,
            'remarks' => 'Contract period completed',
        ]);
    }

    /**
     * Create an initial status record (new hire).
     */
    public function initialHire(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => null,
            'new_status' => EmploymentStatus::Active,
            'remarks' => 'Initial hire',
        ]);
    }
}
