<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\GoalKeyResult $resource
 */
class GoalKeyResultResource extends JsonResource
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
            'goal_id' => $this->resource->goal_id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'metric_type' => $this->resource->metric_type?->value,
            'metric_type_label' => $this->resource->metric_type?->label(),
            'metric_unit' => $this->resource->metric_unit,
            'target_value' => $this->resource->target_value,
            'starting_value' => $this->resource->starting_value,
            'current_value' => $this->resource->current_value,
            'achievement_percentage' => $this->resource->achievement_percentage,
            'weight' => $this->resource->weight,
            'status' => $this->resource->status,
            'completed_at' => $this->resource->completed_at?->toISOString(),
            'sort_order' => $this->resource->sort_order,
            'formatted_current_value' => $this->resource->getFormattedCurrentValue(),
            'formatted_target_value' => $this->resource->getFormattedTargetValue(),
            'progress_entries' => $this->when(
                $this->resource->relationLoaded('progressEntries'),
                fn () => GoalProgressEntryResource::collection($this->resource->progressEntries)
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
