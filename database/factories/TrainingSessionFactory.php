<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Course;
use App\Models\Employee;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TrainingSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+3 months');
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(0, 2).' days');

        $hasTime = fake()->boolean(70);

        return [
            'course_id' => Course::factory(),
            'title' => fake()->optional(0.3)->sentence(3),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'start_time' => $hasTime ? fake()->time('H:i') : null,
            'end_time' => $hasTime ? fake()->time('H:i') : null,
            'location' => fake()->optional(0.6)->randomElement([
                'Conference Room A',
                'Training Center',
                'Main Office - Floor 3',
                'Meeting Room B',
                'Auditorium',
            ]),
            'virtual_link' => fake()->optional(0.4)->url(),
            'status' => SessionStatus::Draft,
            'max_participants' => fake()->optional(0.7)->numberBetween(5, 30),
            'notes' => fake()->optional(0.3)->paragraph(),
            'instructor_employee_id' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the session is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Draft,
        ]);
    }

    /**
     * Indicate that the session is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Scheduled,
        ]);
    }

    /**
     * Indicate that the session is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::InProgress,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the session is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Completed,
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->subDays(6)->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the session is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Cancelled,
        ]);
    }

    /**
     * Set dates in the future (upcoming).
     */
    public function upcoming(): static
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+2 months');
        $endDate = (clone $startDate)->modify('+1 day');

        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);
    }

    /**
     * Set dates in the past.
     */
    public function past(): static
    {
        $startDate = fake()->dateTimeBetween('-2 months', '-1 week');
        $endDate = (clone $startDate)->modify('+1 day');

        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'status' => SessionStatus::Completed,
        ]);
    }

    /**
     * Set a specific course for the session.
     */
    public function forCourse(Course $course): static
    {
        return $this->state(fn (array $attributes) => [
            'course_id' => $course->id,
        ]);
    }

    /**
     * Set a specific instructor for the session.
     */
    public function withInstructor(Employee $instructor): static
    {
        return $this->state(fn (array $attributes) => [
            'instructor_employee_id' => $instructor->id,
        ]);
    }

    /**
     * Set a specific capacity for the session.
     */
    public function withCapacity(int $capacity): static
    {
        return $this->state(fn (array $attributes) => [
            'max_participants' => $capacity,
        ]);
    }

    /**
     * Set no capacity limit.
     */
    public function unlimitedCapacity(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_participants' => null,
        ]);
    }

    /**
     * Set a specific creator for the session.
     */
    public function createdBy(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $employee->id,
        ]);
    }
}
