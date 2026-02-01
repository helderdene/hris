<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentCategory>
 */
class DocumentCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = DocumentCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Documents',
            'description' => fake()->optional()->sentence(),
            'is_predefined' => false,
        ];
    }

    /**
     * Indicate that the category is predefined.
     */
    public function predefined(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_predefined' => true,
        ]);
    }

    /**
     * Indicate that the category is custom.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_predefined' => false,
        ]);
    }
}
