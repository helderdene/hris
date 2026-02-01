<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for list views.
 *
 * @mixin \App\Models\ProbationaryEvaluation
 */
class ProbationaryEvaluationListResource extends JsonResource
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

            // Employee info (minimal)
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
                'department' => $this->employee->department?->name,
                'position' => $this->employee->position?->name,
            ]),

            // Evaluator info
            'evaluator_name' => $this->evaluator_name,

            // Milestone info
            'milestone' => $this->milestone->value,
            'milestone_label' => $this->milestone->shortLabel(),
            'milestone_color' => $this->milestone->color(),
            'milestone_date' => $this->milestone_date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Overall rating (summary)
            'overall_rating' => $this->overall_rating ? (float) $this->overall_rating : null,

            // Recommendation (summary)
            'recommendation' => $this->recommendation?->value,
            'recommendation_short_label' => $this->recommendation?->shortLabel(),
            'recommendation_color' => $this->recommendation?->color(),

            // Timestamps
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),

            // Computed flags
            'is_overdue' => $this->is_overdue,
            'is_final_evaluation' => $this->is_final_evaluation,
        ];
    }
}
