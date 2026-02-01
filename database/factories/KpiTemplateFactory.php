<?php

namespace Database\Factories;

use App\Models\KpiTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KpiTemplate>
 */
class KpiTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = KpiTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucwords($name),
            'code' => strtoupper(str_replace(' ', '-', $name)),
            'description' => fake()->optional()->sentence(),
            'metric_unit' => fake()->randomElement(['units', 'PHP', '%', 'score', 'hours', 'count']),
            'default_target' => fake()->randomFloat(2, 10, 10000),
            'default_weight' => fake()->randomFloat(2, 0.5, 2.0),
            'category' => fake()->optional()->randomElement(['Sales', 'Quality', 'Productivity', 'Customer Service']),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that this is a sales category KPI.
     */
    public function salesCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Sales',
            'metric_unit' => 'PHP',
            'name' => 'Sales Target '.fake()->numberBetween(1, 100),
            'code' => 'SALES-'.fake()->unique()->numberBetween(100, 999),
        ]);
    }

    /**
     * Indicate that this is a quality category KPI.
     */
    public function qualityCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Quality',
            'metric_unit' => 'score',
            'name' => 'Quality Score '.fake()->numberBetween(1, 100),
            'code' => 'QUAL-'.fake()->unique()->numberBetween(100, 999),
        ]);
    }

    /**
     * Indicate that this is a productivity category KPI.
     */
    public function productivityCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Productivity',
            'metric_unit' => 'units',
            'name' => 'Productivity Metric '.fake()->numberBetween(1, 100),
            'code' => 'PROD-'.fake()->unique()->numberBetween(100, 999),
        ]);
    }

    /**
     * Set a specific target and weight.
     */
    public function withDefaults(float $target, float $weight = 1.0): static
    {
        return $this->state(fn (array $attributes) => [
            'default_target' => $target,
            'default_weight' => $weight,
        ]);
    }
}
