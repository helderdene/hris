<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\ComplianceCourse $resource
 */
class ComplianceCourseListResource extends JsonResource
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
            'course_title' => $this->when(
                $this->resource->relationLoaded('course'),
                fn () => $this->resource->course->title
            ),
            'course_code' => $this->when(
                $this->resource->relationLoaded('course'),
                fn () => $this->resource->course->code
            ),
            'course_status' => $this->when(
                $this->resource->relationLoaded('course'),
                fn () => $this->resource->course->status?->value
            ),
            'days_to_complete' => $this->resource->days_to_complete,
            'validity_months' => $this->resource->validity_months,
            'passing_score' => (float) $this->resource->passing_score,
            'modules_count' => $this->when(
                $this->resource->relationLoaded('modules'),
                fn () => $this->resource->modules->count()
            ),
            'rules_count' => $this->when(
                $this->resource->relationLoaded('assignmentRules'),
                fn () => $this->resource->assignmentRules->count()
            ),
            'has_assessments' => $this->resource->hasAssessments(),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
