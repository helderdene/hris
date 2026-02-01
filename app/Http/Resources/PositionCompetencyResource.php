<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PositionCompetency $resource
 */
class PositionCompetencyResource extends JsonResource
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
            'position_id' => $this->resource->position_id,
            'competency_id' => $this->resource->competency_id,
            'job_level' => $this->resource->job_level?->value,
            'job_level_label' => $this->resource->job_level?->label(),
            'required_proficiency_level' => $this->resource->required_proficiency_level,
            'is_mandatory' => $this->resource->is_mandatory,
            'weight' => $this->resource->weight,
            'notes' => $this->resource->notes,
            'position' => $this->when(
                $this->resource->relationLoaded('position'),
                fn () => [
                    'id' => $this->resource->position->id,
                    'title' => $this->resource->position->title,
                    'code' => $this->resource->position->code,
                ]
            ),
            'competency' => $this->when(
                $this->resource->relationLoaded('competency'),
                fn () => new CompetencyResource($this->resource->competency)
            ),
            'proficiency_level' => $this->when(
                $this->resource->relationLoaded('proficiencyLevel'),
                fn () => $this->resource->proficiencyLevel ? new ProficiencyLevelResource($this->resource->proficiencyLevel) : null
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
