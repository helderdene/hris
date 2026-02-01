<?php

namespace Database\Factories;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Assessment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $maxScore = fake()->randomElement([100, 50, 10]);
        $score = fake()->randomFloat(2, 0, $maxScore);

        return [
            'job_application_id' => JobApplication::factory(),
            'test_name' => fake()->sentence(3),
            'type' => fake()->randomElement(AssessmentType::cases()),
            'score' => $score,
            'max_score' => $maxScore,
            'passed' => $score >= ($maxScore * 0.6),
            'assessed_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Mark as passed.
     */
    public function passed(): static
    {
        return $this->state(fn () => ['passed' => true]);
    }

    /**
     * Mark as failed.
     */
    public function failed(): static
    {
        return $this->state(fn () => ['passed' => false]);
    }
}
