<?php

namespace Database\Factories;

use App\Enums\CompletionStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingEnrollment>
 */
class TrainingEnrollmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TrainingEnrollment::class;

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
            'status' => EnrollmentStatus::Confirmed,
            'enrolled_at' => now(),
            'attended_at' => null,
            'notes' => fake()->optional(0.2)->sentence(),
            'enrolled_by' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    /**
     * Indicate that the enrollment is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the enrollment is attended.
     */
    public function attended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Attended,
            'attended_at' => now(),
        ]);
    }

    /**
     * Indicate that the enrollment is completed with assessment.
     */
    public function completed(float $score = 85.00): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Attended,
            'attended_at' => now(),
            'completion_status' => CompletionStatus::Completed,
            'assessment_score' => $score,
        ]);
    }

    /**
     * Indicate that the enrollment has a certificate.
     */
    public function withCertificate(?string $number = null): static
    {
        return $this->state(fn (array $attributes) => [
            'certificate_number' => $number ?? 'CERT-'.strtoupper(fake()->bothify('??####')),
            'certificate_issued_at' => now(),
        ]);
    }

    /**
     * Indicate that the enrollment is a no-show.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::NoShow,
        ]);
    }

    /**
     * Indicate that the enrollment is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Set a specific session for the enrollment.
     */
    public function forSession(TrainingSession $session): static
    {
        return $this->state(fn (array $attributes) => [
            'training_session_id' => $session->id,
        ]);
    }

    /**
     * Set a specific employee for the enrollment.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Set the enrolledBy to an employee.
     */
    public function enrolledBy(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'enrolled_by' => $employee->id,
        ]);
    }

    /**
     * Set the cancelledBy to an employee.
     */
    public function cancelledBy(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled_by' => $employee->id,
            'status' => EnrollmentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
