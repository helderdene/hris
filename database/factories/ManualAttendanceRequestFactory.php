<?php

namespace Database\Factories;

use App\Enums\ManualAttendanceRequestStatus;
use App\Models\Employee;
use App\Models\ManualAttendanceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ManualAttendanceRequest>
 */
class ManualAttendanceRequestFactory extends Factory
{
    protected $model = ManualAttendanceRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'reference_number' => ManualAttendanceRequest::generateReferenceNumber(),
            'attendance_date' => fake()->dateTimeBetween('-5 days', '-1 day'),
            'time_in' => '08:00',
            'time_out' => '17:00',
            'reason' => fake()->sentence(8),
            'status' => ManualAttendanceRequestStatus::Draft,
            'submitted_at' => null,
            'decided_at' => null,
            'cancelled_at' => null,
            'decided_by_user_id' => null,
            'decided_by_role' => null,
            'decision_remarks' => null,
            'cancellation_reason' => null,
            'metadata' => null,
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ManualAttendanceRequestStatus::Draft,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => ManualAttendanceRequestStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ManualAttendanceRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ManualAttendanceRequestStatus::Rejected,
            'submitted_at' => now()->subDay(),
            'decided_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ManualAttendanceRequestStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
