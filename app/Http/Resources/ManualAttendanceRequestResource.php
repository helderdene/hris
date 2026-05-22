<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ManualAttendanceRequest
 */
class ManualAttendanceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,

            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
                'department' => $this->employee->department?->name,
                'position' => $this->employee->position?->title,
            ]),

            'attendance_date' => $this->attendance_date->format('Y-m-d'),
            'time_in' => $this->time_in,
            'time_out' => $this->time_out,
            'reason' => $this->reason,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            'decided_by_role' => $this->decided_by_role,
            'decision_remarks' => $this->decision_remarks,
            'cancellation_reason' => $this->cancellation_reason,

            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'decided_at' => $this->decided_at?->format('Y-m-d H:i:s'),
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            'can_be_edited' => $this->can_be_edited,
            'can_be_cancelled' => $this->can_be_cancelled,
        ];
    }
}
