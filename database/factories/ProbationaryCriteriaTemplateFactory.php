<?php

namespace Database\Factories;

use App\Enums\ProbationaryMilestone;
use App\Models\ProbationaryCriteriaTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProbationaryCriteriaTemplate>
 */
class ProbationaryCriteriaTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProbationaryCriteriaTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'milestone' => fake()->randomElement(ProbationaryMilestone::cases()),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'weight' => fake()->numberBetween(1, 5),
            'sort_order' => fake()->numberBetween(0, 10),
            'min_rating' => 1,
            'max_rating' => 5,
            'is_active' => true,
            'is_required' => true,
        ];
    }

    /**
     * Set as third month milestone.
     */
    public function thirdMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'milestone' => ProbationaryMilestone::ThirdMonth,
        ]);
    }

    /**
     * Set as fifth month milestone.
     */
    public function fifthMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'milestone' => ProbationaryMilestone::FifthMonth,
        ]);
    }

    /**
     * Set as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set as optional.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }
}
