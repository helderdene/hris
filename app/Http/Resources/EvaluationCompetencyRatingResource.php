<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\EvaluationCompetencyRating $resource
 */
class EvaluationCompetencyRatingResource extends JsonResource
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
            'position_competency_id' => $this->resource->position_competency_id,

            // Rating data
            'rating' => $this->resource->rating,
            'comments' => $this->resource->comments,

            // Computed values
            'meets_requirement' => $this->resource->meetsRequirement(),
            'proficiency_gap' => $this->resource->getProficiencyGap(),

            // Related position competency
            'position_competency' => $this->when(
                $this->resource->relationLoaded('positionCompetency') && $this->resource->positionCompetency,
                function () {
                    $pc = $this->resource->positionCompetency;

                    return [
                        'id' => $pc->id,
                        'required_proficiency_level' => $pc->required_proficiency_level,
                        'is_mandatory' => $pc->is_mandatory,
                        'weight' => $pc->weight,
                        'competency' => $this->when(
                            $pc->relationLoaded('competency') && $pc->competency,
                            fn () => [
                                'id' => $pc->competency->id,
                                'name' => $pc->competency->name,
                                'code' => $pc->competency->code,
                                'description' => $pc->competency->description,
                                'category' => $pc->competency->category?->value,
                                'category_label' => $pc->competency->category?->label(),
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
