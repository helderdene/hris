<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for displaying departments in a hierarchical tree structure.
 *
 * @property-read \App\Models\Department $resource
 */
class DepartmentTreeResource extends JsonResource
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
            'status' => $this->resource->status,
            'parent_id' => $this->resource->parent_id,
            'department_head_id' => $this->resource->department_head_id,
            'children' => $this->when(
                $this->resource->relationLoaded('children'),
                fn () => DepartmentTreeResource::collection($this->resource->children)
            ),
        ];
    }
}
