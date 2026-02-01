<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\LeaveApplication
 */
class LeaveApplicationResource extends JsonResource
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

            // Leave type info
            'leave_type_id' => $this->leave_type_id,
            'leave_type' => $this->whenLoaded('leaveType', fn () => [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
                'requires_attachment' => $this->leaveType->requires_attachment,
            ]),

            // Dates and duration
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'date_range' => $this->date_range,
            'total_days' => (float) $this->total_days,
            'is_half_day_start' => $this->is_half_day_start,
            'is_half_day_end' => $this->is_half_day_end,

            // Request details
            'reason' => $this->reason,

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Approval chain
            'current_approval_level' => $this->current_approval_level,
            'total_approval_levels' => $this->total_approval_levels,
            'approvals' => $this->whenLoaded('approvals', fn () => LeaveApplicationApprovalResource::collection($this->approvals)),

            // Balance info
            'leave_balance_id' => $this->leave_balance_id,
            'leave_balance' => $this->whenLoaded('leaveBalance', fn () => [
                'id' => $this->leaveBalance->id,
                'available' => $this->leaveBalance->available,
                'used' => (float) $this->leaveBalance->used,
                'pending' => (float) $this->leaveBalance->pending,
            ]),

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
