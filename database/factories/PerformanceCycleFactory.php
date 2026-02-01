<?php

namespace Database\Factories;

use App\Enums\PerformanceCycleType;
use App\Models\PerformanceCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PerformanceCycle>
 */
class PerformanceCycleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PerformanceCycle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cycleType = fake()->randomElement(PerformanceCycleType::cases());

        return [
            'name' => $this->getNameForType($cycleType),
            'code' => strtoupper(fake()->unique()->lexify('PERF-????')),
            'cycle_type' => $cycleType,
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
            'is_default' => false,
        ];
    }

    /**
     * Get a meaningful name for a cycle type.
     */
    private function getNameForType(PerformanceCycleType $type): string
    {
        return match ($type) {
            PerformanceCycleType::Annual => 'Annual Performance Review',
            PerformanceCycleType::MidYear => 'Mid-Year Performance Review',
            PerformanceCycleType::Probationary => 'Probationary Review',
            PerformanceCycleType::ProjectBased => 'Project Performance Review',
        };
    }

    /**
     * Indicate that this is an active cycle.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that this is an inactive cycle.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that this is the default cycle.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Create an annual cycle.
     */
    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Annual Performance Review',
            'cycle_type' => PerformanceCycleType::Annual,
        ]);
    }

    /**
     * Create a mid-year cycle.
     */
    public function midYear(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Mid-Year Performance Review',
            'cycle_type' => PerformanceCycleType::MidYear,
        ]);
    }

    /**
     * Create a probationary cycle.
     */
    public function probationary(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Probationary Review',
            'cycle_type' => PerformanceCycleType::Probationary,
        ]);
    }

    /**
     * Create a project-based cycle.
     */
    public function projectBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Project Performance Review',
            'cycle_type' => PerformanceCycleType::ProjectBased,
        ]);
    }

    /**
     * Set a specific code for the cycle.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }
}
