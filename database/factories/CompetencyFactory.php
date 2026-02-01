<?php

namespace Database\Factories;

use App\Enums\CompetencyCategory;
use App\Models\Competency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Competency>
 */
class CompetencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Competency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $category = fake()->randomElement(CompetencyCategory::cases());

        return [
            'name' => ucwords($name),
            'code' => strtoupper(substr($category->value, 0, 4).'-'.fake()->unique()->numberBetween(100, 999)),
            'description' => fake()->optional()->paragraph(),
            'category' => $category,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the competency is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate this is a core competency.
     */
    public function core(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => CompetencyCategory::Core,
            'code' => 'CORE-'.fake()->unique()->numberBetween(100, 999),
        ]);
    }

    /**
     * Indicate this is a technical competency.
     */
    public function technical(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => CompetencyCategory::Technical,
            'code' => 'TECH-'.fake()->unique()->numberBetween(100, 999),
        ]);
    }

    /**
     * Indicate this is a leadership competency.
     */
    public function leadership(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => CompetencyCategory::Leadership,
            'code' => 'LEAD-'.fake()->unique()->numberBetween(100, 999),
        ]);
    }

    /**
     * Indicate this is an interpersonal competency.
     */
    public function interpersonal(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => CompetencyCategory::Interpersonal,
            'code' => 'INTP-'.fake()->unique()->numberBetween(100, 999),
        ]);
    }

    /**
     * Indicate this is an analytical competency.
     */
    public function analytical(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => CompetencyCategory::Analytical,
            'code' => 'ANLY-'.fake()->unique()->numberBetween(100, 999),
        ]);
    }

    /**
     * Create a competency with a specific name and code.
     */
    public function withNameAndCode(string $name, string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'code' => $code,
        ]);
    }
}
