<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\JobRequisition
 */
class JobRequisitionResource extends JsonResource
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
            'reference_number' => $this->reference_number,

            // Position info
            'position_id' => $this->position_id,
            'position' => $this->whenLoaded('position', fn () => [
                'id' => $this->position->id,
                'name' => $this->position->title,
            ]),

            // Department info
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),

            // Requester info
            'requested_by_employee_id' => $this->requested_by_employee_id,
            'requested_by' => $this->whenLoaded('requestedByEmployee', fn () => [
                'id' => $this->requestedByEmployee->id,
                'employee_number' => $this->requestedByEmployee->employee_number,
                'full_name' => $this->requestedByEmployee->full_name,
                'department' => $this->requestedByEmployee->department?->name,
                'position' => $this->requestedByEmployee->position?->name,
            ]),

            // Requisition details
            'headcount' => $this->headcount,
            'employment_type' => $this->employment_type->value,
            'employment_type_label' => $this->employment_type->label(),
            'salary_range_min' => $this->salary_range_min ? (float) $this->salary_range_min : null,
            'salary_range_max' => $this->salary_range_max ? (float) $this->salary_range_max : null,
            'justification' => $this->justification,
            'urgency' => $this->urgency->value,
            'urgency_label' => $this->urgency->label(),
            'urgency_color' => $this->urgency->color(),
            'preferred_start_date' => $this->preferred_start_date?->format('Y-m-d'),
            'requirements' => $this->requirements,
            'remarks' => $this->remarks,

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Approval chain
            'current_approval_level' => $this->current_approval_level,
            'total_approval_levels' => $this->total_approval_levels,
            'approvals' => $this->whenLoaded('approvals', fn () => JobRequisitionApprovalResource::collection($this->approvals)),

            // Cancellation
            'cancellation_reason' => $this->cancellation_reason,

            // Timestamps
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed flags
            'can_be_edited' => $this->can_be_edited,
            'can_be_cancelled' => $this->can_be_cancelled,
        ];
    }
}
