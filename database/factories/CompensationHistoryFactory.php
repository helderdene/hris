<?php

namespace Database\Factories;

use App\Enums\PayType;
use App\Models\CompensationHistory;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompensationHistory>
 */
class CompensationHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CompensationHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'previous_basic_pay' => fake()->optional(0.5)->randomFloat(2, 15000, 150000),
            'new_basic_pay' => fake()->randomFloat(2, 20000, 200000),
            'previous_pay_type' => fake()->optional(0.5)->randomElement(PayType::cases()),
            'new_pay_type' => fake()->randomElement(PayType::cases()),
            'effective_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'changed_by' => fake()->optional()->numberBetween(1, 100),
            'remarks' => fake()->optional()->sentence(),
            'ended_at' => null,
        ];
    }

    /**
     * Indicate that this is a past history record (ended).
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => fake()->dateTimeBetween($attributes['effective_date'] ?? '-6 months', 'now'),
        ]);
    }

    /**
     * Indicate that this is a current history record.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
        ]);
    }

    /**
     * Create an initial compensation record (first compensation, no previous value).
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_basic_pay' => null,
            'previous_pay_type' => null,
            'remarks' => 'Initial compensation',
        ]);
    }

    /**
     * Create a compensation change (has previous values).
     */
    public function change(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_basic_pay' => fake()->randomFloat(2, 15000, 150000),
            'previous_pay_type' => fake()->randomElement(PayType::cases()),
            'remarks' => fake()->optional()->sentence(),
        ]);
    }

    /**
     * Create a salary increase record.
     */
    public function increase(float $previousAmount, float $newAmount): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_basic_pay' => $previousAmount,
            'new_basic_pay' => $newAmount,
            'remarks' => 'Salary increase',
        ]);
    }
}
