<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\ComplianceAssignment $resource
 */
class ComplianceAssignmentResource extends JsonResource
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
            'compliance_course' => $this->when(
                $this->resource->relationLoaded('complianceCourse'),
                fn () => new ComplianceCourseListResource($this->resource->complianceCourse)
            ),
            'employee_id' => $this->resource->employee_id,
            'employee' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => [
                    'id' => $this->resource->employee->id,
                    'employee_number' => $this->resource->employee->employee_number,
                    'full_name' => $this->resource->employee->full_name,
                    'department' => $this->resource->employee->department?->name,
                    'position' => $this->resource->employee->position?->title,
                ]
            ),
            'assignment_rule_id' => $this->resource->assignment_rule_id,
            'assignment_rule' => $this->when(
                $this->resource->relationLoaded('assignmentRule') && $this->resource->assignmentRule,
                fn () => [
                    'id' => $this->resource->assignmentRule->id,
                    'name' => $this->resource->assignmentRule->name,
                ]
            ),
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->color(),
            'assigned_date' => $this->resource->assigned_date?->toDateString(),
            'due_date' => $this->resource->due_date?->toDateString(),
            'started_at' => $this->resource->started_at?->toISOString(),
            'completed_at' => $this->resource->completed_at?->toISOString(),
            'final_score' => $this->resource->final_score,
            'attempts_used' => $this->resource->attempts_used,
            'total_time_minutes' => $this->resource->total_time_minutes,
            'valid_until' => $this->resource->valid_until?->toDateString(),
            'exemption_reason' => $this->resource->exemption_reason,
            'exempted_by' => $this->when(
                $this->resource->relationLoaded('exemptedByEmployee') && $this->resource->exemptedByEmployee,
                fn () => [
                    'id' => $this->resource->exemptedByEmployee->id,
                    'full_name' => $this->resource->exemptedByEmployee->full_name,
                ]
            ),
            'exempted_at' => $this->resource->exempted_at?->toISOString(),
            'assigned_by' => $this->when(
                $this->resource->relationLoaded('assignedByEmployee') && $this->resource->assignedByEmployee,
                fn () => [
                    'id' => $this->resource->assignedByEmployee->id,
                    'full_name' => $this->resource->assignedByEmployee->full_name,
                ]
            ),
            'acknowledgment_completed' => $this->resource->acknowledgment_completed,
            'acknowledged_at' => $this->resource->acknowledged_at?->toISOString(),
            'progress' => $this->when(
                $this->resource->relationLoaded('progress'),
                fn () => ComplianceProgressResource::collection($this->resource->progress)
            ),
            'completion_percentage' => $this->resource->getCompletionPercentage(),
            'days_until_due' => $this->resource->getDaysUntilDue(),
            'is_overdue' => $this->resource->isOverdue(),
            'is_due_soon' => $this->resource->isDueSoon(),
            'is_expiring_soon' => $this->resource->isExpiringSoon(),
            'certificate' => $this->when(
                $this->resource->relationLoaded('certificate') && $this->resource->certificate,
                fn () => new ComplianceCertificateResource($this->resource->certificate)
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
