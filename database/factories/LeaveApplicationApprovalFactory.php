<?php

namespace Database\Factories;

use App\Enums\LeaveApprovalDecision;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveApplicationApproval>
 */
class LeaveApplicationApprovalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = LeaveApplicationApproval::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_application_id' => LeaveApplication::factory(),
            'approval_level' => 1,
            'approver_type' => 'supervisor',
            'approver_employee_id' => Employee::factory(),
            'approver_name' => fake()->name(),
            'approver_position' => fake()->jobTitle(),
            'decision' => LeaveApprovalDecision::Pending,
            'remarks' => null,
            'decided_at' => null,
        ];
    }

    /**
     * Set specific approval level.
     */
    public function level(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_level' => $level,
        ]);
    }

    /**
     * Set approver type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'approver_type' => $type,
        ]);
    }

    /**
     * Set as pending decision.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => LeaveApprovalDecision::Pending,
            'decided_at' => null,
            'remarks' => null,
        ]);
    }

    /**
     * Set as approved.
     */
    public function approved(?string $remarks = null): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => LeaveApprovalDecision::Approved,
            'decided_at' => now(),
            'remarks' => $remarks,
        ]);
    }

    /**
     * Set as rejected.
     */
    public function rejected(string $reason = 'Request denied'): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => LeaveApprovalDecision::Rejected,
            'decided_at' => now(),
            'remarks' => $reason,
        ]);
    }

    /**
     * Set as skipped.
     */
    public function skipped(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => LeaveApprovalDecision::Skipped,
            'decided_at' => now(),
            'remarks' => $reason ?? 'Approval level skipped',
        ]);
    }

    /**
     * Set a specific approver.
     */
    public function forApprover(Employee $approver): static
    {
        return $this->state(fn (array $attributes) => [
            'approver_employee_id' => $approver->id,
            'approver_name' => $approver->full_name,
            'approver_position' => $approver->position?->name,
        ]);
    }
}
