<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\KpiTemplate $resource
 */
class KpiTemplateResource extends JsonResource
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
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'description' => $this->resource->description,
            'metric_unit' => $this->resource->metric_unit,
            'default_target' => $this->resource->default_target,
            'default_weight' => $this->resource->default_weight,
            'category' => $this->resource->category,
            'is_active' => $this->resource->is_active,
            'assignments_count' => $this->when(
                $this->resource->relationLoaded('kpiAssignments'),
                fn () => $this->resource->kpiAssignments->count()
            ),
            'active_assignments_count' => $this->when(
                $this->resource->relationLoaded('kpiAssignments'),
                fn () => $this->resource->kpiAssignments
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->count()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
