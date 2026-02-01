<?php

namespace Database\Factories;

use App\Enums\ReferenceRecommendation;
use App\Models\JobApplication;
use App\Models\ReferenceCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReferenceCheck>
 */
class ReferenceCheckFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ReferenceCheck::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'referee_name' => fake()->name(),
            'referee_email' => fake()->safeEmail(),
            'referee_phone' => fake()->optional(0.7)->phoneNumber(),
            'referee_company' => fake()->company(),
            'relationship' => fake()->randomElement(['Manager', 'Colleague', 'Direct Report', 'Client']),
            'contacted' => false,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Mark as contacted with feedback.
     */
    public function contacted(): static
    {
        return $this->state(fn () => [
            'contacted' => true,
            'contacted_at' => now(),
            'feedback' => fake()->paragraph(),
            'recommendation' => fake()->randomElement(ReferenceRecommendation::cases()),
        ]);
    }
}
