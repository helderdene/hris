<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PayrollEntry $resource
 */
class PayrollEntryResource extends JsonResource
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
            'payroll_period_id' => $this->resource->payroll_period_id,
            'payroll_period' => $this->when(
                $this->resource->relationLoaded('payrollPeriod'),
                fn () => $this->resource->payrollPeriod
                    ? new PayrollPeriodResource($this->resource->payrollPeriod)
                    : null
            ),
            'employee_id' => $this->resource->employee_id,

            // Flat employee snapshot fields for Vue compatibility
            'employee_number' => $this->resource->employee_number,
            'employee_name' => $this->resource->employee_name,
            'department_name' => $this->resource->department_name,
            'position_name' => $this->resource->position_name,
            'basic_salary_snapshot' => $this->resource->basic_salary_snapshot,
            'pay_type_snapshot' => $this->resource->pay_type_snapshot?->value,
            'pay_type_label' => $this->resource->pay_type_snapshot?->label(),

            // Flat DTR summary fields
            'days_worked' => $this->resource->days_worked,
            'total_regular_minutes' => $this->resource->total_regular_minutes,
            'total_late_minutes' => $this->resource->total_late_minutes,
            'total_undertime_minutes' => $this->resource->total_undertime_minutes,
            'total_overtime_minutes' => $this->resource->total_overtime_minutes,
            'total_night_diff_minutes' => $this->resource->total_night_diff_minutes,
            'absent_days' => $this->resource->absent_days,
            'holiday_days' => $this->resource->holiday_days,

            // Flat earnings fields
            'basic_pay' => $this->resource->basic_pay,
            'overtime_pay' => $this->resource->overtime_pay,
            'night_diff_pay' => $this->resource->night_diff_pay,
            'holiday_pay' => $this->resource->holiday_pay,
            'allowances_total' => $this->resource->allowances_total,
            'bonuses_total' => $this->resource->bonuses_total,
            'gross_pay' => $this->resource->gross_pay,

            // Flat deductions fields
            'sss_employee' => $this->resource->sss_employee,
            'sss_employer' => $this->resource->sss_employer,
            'philhealth_employee' => $this->resource->philhealth_employee,
            'philhealth_employer' => $this->resource->philhealth_employer,
            'pagibig_employee' => $this->resource->pagibig_employee,
            'pagibig_employer' => $this->resource->pagibig_employer,
            'withholding_tax' => $this->resource->withholding_tax,
            'other_deductions_total' => $this->resource->other_deductions_total,
            'total_deductions' => $this->resource->total_deductions,
            'total_employer_contributions' => $this->resource->total_employer_contributions,

            'net_pay' => $this->resource->net_pay,

            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->colorClass(),
            'is_editable' => $this->resource->isEditable(),
            'can_recompute' => $this->resource->canRecompute(),
            'allowed_transitions' => array_map(
                fn ($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                $this->resource->status?->allowedTransitions() ?? []
            ),

            'computed_at' => $this->resource->computed_at?->toISOString(),
            'computed_at_formatted' => $this->resource->computed_at?->format('M j, Y g:i A'),
            'computed_by' => $this->resource->computed_by,
            'approved_at' => $this->resource->approved_at?->toISOString(),
            'approved_at_formatted' => $this->resource->approved_at?->format('M j, Y g:i A'),
            'approved_by' => $this->resource->approved_by,
            'remarks' => $this->resource->remarks,

            'earning_items' => $this->when(
                $this->resource->relationLoaded('earnings'),
                fn () => PayrollEarningResource::collection($this->resource->earnings)
            ),
            'deduction_items' => $this->when(
                $this->resource->relationLoaded('deductions'),
                fn () => PayrollDeductionResource::collection($this->resource->deductions)
            ),

            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
