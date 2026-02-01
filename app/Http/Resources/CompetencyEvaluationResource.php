<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\CompetencyEvaluation $resource
 */
class CompetencyEvaluationResource extends JsonResource
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
            'position_competency_id' => $this->resource->position_competency_id,
            'self_rating' => $this->resource->self_rating,
            'self_comments' => $this->resource->self_comments,
            'manager_rating' => $this->resource->manager_rating,
            'manager_comments' => $this->resource->manager_comments,
            'final_rating' => $this->resource->final_rating,
            'evidence' => $this->resource->evidence ?? [],
            'evaluated_at' => $this->resource->evaluated_at?->toISOString(),
            'is_complete' => $this->resource->isComplete(),
            'rating_gap' => $this->resource->getRatingGap(),
            'proficiency_gap' => $this->resource->getProficiencyGap(),
            'position_competency' => $this->when(
                $this->resource->relationLoaded('positionCompetency'),
                fn () => new PositionCompetencyResource($this->resource->positionCompetency)
            ),
            'participant' => $this->when(
                $this->resource->relationLoaded('performanceCycleParticipant'),
                fn () => [
                    'id' => $this->resource->performanceCycleParticipant->id,
                    'employee' => $this->resource->performanceCycleParticipant->employee ? [
                        'id' => $this->resource->performanceCycleParticipant->employee->id,
                        'full_name' => $this->resource->performanceCycleParticipant->employee->full_name,
                        'employee_code' => $this->resource->performanceCycleParticipant->employee->employee_code,
                    ] : null,
                ]
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
