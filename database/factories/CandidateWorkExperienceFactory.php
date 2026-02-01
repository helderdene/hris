<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\CandidateWorkExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CandidateWorkExperience>
 */
class CandidateWorkExperienceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CandidateWorkExperience::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-8 years', '-1 year');
        $isCurrent = fake()->boolean(30);

        return [
            'candidate_id' => Candidate::factory(),
            'company' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'description' => fake()->paragraph(),
            'start_date' => $startDate,
            'end_date' => $isCurrent ? null : fake()->dateTimeBetween($startDate, 'now'),
            'is_current' => $isCurrent,
        ];
    }
}
