<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OvertimeRequest
 */
class OvertimeRequestResource extends JsonResource
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

            // Employee info
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
                'department' => $this->employee->department?->name,
                'position' => $this->employee->position?->name,
            ]),

            // Overtime details
            'overtime_date' => $this->overtime_date->format('Y-m-d'),
            'expected_start_time' => $this->expected_start_time,
            'expected_end_time' => $this->expected_end_time,
            'expected_minutes' => $this->expected_minutes,
            'expected_hours_formatted' => $this->expected_hours_formatted,
            'overtime_type' => $this->overtime_type->value,
            'overtime_type_label' => $this->overtime_type->label(),
            'overtime_type_color' => $this->overtime_type->color(),

            // Request details
            'reason' => $this->reason,

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Approval chain
            'current_approval_level' => $this->current_approval_level,
            'total_approval_levels' => $this->total_approval_levels,
            'approvals' => $this->whenLoaded('approvals', fn () => OvertimeRequestApprovalResource::collection($this->approvals)),

            // DTR link
            'daily_time_record_id' => $this->daily_time_record_id,

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
