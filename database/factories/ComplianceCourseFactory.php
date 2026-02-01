<?php

namespace Database\Factories;

use App\Models\ComplianceCourse;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceCourse>
 */
class ComplianceCourseFactory extends Factory
{
    protected $model = ComplianceCourse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory()->published(),
            'days_to_complete' => fake()->randomElement([7, 14, 30, 60, 90]),
            'validity_months' => fake()->randomElement([null, 6, 12, 24]),
            'passing_score' => fake()->randomElement([70.00, 75.00, 80.00, 85.00]),
            'max_attempts' => fake()->randomElement([2, 3, 5]),
            'allow_retakes_after_pass' => false,
            'requires_acknowledgment' => fake()->boolean(30),
            'acknowledgment_text' => null,
            'reminder_days' => [7, 3, 1],
            'escalation_days' => [0, 7],
            'auto_reassign_on_expiry' => true,
            'completion_message' => null,
        ];
    }

    /**
     * Indicate that the course has a strict deadline.
     */
    public function withDeadline(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'days_to_complete' => $days,
        ]);
    }

    /**
     * Indicate that the course has a validity period.
     */
    public function withValidity(int $months = 12): static
    {
        return $this->state(fn (array $attributes) => [
            'validity_months' => $months,
        ]);
    }

    /**
     * Indicate that the course has no validity period (never expires).
     */
    public function noExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'validity_months' => null,
            'auto_reassign_on_expiry' => false,
        ]);
    }

    /**
     * Indicate that acknowledgment is required.
     */
    public function withAcknowledgment(?string $text = null): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_acknowledgment' => true,
            'acknowledgment_text' => $text ?? 'I acknowledge that I have read and understood the contents of this training material.',
        ]);
    }

    /**
     * Indicate that retakes are allowed after passing.
     */
    public function allowRetakes(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_retakes_after_pass' => true,
        ]);
    }

    /**
     * Indicate a high passing score requirement.
     */
    public function highPassingScore(float $score = 90.00): static
    {
        return $this->state(fn (array $attributes) => [
            'passing_score' => $score,
        ]);
    }

    /**
     * Configure custom reminder days.
     *
     * @param  array<int>  $days
     */
    public function withReminders(array $days): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_days' => $days,
        ]);
    }

    /**
     * Configure custom escalation days.
     *
     * @param  array<int>  $days
     */
    public function withEscalation(array $days): static
    {
        return $this->state(fn (array $attributes) => [
            'escalation_days' => $days,
        ]);
    }
}
