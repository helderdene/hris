<?php

namespace Database\Factories;

use App\Enums\OvertimeRequestStatus;
use App\Enums\OvertimeType;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OvertimeRequest>
 */
class OvertimeRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = OvertimeRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minutes = fake()->randomElement([60, 90, 120, 180, 240]);

        return [
            'employee_id' => Employee::factory(),
            'daily_time_record_id' => null,
            'reference_number' => OvertimeRequest::generateReferenceNumber(),
            'overtime_date' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
            'expected_start_time' => '17:00',
            'expected_end_time' => '19:00',
            'expected_minutes' => $minutes,
            'overtime_type' => OvertimeType::Regular,
            'reason' => fake()->sentence(10),
            'status' => OvertimeRequestStatus::Draft,
            'current_approval_level' => 0,
            'total_approval_levels' => 1,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'metadata' => null,
            'created_by' => null,
        ];
    }

    /**
     * Set as draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OvertimeRequestStatus::Draft,
            'current_approval_level' => 0,
        ]);
    }

    /**
     * Set as pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OvertimeRequestStatus::Pending,
            'current_approval_level' => 1,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Set as approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OvertimeRequestStatus::Approved,
            'current_approval_level' => $attributes['total_approval_levels'] ?? 1,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now(),
        ]);
    }

    /**
     * Set as rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OvertimeRequestStatus::Rejected,
            'submitted_at' => now()->subDays(2),
            'rejected_at' => now(),
        ]);
    }

    /**
     * Set as cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OvertimeRequestStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Set a specific overtime date.
     */
    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'overtime_date' => $date,
        ]);
    }

    /**
     * Set expected minutes.
     */
    public function forMinutes(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_minutes' => $minutes,
        ]);
    }

    /**
     * Set overtime type.
     */
    public function ofType(OvertimeType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'overtime_type' => $type,
        ]);
    }

    /**
     * Set the number of approval levels.
     */
    public function withApprovalLevels(int $levels): static
    {
        return $this->state(fn (array $attributes) => [
            'total_approval_levels' => $levels,
        ]);
    }
}
