<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\CourseMaterial $resource
 */
class CourseMaterialResource extends JsonResource
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
            'course_id' => $this->resource->course_id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'file_name' => $this->resource->file_name,
            'file_size' => $this->resource->file_size,
            'formatted_file_size' => $this->resource->formatted_file_size,
            'mime_type' => $this->resource->mime_type,
            'material_type' => $this->resource->material_type?->value,
            'material_type_label' => $this->resource->material_type?->label(),
            'external_url' => $this->resource->external_url,
            'download_url' => $this->resource->getDownloadUrl(),
            'sort_order' => $this->resource->sort_order,
            'uploaded_by' => $this->resource->uploaded_by,
            'uploader' => $this->when(
                $this->resource->relationLoaded('uploader') && $this->resource->uploader,
                fn () => [
                    'id' => $this->resource->uploader->id,
                    'full_name' => $this->resource->uploader->full_name,
                ]
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
