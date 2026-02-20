<?php

namespace Database\Factories;

use App\Enums\CheckInMethod;
use App\Enums\VisitStatus;
use App\Models\Employee;
use App\Models\Visitor;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VisitorVisit>
 */
class VisitorVisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visitor_id' => Visitor::factory(),
            'work_location_id' => WorkLocation::factory(),
            'host_employee_id' => Employee::factory(),
            'purpose' => fake()->sentence(),
            'status' => VisitStatus::PendingApproval,
            'registration_source' => 'visitor',
            'expected_at' => fake()->dateTimeBetween('now', '+7 days'),
            'registration_token' => Str::random(64),
            'qr_token' => null,
        ];
    }

    /**
     * Indicate the visit is pending approval.
     */
    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VisitStatus::PendingApproval,
            'registration_source' => 'visitor',
        ]);
    }

    /**
     * Indicate the visit is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VisitStatus::Approved,
            'registration_source' => 'visitor',
            'approved_at' => now(),
            'host_approved_at' => now(),
            'qr_token' => Str::random(64),
        ]);
    }

    /**
     * Indicate the visit has been approved by the host only.
     */
    public function hostApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VisitStatus::PendingApproval,
            'registration_source' => 'visitor',
            'host_approved_at' => now(),
        ]);
    }

    /**
     * Indicate the visit has been approved by admin only.
     */
    public function adminApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VisitStatus::PendingApproval,
            'registration_source' => 'visitor',
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate the visit is pre-registered by admin.
     */
    public function preRegistered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VisitStatus::PreRegistered,
            'registration_source' => 'admin',
            'qr_token' => Str::random(64),
        ]);
    }

    /**
     * Indicate the visitor is checked in.
     */
    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VisitStatus::CheckedIn,
            'approved_at' => now()->subHour(),
            'checked_in_at' => now(),
            'check_in_method' => CheckInMethod::Manual,
            'qr_token' => Str::random(64),
        ]);
    }

    /**
     * Indicate the visitor is checked out.
     */
    public function checkedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VisitStatus::CheckedOut,
            'approved_at' => now()->subHours(3),
            'checked_in_at' => now()->subHours(2),
            'checked_out_at' => now(),
            'check_in_method' => CheckInMethod::Manual,
            'qr_token' => Str::random(64),
        ]);
    }

    /**
     * Indicate the visit is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VisitStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
