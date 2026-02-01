<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\ComplianceCourse $resource
 */
class ComplianceCourseResource extends JsonResource
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
            'course' => $this->when(
                $this->resource->relationLoaded('course'),
                fn () => new CourseResource($this->resource->course)
            ),
            'days_to_complete' => $this->resource->days_to_complete,
            'validity_months' => $this->resource->validity_months,
            'passing_score' => (float) $this->resource->passing_score,
            'max_attempts' => $this->resource->max_attempts,
            'allow_retakes_after_pass' => $this->resource->allow_retakes_after_pass,
            'requires_acknowledgment' => $this->resource->requires_acknowledgment,
            'acknowledgment_text' => $this->resource->acknowledgment_text,
            'reminder_days' => $this->resource->reminder_days,
            'escalation_days' => $this->resource->escalation_days,
            'auto_reassign_on_expiry' => $this->resource->auto_reassign_on_expiry,
            'completion_message' => $this->resource->completion_message,
            'created_by' => $this->resource->created_by,
            'creator' => $this->when(
                $this->resource->relationLoaded('creator') && $this->resource->creator,
                fn () => [
                    'id' => $this->resource->creator->id,
                    'full_name' => $this->resource->creator->full_name,
                ]
            ),
            'modules' => $this->when(
                $this->resource->relationLoaded('modules'),
                fn () => ComplianceModuleResource::collection($this->resource->modules)
            ),
            'modules_count' => $this->when(
                $this->resource->relationLoaded('modules'),
                fn () => $this->resource->modules->count()
            ),
            'assignment_rules' => $this->when(
                $this->resource->relationLoaded('assignmentRules'),
                fn () => ComplianceRuleResource::collection($this->resource->assignmentRules)
            ),
            'rules_count' => $this->when(
                $this->resource->relationLoaded('assignmentRules'),
                fn () => $this->resource->assignmentRules->count()
            ),
            'total_duration_minutes' => $this->resource->getTotalDurationMinutes(),
            'required_modules_count' => $this->resource->getRequiredModulesCount(),
            'has_assessments' => $this->resource->hasAssessments(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
