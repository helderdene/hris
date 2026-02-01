<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full resource for a single employee adjustment.
 *
 * @mixin \App\Models\EmployeeAdjustment
 */
class EmployeeAdjustmentResource extends JsonResource
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
                'department' => $this->employee->department?->name,
                'position' => $this->employee->position?->title,
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
            'description' => $this->description,

            // Amount
            'amount' => (float) $this->amount,
            'amount_formatted' => number_format((float) $this->amount, 2),
            'is_taxable' => $this->is_taxable,

            // Frequency
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'frequency_color' => $this->frequency->color(),

            // Recurring settings
            'recurring_start_date' => $this->recurring_start_date?->format('Y-m-d'),
            'recurring_end_date' => $this->recurring_end_date?->format('Y-m-d'),
            'recurring_interval' => $this->recurring_interval?->value,
            'recurring_interval_label' => $this->recurring_interval?->label(),
            'remaining_occurrences' => $this->remaining_occurrences,

            // Balance tracking
            'has_balance_tracking' => $this->has_balance_tracking,
            'total_amount' => $this->has_balance_tracking ? (float) $this->total_amount : null,
            'total_amount_formatted' => $this->has_balance_tracking
                ? number_format((float) $this->total_amount, 2)
                : null,
            'total_applied' => $this->has_balance_tracking ? (float) $this->total_applied : null,
            'total_applied_formatted' => $this->has_balance_tracking
                ? number_format((float) $this->total_applied, 2)
                : null,
            'remaining_balance' => $this->has_balance_tracking ? (float) $this->remaining_balance : null,
            'remaining_balance_formatted' => $this->has_balance_tracking
                ? number_format((float) $this->remaining_balance, 2)
                : null,
            'progress_percentage' => $this->has_balance_tracking
                ? round($this->getProgressPercentage(), 1)
                : null,

            // Target period for one-time adjustments
            'target_payroll_period_id' => $this->target_payroll_period_id,
            'target_payroll_period' => $this->whenLoaded('targetPayrollPeriod', fn () => [
                'id' => $this->targetPayrollPeriod->id,
                'name' => $this->targetPayrollPeriod->name,
                'cutoff_start' => $this->targetPayrollPeriod->cutoff_start->format('Y-m-d'),
                'cutoff_end' => $this->targetPayrollPeriod->cutoff_end->format('Y-m-d'),
            ]),

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'allowed_transitions' => array_map(
                fn ($s) => ['value' => $s->value, 'label' => $s->label()],
                $this->status->allowedTransitions()
            ),

            // Notes and metadata
            'notes' => $this->notes,
            'metadata' => $this->metadata,

            // Applications
            'applications' => AdjustmentApplicationResource::collection(
                $this->whenLoaded('applications')
            ),
            'applications_count' => $this->whenCounted('applications'),

            // Timestamps
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
