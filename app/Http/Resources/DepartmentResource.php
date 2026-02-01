<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Department $resource
 */
class DepartmentResource extends JsonResource
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
            'status' => $this->resource->status,
            'parent_id' => $this->resource->parent_id,
            'parent' => $this->when(
                $this->resource->relationLoaded('parent') && $this->resource->parent,
                fn () => [
                    'id' => $this->resource->parent->id,
                    'name' => $this->resource->parent->name,
                    'code' => $this->resource->parent->code,
                ]
            ),
            'children_count' => $this->when(
                $this->resource->relationLoaded('children'),
                fn () => $this->resource->children->count()
            ),
            'department_head_id' => $this->resource->department_head_id,
            'department_head_name' => $this->when(
                $this->resource->relationLoaded('departmentHead') && $this->resource->departmentHead,
                fn () => $this->resource->departmentHead->full_name
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
