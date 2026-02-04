<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\HelpCategory $resource
 */
class HelpCategoryResource extends JsonResource
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
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'icon' => $this->resource->icon,
            'sort_order' => $this->resource->sort_order,
            'is_active' => $this->resource->is_active,
            'articles_count' => $this->when(
                $this->resource->relationLoaded('articles'),
                fn () => $this->resource->articles->count(),
                fn () => $this->resource->articles()->count()
            ),
            'active_articles_count' => $this->when(
                $this->resource->relationLoaded('activeArticles'),
                fn () => $this->resource->activeArticles->count()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
