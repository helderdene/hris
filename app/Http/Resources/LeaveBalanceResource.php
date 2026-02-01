<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for LeaveBalance model.
 *
 * @property-read \App\Models\LeaveBalance $resource
 */
class LeaveBalanceResource extends JsonResource
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
            'employee_id' => $this->resource->employee_id,
            'leave_type_id' => $this->resource->leave_type_id,
            'year' => $this->resource->year,

            // Balance components
            'brought_forward' => (float) $this->resource->brought_forward,
            'earned' => (float) $this->resource->earned,
            'used' => (float) $this->resource->used,
            'pending' => (float) $this->resource->pending,
            'adjustments' => (float) $this->resource->adjustments,
            'expired' => (float) $this->resource->expired,

            // Computed values
            'total_credits' => $this->resource->total_credits,
            'available' => $this->resource->available,

            // Carry-over tracking
            'carry_over_expiry_date' => $this->resource->carry_over_expiry_date?->toDateString(),
            'has_expiring_carry_over' => $this->resource->carry_over_expiry_date !== null
                && $this->resource->brought_forward > $this->resource->expired,

            // Processing timestamps
            'last_accrual_at' => $this->resource->last_accrual_at?->toIso8601String(),
            'year_end_processed_at' => $this->resource->year_end_processed_at?->toIso8601String(),

            // Relationships
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->resource->employee->id,
                    'employee_number' => $this->resource->employee->employee_number,
                    'full_name' => $this->resource->employee->full_name,
                    'department' => $this->resource->employee->department?->name,
                    'position' => $this->resource->employee->position?->name,
                ];
            }),
            'leave_type' => $this->whenLoaded('leaveType', function () {
                return [
                    'id' => $this->resource->leaveType->id,
                    'name' => $this->resource->leaveType->name,
                    'code' => $this->resource->leaveType->code,
                    'leave_category' => $this->resource->leaveType->leave_category?->value,
                    'leave_category_label' => $this->resource->leaveType->leave_category?->label(),
                    'allow_carry_over' => $this->resource->leaveType->allow_carry_over,
                    'max_carry_over_days' => $this->resource->leaveType->max_carry_over_days,
                ];
            }),
            'adjustment_history' => LeaveBalanceAdjustmentResource::collection(
                $this->whenLoaded('adjustmentHistory')
            ),

            // Timestamps
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
