<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\EvaluationKpiRating $resource
 */
class EvaluationKpiRatingResource extends JsonResource
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
            'evaluation_response_id' => $this->resource->evaluation_response_id,
            'kpi_assignment_id' => $this->resource->kpi_assignment_id,

            // Rating data
            'rating' => $this->resource->rating,
            'comments' => $this->resource->comments,

            // Computed values
            'achievement_percentage' => $this->resource->getAchievementPercentage(),
            'is_kpi_achieved' => $this->resource->isKpiAchieved(),

            // Related KPI assignment
            'kpi_assignment' => $this->when(
                $this->resource->relationLoaded('kpiAssignment') && $this->resource->kpiAssignment,
                function () {
                    $ka = $this->resource->kpiAssignment;

                    return [
                        'id' => $ka->id,
                        'target_value' => $ka->target_value,
                        'actual_value' => $ka->actual_value,
                        'weight' => $ka->weight,
                        'achievement_percentage' => $ka->achievement_percentage,
                        'status' => $ka->status?->value,
                        'status_label' => $ka->status?->label(),
                        'kpi_template' => $this->when(
                            $ka->relationLoaded('kpiTemplate') && $ka->kpiTemplate,
                            fn () => [
                                'id' => $ka->kpiTemplate->id,
                                'name' => $ka->kpiTemplate->name,
                                'code' => $ka->kpiTemplate->code,
                                'description' => $ka->kpiTemplate->description,
                                'metric_unit' => $ka->kpiTemplate->metric_unit,
                                'category' => $ka->kpiTemplate->category,
                            ]
                        ),
                    ];
                }
            ),

            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
