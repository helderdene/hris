<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\HelpArticle $resource
 */
class HelpArticleResource extends JsonResource
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
            'help_category_id' => $this->resource->help_category_id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'excerpt' => $this->resource->excerpt,
            'content' => $this->resource->content,
            'related_article_ids' => $this->resource->related_article_ids,
            'sort_order' => $this->resource->sort_order,
            'is_active' => $this->resource->is_active,
            'is_featured' => $this->resource->is_featured,
            'view_count' => $this->resource->view_count,
            'category' => new HelpCategoryResource($this->whenLoaded('category')),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
