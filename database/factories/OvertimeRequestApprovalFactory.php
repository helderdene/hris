<?php

namespace Database\Factories;

use App\Enums\OvertimeApprovalDecision;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRequestApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OvertimeRequestApproval>
 */
class OvertimeRequestApprovalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = OvertimeRequestApproval::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'overtime_request_id' => OvertimeRequest::factory(),
            'approval_level' => 1,
            'approver_type' => 'supervisor',
            'approver_employee_id' => Employee::factory(),
            'approver_name' => fake()->name(),
            'approver_position' => fake()->jobTitle(),
            'decision' => OvertimeApprovalDecision::Pending,
            'remarks' => null,
            'decided_at' => null,
        ];
    }

    /**
     * Set the approval level.
     */
    public function level(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_level' => $level,
        ]);
    }

    /**
     * Set the approver type.
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
            'decision' => OvertimeApprovalDecision::Pending,
            'decided_at' => null,
        ]);
    }

    /**
     * Set as approved decision.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => OvertimeApprovalDecision::Approved,
            'remarks' => fake()->optional()->sentence(),
            'decided_at' => now(),
        ]);
    }

    /**
     * Set as rejected decision.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => OvertimeApprovalDecision::Rejected,
            'remarks' => fake()->sentence(),
            'decided_at' => now(),
        ]);
    }

    /**
     * Set as skipped decision.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => OvertimeApprovalDecision::Skipped,
            'remarks' => 'Approval level skipped',
            'decided_at' => now(),
        ]);
    }
}
