<?php

namespace Database\Factories;

use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Candidate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-45 years', '-20 years'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'zip_code' => fake()->postcode(),
            'country' => 'Philippines',
            'linkedin_url' => fake()->optional(0.5)->url(),
            'portfolio_url' => fake()->optional(0.3)->url(),
            'skills' => fake()->randomElements(
                ['PHP', 'Laravel', 'Vue.js', 'React', 'JavaScript', 'TypeScript', 'Python', 'SQL', 'Docker', 'AWS', 'Git', 'CSS', 'HTML'],
                fake()->numberBetween(2, 6)
            ),
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => null,
        ];
    }
}
