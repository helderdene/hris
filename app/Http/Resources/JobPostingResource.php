<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\JobPosting
 */
class JobPostingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,

            // Relationships
            'job_requisition_id' => $this->job_requisition_id,
            'job_requisition' => $this->whenLoaded('jobRequisition', fn () => [
                'id' => $this->jobRequisition->id,
                'reference_number' => $this->jobRequisition->reference_number,
            ]),

            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),

            'position_id' => $this->position_id,
            'position' => $this->whenLoaded('position', fn () => [
                'id' => $this->position->id,
                'name' => $this->position->title,
            ]),

            'created_by_employee_id' => $this->created_by_employee_id,
            'created_by_employee' => $this->whenLoaded('createdByEmployee', fn () => [
                'id' => $this->createdByEmployee->id,
                'full_name' => $this->createdByEmployee->full_name,
            ]),

            // Content
            'description' => $this->description,
            'requirements' => $this->requirements,
            'benefits' => $this->benefits,
            'employment_type' => $this->employment_type->value,
            'employment_type_label' => $this->employment_type->label(),
            'location' => $this->location,
            'salary_display_option' => $this->salary_display_option?->value,
            'salary_display_option_label' => $this->salary_display_option?->label(),
            'salary_range_min' => $this->salary_range_min ? (float) $this->salary_range_min : null,
            'salary_range_max' => $this->salary_range_max ? (float) $this->salary_range_max : null,
            'application_instructions' => $this->application_instructions,

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Timestamps
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'closed_at' => $this->closed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed flags
            'can_be_edited' => $this->can_be_edited,
            'can_be_published' => $this->can_be_published,
            'can_be_closed' => $this->can_be_closed,
            'is_publicly_visible' => $this->is_publicly_visible,
        ];
    }
}
