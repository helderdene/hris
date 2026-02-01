<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\ComplianceModule $resource
 */
class ComplianceModuleResource extends JsonResource
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
            'compliance_course_id' => $this->resource->compliance_course_id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'content_type' => $this->resource->content_type?->value,
            'content_type_label' => $this->resource->content_type?->label(),
            'content_type_icon' => $this->resource->content_type?->icon(),
            'content' => $this->when(
                ! $this->resource->isAssessment(),
                fn () => $this->resource->content
            ),
            'file_name' => $this->resource->file_name,
            'file_size' => $this->resource->file_size,
            'mime_type' => $this->resource->mime_type,
            'external_url' => $this->resource->external_url,
            'duration_minutes' => $this->resource->duration_minutes,
            'sort_order' => $this->resource->sort_order,
            'is_required' => $this->resource->is_required,
            'passing_score' => $this->resource->passing_score,
            'effective_passing_score' => $this->resource->getEffectivePassingScore(),
            'max_attempts' => $this->resource->max_attempts,
            'effective_max_attempts' => $this->resource->getEffectiveMaxAttempts(),
            'settings' => $this->resource->settings,
            'is_assessment' => $this->resource->isAssessment(),
            'question_count' => $this->when(
                $this->resource->isAssessment() && $this->resource->relationLoaded('assessments'),
                fn () => $this->resource->assessments->count()
            ),
            'total_points' => $this->when(
                $this->resource->isAssessment(),
                fn () => $this->resource->getTotalPoints()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
