<?php

namespace Database\Factories;

use App\Models\PreboardingTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreboardingTemplate>
 */
class PreboardingTemplateFactory extends Factory
{
    protected $model = PreboardingTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Template',
            'description' => fake()->sentence(),
            'is_default' => false,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    /**
     * Mark the template as default.
     */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /**
     * Mark the template as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
