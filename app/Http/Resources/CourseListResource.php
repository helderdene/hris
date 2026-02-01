<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for course list views.
 *
 * @property-read \App\Models\Course $resource
 */
class CourseListResource extends JsonResource
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
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'level' => $this->resource->level?->value,
            'level_label' => $this->resource->level?->label(),
            'formatted_duration' => $this->resource->formatted_duration,
            'cost' => $this->resource->cost,
            'categories' => $this->when(
                $this->resource->relationLoaded('categories'),
                fn () => $this->resource->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
            ),
            'prerequisites_count' => $this->when(
                $this->resource->relationLoaded('prerequisites'),
                fn () => $this->resource->prerequisites->count()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
