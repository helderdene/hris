<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\DepartmentHeadHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DepartmentHeadHistory>
 */
class DepartmentHeadHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = DepartmentHeadHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'employee_id' => fake()->optional()->numberBetween(1, 1000),
            'started_at' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'ended_at' => null,
        ];
    }

    /**
     * Indicate that this is a past head record (ended).
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => fake()->dateTimeBetween($attributes['started_at'] ?? '-6 months', 'now'),
        ]);
    }

    /**
     * Indicate that this is a current head record.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
        ]);
    }
}
