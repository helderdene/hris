<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\DocumentRequest $resource
 */
class DocumentRequestResource extends JsonResource
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
            'employee_id' => $this->resource->employee_id,

            'employee' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => [
                    'id' => $this->resource->employee->id,
                    'employee_number' => $this->resource->employee->employee_number,
                    'full_name' => $this->resource->employee->full_name,
                    'department' => $this->resource->employee->relationLoaded('department') && $this->resource->employee->department
                        ? ['name' => $this->resource->employee->department->name]
                        : null,
                    'position' => $this->resource->employee->relationLoaded('position') && $this->resource->employee->position
                        ? ['name' => $this->resource->employee->position->name]
                        : null,
                ]
            ),

            'document_type' => $this->resource->document_type->value,
            'document_type_label' => $this->resource->document_type->label(),

            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color' => $this->resource->status->color(),

            'notes' => $this->resource->notes,
            'admin_notes' => $this->resource->admin_notes,

            'processed_at' => $this->resource->processed_at?->toISOString(),
            'collected_at' => $this->resource->collected_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
