<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PerformanceCycleParticipant $resource
 */
class PerformanceCycleParticipantResource extends JsonResource
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
            'performance_cycle_instance_id' => $this->resource->performance_cycle_instance_id,
            'employee_id' => $this->resource->employee_id,
            'employee' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => [
                    'id' => $this->resource->employee->id,
                    'full_name' => $this->resource->employee->full_name,
                    'employee_number' => $this->resource->employee->employee_number,
                    'position' => $this->resource->employee->position?->title,
                    'department' => $this->resource->employee->department?->name,
                ]
            ),
            'manager_id' => $this->resource->manager_id,
            'manager' => $this->when(
                $this->resource->relationLoaded('manager') && $this->resource->manager,
                fn () => [
                    'id' => $this->resource->manager->id,
                    'full_name' => $this->resource->manager->full_name,
                    'employee_number' => $this->resource->manager->employee_number,
                ]
            ),
            'is_excluded' => $this->resource->is_excluded,
            'status' => $this->resource->status,
            'status_label' => ucfirst($this->resource->status),
            'completed_at' => $this->resource->completed_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
