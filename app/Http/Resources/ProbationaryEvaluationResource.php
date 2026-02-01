<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProbationaryEvaluation
 */
class ProbationaryEvaluationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Employee info
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
                'department' => $this->employee->department?->name,
                'position' => $this->employee->position?->name,
                'hire_date' => $this->employee->hire_date?->format('Y-m-d'),
                'employment_type' => $this->employee->employment_type?->value,
                'employment_type_label' => $this->employee->employment_type?->label(),
            ]),

            // Evaluator info
            'evaluator_id' => $this->evaluator_id,
            'evaluator_name' => $this->evaluator_name,
            'evaluator_position' => $this->evaluator_position,
            'evaluator' => $this->whenLoaded('evaluator', fn () => [
                'id' => $this->evaluator->id,
                'employee_number' => $this->evaluator->employee_number,
                'full_name' => $this->evaluator->full_name,
                'department' => $this->evaluator->department?->name,
                'position' => $this->evaluator->position?->name,
            ]),

            // Milestone info
            'milestone' => $this->milestone->value,
            'milestone_label' => $this->milestone->label(),
            'milestone_short_label' => $this->milestone->shortLabel(),
            'milestone_color' => $this->milestone->color(),
            'milestone_date' => $this->milestone_date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Evaluation content
            'criteria_ratings' => $this->criteria_ratings,
            'overall_rating' => $this->overall_rating ? (float) $this->overall_rating : null,
            'strengths' => $this->strengths,
            'areas_for_improvement' => $this->areas_for_improvement,
            'manager_comments' => $this->manager_comments,

            // Recommendation (for final evaluation)
            'recommendation' => $this->recommendation?->value,
            'recommendation_label' => $this->recommendation?->label(),
            'recommendation_short_label' => $this->recommendation?->shortLabel(),
            'recommendation_color' => $this->recommendation?->color(),
            'recommendation_conditions' => $this->recommendation_conditions,
            'extension_months' => $this->extension_months,
            'recommendation_reason' => $this->recommendation_reason,

            // Previous evaluation (for 5th month showing 3rd month results)
            'previous_evaluation_id' => $this->previous_evaluation_id,
            'previous_evaluation' => $this->whenLoaded('previousEvaluation', fn () => [
                'id' => $this->previousEvaluation->id,
                'milestone' => $this->previousEvaluation->milestone->value,
                'milestone_label' => $this->previousEvaluation->milestone->label(),
                'overall_rating' => $this->previousEvaluation->overall_rating ? (float) $this->previousEvaluation->overall_rating : null,
                'strengths' => $this->previousEvaluation->strengths,
                'areas_for_improvement' => $this->previousEvaluation->areas_for_improvement,
                'manager_comments' => $this->previousEvaluation->manager_comments,
                'criteria_ratings' => $this->previousEvaluation->criteria_ratings,
                'status' => $this->previousEvaluation->status->value,
                'approved_at' => $this->previousEvaluation->approved_at?->format('Y-m-d H:i:s'),
            ]),

            // Approvals
            'approvals' => $this->whenLoaded('approvals', fn () => ProbationaryEvaluationApprovalResource::collection($this->approvals)),

            // Timestamps
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed flags
            'can_be_edited' => $this->can_be_edited,
            'is_overdue' => $this->is_overdue,
            'is_final_evaluation' => $this->is_final_evaluation,
            'requires_recommendation' => $this->requires_recommendation,
        ];
    }
}
