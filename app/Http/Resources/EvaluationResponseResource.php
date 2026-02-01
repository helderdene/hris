<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\EvaluationResponse $resource
 */
class EvaluationResponseResource extends JsonResource
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
            'evaluation_reviewer_id' => $this->resource->evaluation_reviewer_id,

            // Narrative feedback
            'strengths' => $this->resource->strengths,
            'areas_for_improvement' => $this->resource->areas_for_improvement,
            'overall_comments' => $this->resource->overall_comments,
            'development_suggestions' => $this->resource->development_suggestions,

            // Status
            'is_draft' => $this->resource->is_draft,
            'is_complete' => $this->resource->isComplete(),
            'completion_percentage' => $this->resource->getCompletionPercentage(),

            // Averages
            'average_competency_rating' => $this->resource->getAverageCompetencyRating(),
            'average_kpi_rating' => $this->resource->getAverageKpiRating(),

            // Timestamps
            'last_saved_at' => $this->resource->last_saved_at?->toISOString(),
            'submitted_at' => $this->resource->submitted_at?->toISOString(),

            // Relations
            'competency_ratings' => $this->when(
                $this->resource->relationLoaded('competencyRatings'),
                fn () => EvaluationCompetencyRatingResource::collection($this->resource->competencyRatings)
            ),

            'kpi_ratings' => $this->when(
                $this->resource->relationLoaded('kpiRatings'),
                fn () => EvaluationKpiRatingResource::collection($this->resource->kpiRatings)
            ),

            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
