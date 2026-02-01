<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PerformanceCycleInstance $resource
 */
class PerformanceCycleInstanceResource extends JsonResource
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
            'performance_cycle_id' => $this->resource->performance_cycle_id,
            'performance_cycle' => $this->when(
                $this->resource->relationLoaded('performanceCycle'),
                fn () => new PerformanceCycleResource($this->resource->performanceCycle)
            ),
            'name' => $this->resource->name,
            'year' => $this->resource->year,
            'instance_number' => $this->resource->instance_number,
            'start_date' => $this->resource->start_date?->toDateString(),
            'end_date' => $this->resource->end_date?->toDateString(),
            'date_range' => $this->resource->start_date && $this->resource->end_date
                ? $this->resource->getDateRange()
                : null,
            'formatted_start_date' => $this->resource->start_date?->format('M j, Y'),
            'formatted_end_date' => $this->resource->end_date?->format('M j, Y'),
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->colorClass(),
            'is_editable' => $this->resource->status ? $this->resource->isEditable() : true,
            'is_deletable' => $this->resource->status ? $this->resource->isDeletable() : true,
            'allowed_transitions' => collect($this->resource->status?->allowedTransitions() ?? [])
                ->map(fn ($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])->values()->all(),
            'employee_count' => $this->resource->employee_count,
            'activated_at' => $this->resource->activated_at?->toISOString(),
            'evaluation_started_at' => $this->resource->evaluation_started_at?->toISOString(),
            'closed_at' => $this->resource->closed_at?->toISOString(),
            'closed_by' => $this->resource->closed_by,
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
