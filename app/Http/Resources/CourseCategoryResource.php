<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\CourseCategory $resource
 */
class CourseCategoryResource extends JsonResource
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
            'courses_count' => $this->when(
                isset($this->resource->courses_count),
                fn () => $this->resource->courses_count
            ),
            'is_active' => $this->resource->is_active,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
