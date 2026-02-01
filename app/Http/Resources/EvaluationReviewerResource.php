<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\EvaluationReviewer $resource
 */
class EvaluationReviewerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'performance_cycle_participant_id' => $this->resource->performance_cycle_participant_id,
            'reviewer_employee_id' => $this->resource->reviewer_employee_id,

            // Type and status
            'reviewer_type' => $this->resource->reviewer_type->value,
            'reviewer_type_label' => $this->resource->reviewer_type->label(),
            'reviewer_type_color_class' => $this->resource->reviewer_type->colorClass(),
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color_class' => $this->resource->status->colorClass(),

            // Assignment details
            'assignment_method' => $this->resource->assignment_method->value,
            'assignment_method_label' => $this->resource->assignment_method->label(),
            'assigned_by' => $this->resource->assigned_by,

            // Permissions
            'can_view_kpis' => $this->resource->canViewKpis(),
            'can_edit' => $this->resource->canEdit(),

            // Timestamps
            'invited_at' => $this->resource->invited_at?->toISOString(),
            'started_at' => $this->resource->started_at?->toISOString(),
            'submitted_at' => $this->resource->submitted_at?->toISOString(),
            'declined_at' => $this->resource->declined_at?->toISOString(),
            'decline_reason' => $this->resource->decline_reason,

            // Relations
            'reviewer_employee' => $this->when(
                $this->resource->relationLoaded('reviewerEmployee') && $this->resource->reviewerEmployee,
                fn () => [
                    'id' => $this->resource->reviewerEmployee->id,
                    'full_name' => $this->resource->reviewerEmployee->full_name,
                    'employee_number' => $this->resource->reviewerEmployee->employee_number,
                    'profile_photo_url' => $this->resource->reviewerEmployee->getProfilePhoto()?->getUrl(),
                    'position' => $this->when(
                        $this->resource->reviewerEmployee->relationLoaded('position') && $this->resource->reviewerEmployee->position,
                        fn () => [
                            'id' => $this->resource->reviewerEmployee->position->id,
                            'title' => $this->resource->reviewerEmployee->position->title,
                        ]
                    ),
                    'department' => $this->when(
                        $this->resource->reviewerEmployee->relationLoaded('department') && $this->resource->reviewerEmployee->department,
                        fn () => [
                            'id' => $this->resource->reviewerEmployee->department->id,
                            'name' => $this->resource->reviewerEmployee->department->name,
                        ]
                    ),
                ]
            ),

            'evaluation_response' => $this->when(
                $this->resource->relationLoaded('evaluationResponse') && $this->resource->evaluationResponse,
                fn () => new EvaluationResponseResource($this->resource->evaluationResponse)
            ),

            'participant' => $this->when(
                $this->resource->relationLoaded('participant') && $this->resource->participant,
                fn () => new PerformanceCycleParticipantResource($this->resource->participant)
            ),

            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
