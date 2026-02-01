<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Interview;
use App\Models\InterviewPanelist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InterviewPanelist>
 */
class InterviewPanelistFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = InterviewPanelist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interview_id' => Interview::factory(),
            'employee_id' => Employee::factory(),
            'is_lead' => false,
        ];
    }

    /**
     * Set as lead panelist.
     */
    public function lead(): static
    {
        return $this->state(fn () => ['is_lead' => true]);
    }

    /**
     * Set with submitted feedback.
     */
    public function withFeedback(): static
    {
        return $this->state(fn () => [
            'feedback' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
            'feedback_submitted_at' => now(),
        ]);
    }
}
