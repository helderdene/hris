<?php

namespace Database\Factories;

use App\Enums\ProbationaryApprovalDecision;
use App\Models\Employee;
use App\Models\ProbationaryEvaluation;
use App\Models\ProbationaryEvaluationApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProbationaryEvaluationApproval>
 */
class ProbationaryEvaluationApprovalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProbationaryEvaluationApproval::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'probationary_evaluation_id' => ProbationaryEvaluation::factory(),
            'approval_level' => 1,
            'approver_type' => 'hr',
            'approver_employee_id' => null,
            'approver_name' => null,
            'approver_position' => null,
            'decision' => ProbationaryApprovalDecision::Pending,
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
            'decision' => ProbationaryApprovalDecision::Pending,
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
            'decision' => ProbationaryApprovalDecision::Approved,
            'decided_at' => now(),
            'remarks' => $remarks,
        ]);
    }

    /**
     * Set as rejected.
     */
    public function rejected(string $reason = 'Evaluation rejected'): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => ProbationaryApprovalDecision::Rejected,
            'decided_at' => now(),
            'remarks' => $reason,
        ]);
    }

    /**
     * Set as revision requested.
     */
    public function revisionRequested(string $reason = 'Please revise the evaluation'): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => ProbationaryApprovalDecision::RevisionRequested,
            'decided_at' => now(),
            'remarks' => $reason,
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
