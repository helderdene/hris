<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Course $resource
 */
class CourseResource extends JsonResource
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
            'title' => $this->resource->title,
            'code' => $this->resource->code,
            'description' => $this->resource->description,
            'delivery_method' => $this->resource->delivery_method?->value,
            'delivery_method_label' => $this->resource->delivery_method?->label(),
            'provider_type' => $this->resource->provider_type?->value,
            'provider_type_label' => $this->resource->provider_type?->label(),
            'provider_name' => $this->resource->provider_name,
            'duration_hours' => $this->resource->duration_hours,
            'duration_days' => $this->resource->duration_days,
            'formatted_duration' => $this->resource->formatted_duration,
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'level' => $this->resource->level?->value,
            'level_label' => $this->resource->level?->label(),
            'cost' => $this->resource->cost,
            'max_participants' => $this->resource->max_participants,
            'learning_objectives' => $this->resource->learning_objectives,
            'syllabus' => $this->resource->syllabus,
            'created_by' => $this->resource->created_by,
            'creator' => $this->when(
                $this->resource->relationLoaded('creator') && $this->resource->creator,
                fn () => [
                    'id' => $this->resource->creator->id,
                    'full_name' => $this->resource->creator->full_name,
                ]
            ),
            'categories' => $this->when(
                $this->resource->relationLoaded('categories'),
                fn () => $this->resource->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'code' => $category->code,
                ])
            ),
            'prerequisites' => $this->when(
                $this->resource->relationLoaded('prerequisites'),
                fn () => $this->resource->prerequisites->map(fn ($prerequisite) => [
                    'id' => $prerequisite->id,
                    'title' => $prerequisite->title,
                    'code' => $prerequisite->code,
                    'is_mandatory' => (bool) $prerequisite->pivot->is_mandatory,
                ])
            ),
            'required_by_count' => $this->when(
                $this->resource->relationLoaded('requiredBy'),
                fn () => $this->resource->requiredBy->count()
            ),
            'materials' => $this->when(
                $this->resource->relationLoaded('materials'),
                fn () => CourseMaterialResource::collection($this->resource->materials)
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
