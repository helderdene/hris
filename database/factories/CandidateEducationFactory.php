<?php

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Models\Candidate;
use App\Models\CandidateEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CandidateEducation>
 */
class CandidateEducationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CandidateEducation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-10 years', '-2 years');
        $isCurrent = fake()->boolean(20);

        return [
            'candidate_id' => Candidate::factory(),
            'education_level' => fake()->randomElement(EducationLevel::cases()),
            'institution' => fake()->company().' University',
            'field_of_study' => fake()->randomElement(['Computer Science', 'Information Technology', 'Business Administration', 'Engineering', 'Accounting', 'Psychology']),
            'start_date' => $startDate,
            'end_date' => $isCurrent ? null : fake()->dateTimeBetween($startDate, 'now'),
            'is_current' => $isCurrent,
        ];
    }
}
