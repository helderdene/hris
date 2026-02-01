<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevelopmentPlanItemResource extends JsonResource
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
            'development_plan_id' => $this->development_plan_id,
            'competency_id' => $this->competency_id,
            'title' => $this->title,
            'description' => $this->description,
            'current_level' => $this->current_level,
            'target_level' => $this->target_level,
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'priority_color' => $this->priority?->colorClass(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->colorClass(),
            'progress_percentage' => $this->progress_percentage,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed fields
            'proficiency_gap' => $this->getProficiencyGap(),
            'has_competency' => $this->hasCompetency(),

            // Relationships
            'competency' => $this->whenLoaded('competency', fn () => [
                'id' => $this->competency->id,
                'name' => $this->competency->name,
                'code' => $this->competency->code,
                'category' => $this->competency->category,
            ]),
            'activities' => DevelopmentActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
