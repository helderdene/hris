<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for training enrollment requests with approval data.
 *
 * @property-read \App\Models\TrainingEnrollment $resource
 */
class TrainingEnrollmentRequestResource extends JsonResource
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
            'reference_number' => $this->resource->reference_number,
            'training_session_id' => $this->resource->training_session_id,
            'employee_id' => $this->resource->employee_id,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color' => $this->resource->status->badgeColor(),
            'request_reason' => $this->resource->request_reason,
            'submitted_at' => $this->resource->submitted_at?->toISOString(),
            'enrolled_at' => $this->resource->enrolled_at?->toISOString(),
            'approved_at' => $this->resource->approved_at?->toISOString(),
            'rejected_at' => $this->resource->rejected_at?->toISOString(),
            'rejection_reason' => $this->resource->rejection_reason,
            'cancelled_at' => $this->resource->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->resource->cancellation_reason,
            'notes' => $this->resource->notes,
            'can_approve' => $this->resource->status->canBeApproved(),
            'can_reject' => $this->resource->status->canBeRejected(),
            'can_cancel' => $this->resource->status->canBeCancelled(),
            'approver' => [
                'id' => $this->resource->approver_employee_id,
                'name' => $this->resource->approver_name,
                'position' => $this->resource->approver_position,
                'remarks' => $this->resource->approver_remarks,
            ],
            'employee' => $this->when(
                $this->resource->relationLoaded('employee') && $this->resource->employee,
                fn () => [
                    'id' => $this->resource->employee->id,
                    'full_name' => $this->resource->employee->full_name,
                    'employee_number' => $this->resource->employee->employee_number,
                    'department' => $this->when(
                        $this->resource->employee->relationLoaded('department'),
                        fn () => $this->resource->employee->department?->name
                    ),
                    'position' => $this->when(
                        $this->resource->employee->relationLoaded('position'),
                        fn () => $this->resource->employee->position?->name
                    ),
                    'avatar_url' => $this->resource->employee->avatar_url,
                ]
            ),
            'session' => $this->when(
                $this->resource->relationLoaded('session') && $this->resource->session,
                fn () => [
                    'id' => $this->resource->session->id,
                    'title' => $this->resource->session->display_title,
                    'start_date' => $this->resource->session->start_date?->toISOString(),
                    'end_date' => $this->resource->session->end_date?->toISOString(),
                    'date_range' => $this->resource->session->date_range,
                    'time_range' => $this->resource->session->time_range,
                    'location' => $this->resource->session->location,
                    'status' => $this->resource->session->status->value,
                    'enrolled_count' => $this->resource->session->enrolled_count,
                    'max_participants' => $this->resource->session->effective_max_participants,
                    'is_full' => $this->resource->session->is_full,
                    'course' => $this->when(
                        $this->resource->session->relationLoaded('course') && $this->resource->session->course,
                        fn () => [
                            'id' => $this->resource->session->course->id,
                            'title' => $this->resource->session->course->title,
                            'code' => $this->resource->session->course->code,
                        ]
                    ),
                ]
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
