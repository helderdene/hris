<?php

namespace Database\Factories;

use App\Enums\WaitlistStatus;
use App\Models\Employee;
use App\Models\TrainingSession;
use App\Models\TrainingWaitlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingWaitlist>
 */
class TrainingWaitlistFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TrainingWaitlist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_session_id' => TrainingSession::factory(),
            'employee_id' => Employee::factory(),
            'status' => WaitlistStatus::Waiting,
            'position' => 1,
            'joined_at' => now(),
            'promoted_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the waitlist entry is waiting.
     */
    public function waiting(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WaitlistStatus::Waiting,
        ]);
    }

    /**
     * Indicate that the waitlist entry has been promoted.
     */
    public function promoted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WaitlistStatus::Promoted,
            'promoted_at' => now(),
        ]);
    }

    /**
     * Indicate that the waitlist entry has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WaitlistStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the waitlist entry is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WaitlistStatus::Cancelled,
        ]);
    }

    /**
     * Set a specific session for the waitlist entry.
     */
    public function forSession(TrainingSession $session): static
    {
        return $this->state(fn (array $attributes) => [
            'training_session_id' => $session->id,
        ]);
    }

    /**
     * Set a specific employee for the waitlist entry.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Set a specific position in the queue.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }
}
