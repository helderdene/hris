<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for listing employee adjustments.
 *
 * @mixin \App\Models\EmployeeAdjustment
 */
class EmployeeAdjustmentListResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
            ]),

            // Classification
            'adjustment_category' => $this->adjustment_category->value,
            'adjustment_category_label' => $this->adjustment_category->label(),
            'adjustment_category_color' => $this->adjustment_category->color(),
            'adjustment_type' => $this->adjustment_type->value,
            'adjustment_type_label' => $this->adjustment_type->label(),
            'adjustment_type_group' => $this->adjustment_type->group(),
            'adjustment_code' => $this->adjustment_code,
            'name' => $this->name,

            // Amount
            'amount' => (float) $this->amount,
            'amount_formatted' => number_format((float) $this->amount, 2),

            // Frequency
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'frequency_color' => $this->frequency->color(),

            // Balance tracking (summary)
            'has_balance_tracking' => $this->has_balance_tracking,
            'remaining_balance' => $this->has_balance_tracking ? (float) $this->remaining_balance : null,
            'remaining_balance_formatted' => $this->has_balance_tracking
                ? number_format((float) $this->remaining_balance, 2)
                : null,
            'progress_percentage' => $this->has_balance_tracking
                ? round($this->getProgressPercentage(), 1)
                : null,

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Dates
            'recurring_start_date' => $this->recurring_start_date?->format('Y-m-d'),
            'recurring_end_date' => $this->recurring_end_date?->format('Y-m-d'),
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
