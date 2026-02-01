<?php

namespace Database\Factories;

use App\Models\CourseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseCategory>
 */
class CourseCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CourseCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Leadership',
            'Technical Skills',
            'Soft Skills',
            'Compliance',
            'Safety',
            'Product Training',
            'Sales',
            'Customer Service',
            'Management',
            'Communication',
        ];

        return [
            'name' => fake()->unique()->randomElement($categories).' '.fake()->word(),
            'code' => fake()->unique()->regexify('[A-Z]{2,4}-[0-9]{2}'),
            'description' => fake()->optional()->sentence(),
            'parent_id' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the category is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the category has a parent.
     */
    public function withParent(?CourseCategory $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent?->id ?? CourseCategory::factory(),
        ]);
    }
}
