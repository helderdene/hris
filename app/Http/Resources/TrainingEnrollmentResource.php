<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\TrainingEnrollment $resource
 */
class TrainingEnrollmentResource extends JsonResource
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
            'is_pending' => $this->resource->status->isPending(),
            'request_reason' => $this->resource->request_reason,
            'submitted_at' => $this->resource->submitted_at?->toISOString(),
            'enrolled_at' => $this->resource->enrolled_at?->toISOString(),
            'attended_at' => $this->resource->attended_at?->toISOString(),
            'approved_at' => $this->resource->approved_at?->toISOString(),
            'rejected_at' => $this->resource->rejected_at?->toISOString(),
            'rejection_reason' => $this->resource->rejection_reason,
            'cancelled_at' => $this->resource->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->resource->cancellation_reason,
            'notes' => $this->resource->notes,
            'can_cancel' => $this->resource->status->canBeCancelled(),
            'can_mark_attendance' => $this->resource->status->canMarkAttendance(),
            'approver' => $this->when(
                $this->resource->approver_employee_id,
                fn () => [
                    'id' => $this->resource->approver_employee_id,
                    'name' => $this->resource->approver_name,
                    'position' => $this->resource->approver_position,
                    'remarks' => $this->resource->approver_remarks,
                ]
            ),
            'employee' => $this->when(
                $this->resource->relationLoaded('employee') && $this->resource->employee,
                fn () => [
                    'id' => $this->resource->employee->id,
                    'full_name' => $this->resource->employee->full_name,
                    'employee_number' => $this->resource->employee->employee_number,
                    'department' => $this->resource->employee->department?->name,
                    'position' => $this->resource->employee->position?->name,
                ]
            ),
            'session' => $this->when(
                $this->resource->relationLoaded('session') && $this->resource->session,
                fn () => new TrainingSessionListResource($this->resource->session)
            ),
            'enrolled_by' => $this->when(
                $this->resource->relationLoaded('enrolledByEmployee') && $this->resource->enrolledByEmployee,
                fn () => [
                    'id' => $this->resource->enrolledByEmployee->id,
                    'full_name' => $this->resource->enrolledByEmployee->full_name,
                ]
            ),
            'cancelled_by' => $this->when(
                $this->resource->relationLoaded('cancelledByEmployee') && $this->resource->cancelledByEmployee,
                fn () => [
                    'id' => $this->resource->cancelledByEmployee->id,
                    'full_name' => $this->resource->cancelledByEmployee->full_name,
                ]
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
